@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Audit Logs</h4>
        <p class="text-muted mb-0">Riwayat akses halaman, autentikasi, aktivitas perubahan, dan request API. Nilai payload dan kredensial tidak disimpan.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-3 align-items-end"
                id="audit-log-filter-form">
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
                <div class="col-12 d-flex flex-wrap align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                    <div class="ms-auto">
                        <label for="per_page" class="form-label mb-1">Baris per halaman</label>
                        <select id="per_page" name="per_page" class="form-select">
                            @foreach ([10, 25, 50, 100, 200] as $pageLength)
                                <option value="{{ $pageLength }}" @selected($perPage === $pageLength)>
                                    {{ $pageLength }}
                                </option>
                            @endforeach
                        </select>
                    </div>
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
                        <th>Detail Error</th>
                        <th>IP</th>
                        <th class="text-center">Detail</th>
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
                            <td>
                                @if ($error = $log->properties['integration_error'] ?? null)
                                    <span class="fw-semibold">
                                        H2H{{ $error['response_status'] ? ' '.$error['response_status'] : '' }}
                                    </span>
                                    @if ($error['message'])
                                        <br><small class="text-danger">{{ $error['message'] }}</small>
                                    @else
                                        <br><small class="text-muted">{{ $error['endpoint'] }}</small>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $log->ip_address ?? '—' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary audit-log-detail-button"
                                    data-bs-toggle="modal" data-bs-target="#audit-log-detail-modal"
                                    data-audit-detail="{{ json_encode([
                                        'id' => $log->id,
                                        'created_at' => $log->created_at->format('d/m/Y H:i:s'),
                                        'event' => $log->event,
                                        'actor_type' => $log->actor_type,
                                        'actor_id' => $log->actor_id,
                                        'actor_name' => $log->actor_name,
                                        'method' => $log->method,
                                        'route_name' => $log->route_name,
                                        'path' => $log->path,
                                        'target_type' => $log->target_type,
                                        'target_id' => $log->target_id,
                                        'status' => $log->status,
                                        'ip_address' => $log->ip_address,
                                        'properties' => $log->properties ?? [],
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                                    aria-label="Lihat detail audit log #{{ $log->id }}">
                                    <i class="bx bx-detail"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5">Belum ada aktivitas yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-body border-top">{{ $logs->links() }}</div>
        @endif
    </div>

    <div class="modal fade" id="audit-log-detail-modal" tabindex="-1" aria-labelledby="audit-log-detail-title"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="audit-log-detail-title">Detail Audit Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-4" id="audit-log-detail-fields"></dl>
                    <h6>Properties</h6>
                    <pre class="bg-light rounded p-3 mb-0 text-wrap" id="audit-log-detail-properties"></pre>
                    <small class="text-muted d-block mt-3">
                        Detail menampilkan metadata yang tersimpan; nilai payload request dan kredensial tidak ditampilkan.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content-script')
    <script>
        const auditDetailLabels = {
            id: 'ID',
            created_at: 'Waktu',
            event: 'Aktivitas',
            actor_type: 'Tipe actor',
            actor_id: 'ID actor',
            actor_name: 'Nama actor',
            method: 'Metode',
            route_name: 'Nama route',
            path: 'Path',
            target_type: 'Tipe target',
            target_id: 'ID target',
            status: 'Status HTTP',
            ip_address: 'IP address',
        };

        document.querySelectorAll('.audit-log-detail-button').forEach(function (button) {
            button.addEventListener('click', function () {
                const details = JSON.parse(this.dataset.auditDetail);
                const fields = document.getElementById('audit-log-detail-fields');
                fields.replaceChildren();

                Object.entries(auditDetailLabels).forEach(function ([key, label]) {
                    const term = document.createElement('dt');
                    term.className = 'col-sm-4 text-muted';
                    term.textContent = label;

                    const description = document.createElement('dd');
                    description.className = 'col-sm-8';
                    description.textContent = details[key] ?? '—';

                    fields.append(term, description);
                });

                document.getElementById('audit-log-detail-properties').textContent =
                    JSON.stringify(details.properties ?? {}, null, 2);
            });
        });

        document.getElementById('per_page').addEventListener('change', function () {
            document.getElementById('audit-log-filter-form').submit();
        });
    </script>
    @endsection
