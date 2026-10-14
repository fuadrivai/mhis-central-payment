@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <a href="{{ route('reconciliations.index') }}" class="btn btn-outline-secondary mb-3">
                <i class="bx bx-arrow-back me-1"></i> Kembali
            </a>
            <h4 class="mb-1">Hasil Rekonsiliasi #{{ $reconciliation->id }}</h4>
            <p class="text-muted mb-0">
                {{ $reconciliation->bank?->name }} ·
                {{ $reconciliation->period_from->format('d M Y') }} – {{ $reconciliation->period_to->format('d M Y') }}
                (WIB) · {{ $reconciliation->original_filename }}
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Cocok', $reconciliation->matched_count, 'success'],
            ['Nominal Berbeda', $reconciliation->amount_mismatch_count, 'warning'],
            ['Tidak Ada di Sistem', $reconciliation->not_found_count, 'danger'],
            ['Duplikat di Laporan', $reconciliation->duplicate_count, 'secondary'],
            ['Tidak Ada di Laporan', $reconciliation->missing_from_bank_count, 'info'],
        ] as [$label, $count, $color])
            <div class="col-sm-6 col-xl">
                <div class="card h-100 border-start border-{{ $color }} border-3">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ $label }}</p>
                        <h4 class="mb-0">{{ number_format($count) }}</h4>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="alert alert-info" role="alert">
        Pencocokan menggunakan nomor referensi dan nominal. Transaksi internal yang tidak muncul di laporan dihitung
        berdasarkan periode WIB yang dipilih.
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Baris CSV</th>
                        <th>No. Referensi</th>
                        <th>Status</th>
                        <th class="text-end">Nominal Laporan</th>
                        <th class="text-end">Nominal Sistem</th>
                        <th>Waktu Laporan</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reconciliation->items as $item)
                        @php
                            $statusLabels = [
                                'matched' => 'Cocok',
                                'amount_mismatch' => 'Nominal berbeda',
                                'not_found' => 'Tidak ditemukan di sistem',
                                'duplicate' => 'Duplikat di laporan',
                                'missing_from_bank' => 'Tidak ada di laporan bank',
                            ];
                        @endphp
                        <tr>
                            <td>{{ $item->row_number ?? '—' }}</td>
                            <td class="fw-semibold">{{ $item->reference_no }}</td>
                            <td>
                                <span class="badge bg-label-{{ match ($item->status) {
                                    'matched' => 'success',
                                    'amount_mismatch' => 'warning',
                                    'not_found' => 'danger',
                                    'duplicate' => 'secondary',
                                    default => 'info',
                                } }}">
                                    {{ $statusLabels[$item->status] ?? $item->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                {{ $item->statement_amount === null ? '—' : 'Rp. '.number_format((float) $item->statement_amount, 0, '.', ',') }}
                            </td>
                            <td class="text-end">
                                {{ $item->internal_amount === null ? '—' : 'Rp. '.number_format((float) $item->internal_amount, 0, '.', ',') }}
                            </td>
                            <td>{{ $item->statement_paid_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') ?? '—' }}</td>
                            <td>
                                @if ($item->paymentTransaction?->invoice)
                                    <a href="{{ route('invoices.show', $item->paymentTransaction->invoice) }}">
                                        {{ $item->paymentTransaction->invoice->invoice_number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">Tidak ada detail rekonsiliasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reconciliation->items->hasPages())
            <div class="card-body border-top">
                {{ $reconciliation->items->links() }}
            </div>
        @endif
    </div>
@endsection
