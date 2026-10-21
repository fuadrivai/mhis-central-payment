<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\H2hApiLog;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordAuditActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $this->actor($request);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $resolvedActor = $this->actor($request);
            if ($resolvedActor['type'] !== 'guest') {
                $actor = $resolvedActor;
            }

            $status = match (true) {
                $exception instanceof ValidationException => $exception->response?->getStatusCode()
                    ?? ($this->expectsJsonResponse($request) ? $exception->status : Response::HTTP_FOUND),
                $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ModelNotFoundException => Response::HTTP_NOT_FOUND,
                $exception instanceof AuthorizationException => Response::HTTP_FORBIDDEN,
                $exception instanceof AuthenticationException => $this->expectsJsonResponse($request)
                    ? Response::HTTP_UNAUTHORIZED : Response::HTTP_FOUND,
                $exception instanceof TokenMismatchException => Response::HTTP_FOUND,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            $this->record($request, $status, $actor, failed: true);

            throw $exception;
        }

        $resolvedActor = $this->actor($request);
        if ($resolvedActor['type'] !== 'guest') {
            $actor = $resolvedActor;
        }

        $failed = $response->getStatusCode() >= Response::HTTP_BAD_REQUEST;
        if ($response->isRedirect() && $request->hasSession() && $request->session()->has('errors')) {
            $failed = true;
        }

        $this->record($request, $response->getStatusCode(), $actor, failed: $failed, response: $response);

        return $response;
    }

    /**
     * @return array{type: string, id: int|string|null, name: ?string}
     */
    private function actor(Request $request): array
    {
        $user = $request->user();
        if ($user instanceof User) {
            return ['type' => 'user', 'id' => $user->getKey(), 'name' => $user->name];
        }

        $branch = $request->attributes->get('branch');
        if ($branch instanceof Branch) {
            return ['type' => 'branch', 'id' => $branch->getKey(), 'name' => $branch->name];
        }

        $bank = $request->attributes->get('audit_actor');
        if ($bank instanceof Bank) {
            return ['type' => 'bank', 'id' => $bank->getKey(), 'name' => $bank->name];
        }

        return ['type' => 'guest', 'id' => null, 'name' => null];
    }

    /**
     * @param  array{type: string, id: int|string|null, name: ?string}  $actor
     */
    private function record(
        Request $request,
        int $status,
        array $actor,
        bool $failed = false,
        ?Response $response = null,
    ): void {
        $route = $request->route();
        $routeName = $route?->getName();
        $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        $event = match (true) {
            $routeName === 'login.store' => 'auth.login',
            $routeName === 'logout' => 'auth.logout',
            $request->is('api/*') => 'api.request',
            $isRead => 'web.read',
            default => 'web.write',
        };
        $target = null;

        foreach ($route?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                $target = [
                    'type' => class_basename($parameter),
                    'id' => (string) $parameter->getKey(),
                ];
                break;
            }
        }

        $integrationError = $this->invoiceCreationError($request, $status, $response);

        if ($integrationError !== null) {
            $target = [
                'type' => 'Invoice',
                'id' => (string) $integrationError['invoice_id'],
            ];
        }

        $properties = [
            'outcome' => $this->outcome($request, $event, $status, $failed),
            'input_fields' => $isRead ? [] : $this->safeFieldNames(
                $request->isJson() ? $request->json()->all() : $request->request->all(),
            ),
            'query_fields' => $this->safeFieldNames($request->query()),
        ];

        if ($integrationError !== null) {
            $properties['integration_error'] = [
                'endpoint' => $integrationError['endpoint'],
                'response_status' => $integrationError['response_status'],
                'message' => $integrationError['message'],
            ];
        }

        try {
            AuditLog::query()->create([
                'event' => $event,
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'actor_name' => $actor['name'],
                'method' => $request->method(),
                'route_name' => $routeName,
                'path' => $route?->uri() ?? $request->path(),
                'target_type' => $target['type'] ?? null,
                'target_id' => $target['id'] ?? null,
                'status' => $status,
                'properties' => $properties,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to persist audit activity.', [
                'exception_class' => $exception::class,
                'route_name' => $routeName,
            ]);
        }
    }

    /**
     * @return array{invoice_id: int, endpoint: string, response_status: ?int, message: ?string}|null
     */
    private function invoiceCreationError(Request $request, int $status, ?Response $response): ?array
    {
        if (
            $request->route()?->getName() !== 'api.v1.invoices.store'
            || $status < Response::HTTP_INTERNAL_SERVER_ERROR
            || ! $response instanceof JsonResponse
        ) {
            return null;
        }

        $responseData = $response->getData(true);
        $invoiceId = filter_var($responseData['id'] ?? null, FILTER_VALIDATE_INT);

        if ($invoiceId === false || $invoiceId === null) {
            return null;
        }

        $h2hLog = H2hApiLog::query()
            ->where('invoice_id', $invoiceId)
            ->where('endpoint', '/api/internal/va/create')
            ->latest('id')
            ->first(['endpoint', 'response_status', 'response_payload', 'error_message']);

        if ($h2hLog === null) {
            return null;
        }

        return [
            'invoice_id' => (int) $invoiceId,
            'endpoint' => $h2hLog->endpoint,
            'response_status' => $h2hLog->response_status,
            'message' => $this->safeIntegrationErrorMessage(
                $h2hLog->error_message,
                $h2hLog->response_payload ?? [],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function safeIntegrationErrorMessage(?string $errorMessage, array $payload): ?string
    {
        $message = $errorMessage;

        if ($message === null || trim($message) === '') {
            $error = $payload['error'] ?? null;
            $message = is_string($error) ? $error : null;

            if (is_array($error)) {
                $message = $error['message'] ?? $error['description'] ?? null;
            }

            $message ??= $payload['message'] ?? $payload['error_description'] ?? null;
        }

        if (! is_string($message) || trim($message) === '') {
            return null;
        }

        $message = strip_tags($message);
        $message = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace(
            '/\b(api[_ -]?key|authorization|password|secret|token)\b\s*[:=]\s*[^\s,;]+/i',
            '$1=[redacted]',
            $message,
        ) ?? $message;

        return Str::limit(trim($message), 500);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    private function safeFieldNames(array $input): array
    {
        return collect(array_keys(Arr::dot($input)))
            ->reject(fn (string|int $field): bool => preg_match(
                '/(?:password|secret|token|api.?key|authorization|cookie|signature)/i',
                (string) $field,
            ) === 1)
            ->map(fn (string|int $field): string => Str::limit((string) $field, 120, ''))
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }

    private function outcome(Request $request, string $event, int $status, bool $failed): string
    {
        if ($event === 'auth.login') {
            return $request->user() === null ? 'failure' : 'success';
        }

        return ! $failed && $status < Response::HTTP_BAD_REQUEST ? 'success' : 'failure';
    }

    private function expectsJsonResponse(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
