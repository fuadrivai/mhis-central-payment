@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Pengaturan Cabang</h4>
            <p class="text-muted mb-0">Kelola identitas cabang dan prefix Virtual Account MHIS.</p>
        </div>
        <a href="{{ route('settings.branches.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Tambah Cabang
        </a>
    </div>

    @if (session('branch_api_key'))
        <div class="alert alert-warning" role="alert">
            <h6 class="alert-heading mb-2">
                <i class="bx bx-key me-1"></i> API key {{ session('branch_api_key_name') }}
            </h6>
            <p class="mb-2">
                Key ini hanya ditampilkan sekarang. Salin dan simpan di secret manager sistem pemanggil.
                Jangan kirim melalui chat atau commit ke source code.
            </p>
            <code class="d-block bg-white rounded p-3 text-break user-select-all">{{ session('branch_api_key') }}</code>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Cabang</th>
                        <th>Kode</th>
                        <th>Prefix Fixed VA</th>
                        <th>Prefix Dynamic VA</th>
                        <th>API Key</th>
                        <th>Data Terkait</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $branch->name }}</span>
                            </td>
                            <td><span class="badge bg-label-secondary">{{ $branch->code }}</span></td>
                            <td><code>{{ $branch->va_fixed_prefix }}</code></td>
                            <td><code>{{ $branch->va_dynamic_prefix }}</code></td>
                            <td>
                                @if ($branch->api_key)
                                    <span class="badge bg-label-success">Terkonfigurasi</span>
                                    <div class="small text-muted">Nilai disembunyikan</div>
                                @else
                                    <span class="badge bg-label-warning">Belum dibuat</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <span title="Invoice">{{ number_format($branch->invoices_count) }} invoice</span><br>
                                <span title="Siswa">{{ number_format($branch->students_count) }} siswa</span>,
                                <span title="Virtual Accounts">{{ number_format($branch->virtual_accounts_count) }} VA</span>
                            </td>
                            <td>
                                @if ($branch->is_active)
                                    <span class="badge bg-label-success">Aktif</span>
                                @else
                                    <span class="badge bg-label-secondary">Nonaktif</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('settings.branches.edit', $branch) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bx bx-edit-alt me-1"></i> Edit
                                </a>
                                <form action="{{ route('settings.branches.rotate-api-key', $branch) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Rotasi API key akan langsung menonaktifkan key lama. Lanjutkan?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                        <i class="bx bx-refresh me-1"></i> Rotasi Key
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bx bx-buildings fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-3">Belum ada data cabang.</p>
                                <a href="{{ route('settings.branches.create') }}" class="btn btn-primary btn-sm">Tambah Cabang</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($branches->hasPages())
            <div class="card-body border-top">
                {{ $branches->links() }}
            </div>
        @endif
    </div>

    <div class="alert alert-info mt-4 mb-0" role="alert">
        <i class="bx bx-info-circle me-1"></i>
        Cabang yang sudah memiliki data pembayaran tidak dapat dihapus. Prefix VA dan kode cabang dikunci setelah digunakan
        agar data historis tetap konsisten.
    </div>
@endsection
