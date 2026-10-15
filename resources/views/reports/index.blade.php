@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="mb-1">Laporan Pembayaran</h4>
            <p class="text-muted mb-0">Ringkasan pembayaran, tagihan, rekonsiliasi, cabang, dan transaksi bermasalah.</p>
        </div>
        <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-outline-primary">
            <i class="bx bx-download me-1"></i> Ekspor CSV
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Jumlah {{ $filters['report'] === 'summary' || $filters['report'] === 'branches' ? 'transaksi' : 'data' }}</small>
                    <h4 class="mb-0">{{ number_format($metrics['total_count']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Total nominal</small>
                    <h4 class="mb-0">Rp {{ number_format((float) $metrics['total_amount'], 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label for="report" class="form-label">Jenis laporan</label>
                    <select id="report" name="report" class="form-select">
                        @foreach ($reportOptions as $key => $label)
                            <option value="{{ $key }}" @selected($filters['report'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="from" class="form-label">Dari tanggal</label>
                    <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="to" class="form-label">Sampai tanggal</label>
                    <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="branch_id" class="form-label">Cabang</label>
                    <select id="branch_id" name="branch_id" class="form-select">
                        <option value="">Semua cabang</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="bank_id" class="form-label">Bank</label>
                    <select id="bank_id" name="bank_id" class="form-select">
                        <option value="">Semua bank</option>
                        @foreach ($banks as $bank)
                            <option value="{{ $bank->id }}" @selected((string) ($filters['bank_id'] ?? '') === (string) $bank->id)>{{ $bank->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-4">
                    <label for="va_type" class="form-label">Jenis VA</label>
                    <select id="va_type" name="va_type" class="form-select">
                        <option value="">Semua</option>
                        <option value="fixed" @selected(($filters['va_type'] ?? '') === 'fixed')>Fixed</option>
                        <option value="dynamic" @selected(($filters['va_type'] ?? '') === 'dynamic')>Dynamic</option>
                    </select>
                </div>
                @if ($statuses !== [])
                    <div class="col-lg-3 col-md-4">
                        @if ($filters['report'] === 'issues')
                            <label for="status" class="form-label">Status / kode HTTP</label>
                            <input id="status" name="status" value="{{ $filters['status'] ?? '' }}" class="form-control"
                                list="issue-statuses" placeholder="Semua status">
                            <datalist id="issue-statuses">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </datalist>
                        @else
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">Semua status</option>
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endif
                <div class="col-lg-5 col-md-8">
                    <label for="search" class="form-label">Pencarian</label>
                    <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control"
                        maxlength="100" placeholder="NIS, nama siswa, invoice, atau referensi">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        @foreach ($columns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($columns as $key => $label)
                                <td>
                                    @if ($key === 'amount')
                                        {{ $row->{$key} === null ? '—' : 'Rp '.number_format((float) $row->{$key}, 0, ',', '.') }}
                                    @elseif ($key === 'transaction_count')
                                        {{ number_format((int) $row->{$key}) }}
                                    @elseif ($key === 'period' && $row->{$key})
                                        {{ \Illuminate\Support\Carbon::parse($row->{$key})->format('d/m/Y H:i') }}
                                    @elseif ($key === 'status')
                                        <span class="badge bg-label-secondary">{{ str_replace('_', ' ', $row->{$key} ?? '—') }}</span>
                                    @else
                                        {{ $row->{$key} ?? '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($columns) }}" class="text-center py-5">Tidak ada data yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-body border-top">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
