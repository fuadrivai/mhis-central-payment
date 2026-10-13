@extends('template.main')

@section('content-style')
@endsection

@section('content')
    <!-- Welcome Header Banner -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h3 class="text-white mb-1">Selamat Datang, {{ auth()->user()->name }}! 👋</h3>
                            <p class="mb-0 text-white-50">
                                <strong>MHIS Central Payment</strong> — Sistem Pembayaran Terpusat Mutiara Harapan Islamic School
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-white text-primary fw-semibold px-3 py-2">
                                <i class="bx bx-shield-quarter me-1"></i>
                                Role: {{ ucwords(str_replace('_', ' ', auth()->user()->role ?? 'super_admin')) }}
                            </span>
                            <span class="badge bg-label-dark text-white fw-normal px-3 py-2">
                                <i class="bx bx-check-circle me-1"></i> Mandiri H2H Ready
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistic Cards (Data Contoh / Placeholder) -->
    <div class="row">
        <!-- Invoices -->
        <div class="col-sm-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted fw-semibold d-block mb-1">Total Tagihan (Invoices)</span>
                            <div class="d-flex align-items-baseline mt-2">
                                <h4 class="mb-0 me-2">0</h4>
                                <small class="text-muted">Invoice</small>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <span class="badge bg-label-secondary">Data Contoh</span>
                            </small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-receipt fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Virtual Accounts -->
        <div class="col-sm-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted fw-semibold d-block mb-1">Mandiri Virtual Account</span>
                            <div class="d-flex align-items-baseline mt-2">
                                <h4 class="mb-0 me-2">0</h4>
                                <small class="text-muted">VA Aktif</small>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <span class="badge bg-label-info">Bank Mandiri H2H</span>
                            </small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-credit-card fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Pembayaran Masuk -->
        <div class="col-sm-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted fw-semibold d-block mb-1">Pembayaran Lunas (Paid)</span>
                            <div class="d-flex align-items-baseline mt-2">
                                <h4 class="mb-0 me-2">Rp 0</h4>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <span class="badge bg-label-success">0 Transaksi Lunas</span>
                            </small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-check-shield fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Payment -->
        <div class="col-sm-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted fw-semibold d-block mb-1">Menunggu Pembayaran</span>
                            <div class="d-flex align-items-baseline mt-2">
                                <h4 class="mb-0 me-2">0</h4>
                                <small class="text-muted">Pending</small>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <span class="badge bg-label-warning">Perlu Verifikasi</span>
                            </small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-time-five fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Middle Section: Mandiri H2H Endpoints & Quick Shortcuts -->
    <div class="row">
        <!-- Mandiri H2H Service Status -->
        <div class="col-md-7 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-transfer text-primary me-2"></i>Koneksi Backend Mandiri H2H
                    </h5>
                    <span class="badge bg-label-success">Ready for Integration</span>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Sistem ini bertindak sebagai Central Payment Hub yang mengelola penagihan dan terhubung ke backend Mandiri Host to Host melalui 3 endpoint internal:
                    </p>

                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <span class="badge bg-label-primary me-2 font-monospace">POST</span>
                                <code>/api/internal/va/create</code>
                                <div class="text-muted small mt-1">Digunakan untuk men-generate Virtual Account baru saat invoice diterbitkan.</div>
                            </div>
                            <span class="badge bg-label-primary rounded-pill">Create VA</span>
                        </div>

                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <span class="badge bg-label-info me-2 font-monospace">GET</span>
                                <code>/api/internal/va/inquiry?vaNo={vaNo}</code>
                                <div class="text-muted small mt-1">Digunakan untuk mengecek status pembayaran &amp; verifikasi transaksi terkini.</div>
                            </div>
                            <span class="badge bg-label-info rounded-pill">Inquiry Status</span>
                        </div>

                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <span class="badge bg-label-warning me-2 font-monospace">POST</span>
                                <code>/api/internal/va/update</code>
                                <div class="text-muted small mt-1">Digunakan untuk memperbarui nominal, tanggal kadaluarsa, atau penutupan VA.</div>
                            </div>
                            <span class="badge bg-label-warning rounded-pill">Update VA</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Security -->
        <div class="col-md-5 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-bolt-circle text-warning me-2"></i>Aksi Cepat &amp; Keamanan Akun
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-3">
                        <a href="{{ route('password.change') }}" class="btn btn-outline-primary d-flex align-items-center justify-content-center">
                            <i class="bx bx-lock-alt me-2"></i> Ganti Password Akun
                        </a>
                        <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" disabled>
                            <i class="bx bx-plus-circle me-2"></i> Buat Tagihan Baru <span class="badge bg-label-primary ms-auto">Fase 2</span>
                        </button>
                        <a href="{{ route('virtual-accounts.fixed.create') }}" class="btn btn-outline-primary d-flex align-items-center justify-content-center">
                            <i class="bx bx-credit-card me-2"></i> Buat Fixed VA Siswa
                        </a>
                    </div>

                    <div class="alert alert-info d-flex align-items-center mb-0" role="alert">
                        <i class="bx bx-info-circle flex-shrink-0 me-2 fs-5"></i>
                        <small>
                            Sesi login Anda dilindungi dengan token anti-CSRF dan proteksi cache browser (no-store).
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Invoices Table (Preview State) -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Transaksi &amp; Tagihan Terbaru</h5>
                        <small class="text-muted">Pratinjau daftar tagihan siswa yang terhubung dengan Mandiri Virtual Account</small>
                    </div>
                    <span class="badge bg-label-secondary">Fase Berikutnya</span>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>No. Invoice</th>
                                <th>Siswa / Pembayar</th>
                                <th>Jenis Tagihan</th>
                                <th>VA Mandiri</th>
                                <th>Total Nominal</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bx bx-receipt fs-1 d-block mb-2 text-secondary"></i>
                                    <strong>Belum ada transaksi aktif yang tersimpan.</strong>
                                    <p class="small text-muted mb-0">
                                        Data transaksi dan tagihan akan tampil di sini setelah modul skema database dan invoice diaktifkan.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content-script')
@endsection
