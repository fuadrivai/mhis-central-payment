<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'event' => ['nullable', Rule::in(['web.read', 'web.write', 'auth.login', 'auth.logout', 'api.request'])],
            'actor_type' => ['nullable', Rule::in(['user', 'branch', 'bank', 'guest'])],
            'route' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100, 200])],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 10);

        $logs = AuditLog::query()
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['actor_type'] ?? null, fn ($query, string $actorType) => $query->where('actor_type', $actorType))
            ->when($filters['route'] ?? null, function ($query, string $route): void {
                $query->where(function ($query) use ($route): void {
                    $query->where('route_name', 'like', '%'.$route.'%')
                        ->orWhere('path', 'like', '%'.$route.'%');
                });
            })
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->where('created_at', '<=', $date.' 23:59:59.999999'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('audit-logs.index', compact('logs', 'filters', 'perPage'));
    }
}
