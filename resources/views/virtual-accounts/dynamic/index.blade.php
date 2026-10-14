@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Dynamic Virtual Accounts</h4>
        <p class="text-muted mb-0">VA yang dibuat khusus untuk membayar invoice.</p>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nomor VA</th>
                        <th>Invoice / Bill Code</th>
                        <th>Nama Tagihan</th>
                        <th>Pelanggan</th>
                        <th>Bank</th>
                        <th>Cabang</th>
                        <th class="text-end">Nominal</th>
                        <th>Status VA / Invoice</th>
                        <th>Kedaluwarsa</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($virtualAccounts as $virtualAccount)
                        <tr>
                            <td><code class="user-select-all">{{ $virtualAccount->virtual_account_no }}</code></td>
                            <td>
                                @if ($virtualAccount->invoice)
                                    <a href="{{ route('invoices.show', $virtualAccount->invoice) }}" class="fw-semibold">
                                        {{ $virtualAccount->invoice->invoice_number }}
                                    </a>
                                    <div class="small text-muted">{{ $virtualAccount->invoice->bill_code }}</div>
                                @else
                                    <span class="text-muted">Invoice tidak tersedia</span>
                                @endif
                            </td>
                            <td>{{ $virtualAccount->invoice?->bill_name ?? '-' }}</td>
                            <td>{{ $virtualAccount->virtual_account_name }}</td>
                            <td>{{ $virtualAccount->bank?->name ?? '-' }}</td>
                            <td>{{ $virtualAccount->branch?->name ?? '-' }}</td>
                            <td class="text-end">
                                Rp {{ number_format((float) ($virtualAccount->invoice?->total_amount ?? $virtualAccount->current_amount), 2, ',', '.') }}
                            </td>
                            <td>
                                @php
                                    $vaStatus = match ($virtualAccount->status) {
                                        'active' => ['Aktif', 'success'],
                                        'verification_required' => ['Perlu Verifikasi', 'warning'],
                                        'pending_h2h' => ['Menunggu H2H', 'info'],
                                        'failed' => ['Ditolak H2H', 'danger'],
                                        'inactive' => ['Nonaktif', 'secondary'],
                                        default => [$virtualAccount->status, 'secondary'],
                                    };
                                    $invoiceStatus = match ($virtualAccount->invoice?->displayStatus()) {
                                        'paid' => ['Lunas', 'success'],
                                        'pending' => ['Menunggu Pembayaran', 'warning'],
                                        'expired' => ['Kedaluwarsa', 'secondary'],
                                        'failed' => ['Gagal', 'danger'],
                                        null => ['-', 'secondary'],
                                        default => [$virtualAccount->invoice->displayStatus(), 'secondary'],
                                    };
                                @endphp
                                <span class="badge bg-label-{{ $vaStatus[1] }}">{{ $vaStatus[0] }}</span>
                                <div class="small mt-1">
                                    Invoice: <span class="text-{{ $invoiceStatus[1] }}">{{ $invoiceStatus[0] }}</span>
                                </div>
                            </td>
                            <td>{{ $virtualAccount->expired_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="text-center">
                                @if ($virtualAccount->invoice)
                                    <a href="{{ route('invoices.show', $virtualAccount->invoice) }}"
                                        class="btn btn-sm btn-outline-primary" aria-label="Detail invoice">
                                        <i class="bx bx-show"></i>
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="bx bx-credit-card fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-0">Belum ada Dynamic VA.</p>
                                <small class="text-muted">Dynamic VA akan muncul di sini saat invoice dibuat.</small>
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
@endsection
