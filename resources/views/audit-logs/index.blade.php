@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Audit Logs</h4>
        <p class="text-muted mb-0">Riwayat akses halaman, autentikasi, aktivitas perubahan, dan request API. Nilai payload dan kredensial tidak disimpan.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="event" class="form-label">Aktivitas</label>
                    <select id="event" name="event" class="form-select">
                        <option value="">Semua aktivitas</option>
                        @foreach ([
                            'web.read' => 'Lihat halaman',
                            'web.write' => 'Perubahan',
                            'auth.login' => 'Login',
                            'auth.logout' => 'Logout',
                            'api.request' => 'Request API',
                        ] as $event => $label)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="actor_type" class="form-label">Actor</label>
                    <select id="actor_type" name="actor_type" class="form-select">
                        <option value="">Semua actor</option>
                        @foreach (['user' => 'Pengguna', 'branch' => 'Cabang API', 'bank' => 'Bank', 'guest' => 'Guest'] as $type => $label)
                            <option value="{{ $type }}" @selected(($filters['actor_type'] ?? '') === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="route" class="form-label">Route</label>
                    <input id="route" name="route" value="{{ $filters['route'] ?? '' }}" class="form-control"
                        maxlength="100" placeholder="Nama atau path route">
                </div>
                <div class="col-md-2">
                    <label for="from" class="form-label">Dari tanggal</label>
                    <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="to" class="form-label">Sampai tanggal</label>
                    <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Actor</th>
                        <th>Aktivitas</th>
                        <th>Route / Target</th>
                        <th>Field Request</th>
                        <th>Status</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <span class="fw-semibold">{{ $log->actor_name ?? 'Guest' }}</span>
                                <br><small class="text-muted">{{ $log->actor_type }}{{ $log->actor_id ? ' #'.$log->actor_id : '' }}</small>
                            </td>
                            <td>{{ str($log->event)->replace('.', ' ')->title() }}</td>
                            <td>
                                <span class="fw-semibold">{{ $log->route_name ?? $log->path }}</span>
                                @if ($log->target_type)
                                    <br><small class="text-muted">{{ $log->target_type }} #{{ $log->target_id }}</small>
                                @else
                                    <br><small class="text-muted">{{ $log->method }} {{ $log->path }}</small>
                                @endif
                            </td>
                            <td>
                                @php($fields = array_merge($log->properties['input_fields'] ?? [], $log->properties['query_fields'] ?? []))
                                {{ $fields === [] ? '—' : implode(', ', $fields) }}
                            </td>
                            <td>
                                @php($outcome = $log->properties['outcome'] ?? 'failure')
                                <span class="badge {{ $outcome === 'success' ? 'bg-label-success' : 'bg-label-danger' }}">
                                    {{ $log->status }} · {{ str($outcome)->title() }}
                                </span>
                            </td>
                            <td>{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5">Belum ada aktivitas yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-body border-top">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
