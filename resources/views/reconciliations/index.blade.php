@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Reconciliation</h4>
        <p class="text-muted mb-0">Cocokkan laporan pembayaran bank dengan transaksi yang tercatat di sistem.</p>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1">Unggah Laporan Bank</h5>
                <small class="text-muted">CSV maksimal 10 MB dan 10.000 transaksi.</small>
            </div>
            <a href="{{ route('reconciliations.template') }}" class="btn btn-outline-primary">
                <i class="bx bx-download me-1"></i> Unduh Template CSV
            </a>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('reconciliations.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="bank_id" class="form-label">Bank</label>
                        <select name="bank_id" id="bank_id" class="form-select" required>
                            <option value="">Pilih bank</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>
                                    {{ $bank->name }} ({{ $bank->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="period_from" class="form-label">Periode Mulai (WIB)</label>
                        <input type="date" name="period_from" id="period_from" class="form-control"
                            value="{{ old('period_from', now('Asia/Jakarta')->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="period_to" class="form-label">Periode Akhir (WIB)</label>
                        <input type="date" name="period_to" id="period_to" class="form-control"
                            value="{{ old('period_to', now('Asia/Jakarta')->toDateString()) }}" required>
                    </div>
                    <div class="col-12">
                        <label for="statement_file" class="form-label">File laporan CSV</label>
                        <input type="file" name="statement_file" id="statement_file" class="form-control"
                            accept=".csv,text/csv" required>
                        <small class="form-text text-muted">
                            Kolom wajib: <code>reference_no,amount,paid_at</code>.
                            Waktu harus ISO-8601, misalnya <code>2026-10-14T09:00:02+07:00</code>.
                        </small>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" @disabled($banks->isEmpty())>
                            <i class="bx bx-check-double me-1"></i> Proses Rekonsiliasi
                        </button>
                        @if ($banks->isEmpty())
                            <span class="text-muted ms-2">Tidak ada bank aktif untuk dipilih.</span>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Riwayat Rekonsiliasi</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tanggal Proses</th>
                        <th>Bank</th>
                        <th>Periode</th>
                        <th>Nama File</th>
                        <th class="text-end">Cocok</th>
                        <th class="text-end">Perlu Pemeriksaan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reconciliations as $reconciliation)
                        <tr>
                            <td>{{ $reconciliation->created_at?->format('d M Y H:i') }}</td>
                            <td>{{ $reconciliation->bank?->name ?? '-' }}</td>
                            <td>{{ $reconciliation->period_from->format('d M Y') }} – {{ $reconciliation->period_to->format('d M Y') }}</td>
                            <td>{{ $reconciliation->original_filename }}</td>
                            <td class="text-end">{{ number_format($reconciliation->matched_count) }}</td>
                            <td class="text-end">
                                {{ number_format(
                                    $reconciliation->amount_mismatch_count
                                    + $reconciliation->not_found_count
                                    + $reconciliation->duplicate_count
                                    + $reconciliation->missing_from_bank_count
                                ) }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('reconciliations.show', $reconciliation) }}"
                                    class="btn btn-sm btn-outline-primary" aria-label="Detail rekonsiliasi">
                                    <i class="bx bx-show"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bx bx-check-double fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-0">Belum ada riwayat rekonsiliasi.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reconciliations->hasPages())
            <div class="card-body border-top">
                {{ $reconciliations->links() }}
            </div>
        @endif
    </div>
@endsection
