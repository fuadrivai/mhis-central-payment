@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Fixed Virtual Accounts</h4>
            <p class="text-muted mb-0">VA tetap siswa untuk pembayaran SPP dan Annual Fee.</p>
        </div>
        <a href="{{ route('virtual-accounts.fixed.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Buat Fixed VA
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nomor VA</th>
                        <th>Bank</th>
                        <th>Siswa</th>
                        <th>NIS</th>
                        <th>Cabang</th>
                        <th>Nominal</th>
                        <th>Status H2H</th>
                        <th>Kedaluwarsa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($virtualAccounts as $virtualAccount)
                        <tr>
                            <td><code class="user-select-all">{{ $virtualAccount->virtual_account_no }}</code></td>
                            <td>{{ $virtualAccount->bank?->name ?? '-' }}</td>
                            <td>
                                <span class="fw-semibold">{{ $virtualAccount->student?->name ?? $virtualAccount->virtual_account_name }}</span>
                                @if ($virtualAccount->student?->grade_class)
                                    <div class="small text-muted">{{ $virtualAccount->student->grade_class }}</div>
                                @endif
                            </td>
                            <td>{{ $virtualAccount->student?->nis ?? '-' }}</td>
                            <td>
                                <span class="fw-semibold">{{ $virtualAccount->branch?->name ?? '-' }}</span>
                                @if ($virtualAccount->branch?->code)
                                    <div class="small text-muted">{{ $virtualAccount->branch->code }}</div>
                                @endif
                            </td>
                            <td>Rp {{ number_format((float) $virtualAccount->current_amount, 2, ',', '.') }}</td>
                            <td>
                                @switch($virtualAccount->status)
                                    @case('active')
                                        <span class="badge bg-label-success">Aktif</span>
                                    @break
                                    @case('verification_required')
                                        <span class="badge bg-label-warning">Perlu Verifikasi</span>
                                    @break
                                    @case('pending_h2h')
                                        <span class="badge bg-label-info">Menunggu H2H</span>
                                    @break
                                    @case('failed')
                                        <span class="badge bg-label-danger">Ditolak H2H</span>
                                    @break
                                    @default
                                        <span class="badge bg-label-secondary">{{ $virtualAccount->status }}</span>
                                @endswitch
                            </td>
                            <td>{{ $virtualAccount->expired_at?->format('d M Y H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bx bx-credit-card fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-3">Belum ada Fixed VA.</p>
                                <a href="{{ route('virtual-accounts.fixed.create') }}" class="btn btn-primary btn-sm">
                                    Buat Fixed VA pertama
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($virtualAccounts->hasPages())
            <div class="card-body border-top">
                {{ $virtualAccounts->links() }}
            </div>
        @endif
    </div>

    <div class="alert alert-info mt-4 mb-0" role="alert">
        <i class="bx bx-info-circle me-1"></i>
        VA yang memerlukan verifikasi tidak boleh dikirim ulang ke endpoint create sampai statusnya dikonfirmasi ke backend H2H.
    </div>
@endsection
