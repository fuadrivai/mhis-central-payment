<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
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

        $this->record($request, $response->getStatusCode(), $actor, failed: $failed);

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
    private function record(Request $request, int $status, array $actor, bool $failed = false): void
    {
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

        $properties = [
            'outcome' => $this->outcome($request, $event, $status, $failed),
            'input_fields' => $isRead ? [] : $this->safeFieldNames(
                $request->isJson() ? $request->json()->all() : $request->request->all(),
            ),
            'query_fields' => $this->safeFieldNames($request->query()),
        ];

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
