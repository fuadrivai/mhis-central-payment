@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Fixed Virtual Accounts</h4>
            <p class="text-muted mb-0">VA tetap siswa untuk pembayaran SPP dan Annual Fee.</p>
        </div>
        @if (auth()->user()->hasPermission('fixed_virtual_accounts.create'))
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('virtual-accounts.fixed.bulk.create') }}" class="btn btn-outline-primary">
                    <i class="bx bx-upload me-1"></i> Bulk Import
                </a>
                <a href="{{ route('virtual-accounts.fixed.create') }}" class="btn btn-primary">
                    <i class="bx bx-plus me-1"></i> Buat Fixed VA
                </a>
            </div>
        @endif
    </div>

    @if (session('bulk_fixed_va_failures'))
        <div class="alert alert-warning" role="alert">
            <p class="fw-semibold mb-2">Sebagian baris belum berhasil dibuat:</p>
            <ul class="mb-0">
                @foreach (session('bulk_fixed_va_failures') as $failure)
                    <li>
                        Baris {{ $failure['row'] }} (NIS {{ $failure['nis'] }}):
                        @switch($failure['outcome'])
                            @case(\App\Services\Contracts\VirtualAccountServiceInterface::OUTCOME_UNKNOWN)
                            @case(\App\Services\Contracts\VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR)
                                Status H2H belum dapat dipastikan. Verifikasi ke bank sebelum mengimpor ulang.
                            @break
                            @default
                                Ditolak backend H2H. Periksa log integrasi sebelum mencoba kembali.
                        @endswitch
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('virtual-accounts.fixed.index') }}" class="row g-2 align-items-end">
                <div class="col-md-8 col-lg-9">
                    <label for="search" class="form-label">Cari Fixed VA</label>
                    <input id="search" name="search" type="search" value="{{ $search }}"
                        class="form-control" placeholder="Cari NIS, nama siswa, nomor VA, atau cabang">
                </div>
                <div class="col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-search me-1"></i> Cari
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('virtual-accounts.fixed.index') }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
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
                        @if (auth()->user()->hasPermission('fixed_virtual_accounts.create'))
                            <th class="text-center">Aksi</th>
                        @endif
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
                            @if (auth()->user()->hasPermission('fixed_virtual_accounts.create'))
                                <td class="text-center">
                                    @if ($virtualAccount->status === 'active' && $virtualAccount->student)
                                        <a href="{{ route('virtual-accounts.fixed.edit', $virtualAccount) }}"
                                            class="btn btn-sm btn-outline-primary" aria-label="Edit Fixed VA {{ $virtualAccount->virtual_account_no }}">
                                            <i class="bx bx-edit-alt me-1"></i> Edit
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasPermission('fixed_virtual_accounts.create') ? 9 : 8 }}" class="text-center py-5">
                                <i class="bx bx-credit-card fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-3">{{ $search !== '' ? 'Fixed VA tidak ditemukan.' : 'Belum ada Fixed VA.' }}</p>
                                @if (auth()->user()->hasPermission('fixed_virtual_accounts.create'))
                                    <a href="{{ route('virtual-accounts.fixed.create') }}" class="btn btn-primary btn-sm">
                                        Buat Fixed VA pertama
                                    </a>
                                @endif
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
