@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Transactions</h4>
        <p class="text-muted mb-0">Pantau pembayaran yang diterima dan diproses dari bank.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('payment-transactions.index') }}">
                <div class="input-group" style="max-width: 520px;">
                    <input type="search" class="form-control" name="search" value="{{ $search }}"
                        placeholder="Cari referensi, invoice, bill code, atau nomor VA"
                        aria-label="Cari transaksi pembayaran">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bx bx-search me-1"></i> Cari
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('payment-transactions.index') }}" class="btn btn-outline-secondary">Hapus</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Waktu Bayar</th>
                        <th>No. Referensi</th>
                        <th>Invoice / Bill Code</th>
                        <th>Virtual Account</th>
                        <th>Cabang / Bank</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->paid_at?->format('d M Y H:i:s') ?? '-' }}</td>
                            <td class="fw-semibold">{{ $transaction->payment_reference_no ?? '-' }}</td>
                            <td>
                                <div>{{ $transaction->invoice?->invoice_number ?? '-' }}</div>
                                <small class="text-muted">{{ $transaction->invoice?->bill_code ?? '-' }}</small>
                            </td>
                            <td>{{ $transaction->virtualAccount?->virtual_account_no ?? '-' }}</td>
                            <td>
                                <div>{{ $transaction->branch?->name ?? '-' }}</div>
                                <small class="text-muted">{{ $transaction->bank?->name ?? '-' }}</small>
                            </td>
                            <td class="text-end">Rp. {{ number_format((float) $transaction->paid_amount, 0, '.', ',') }}</td>
                            <td class="text-center">
                                <a href="{{ route('payment-transactions.show', $transaction) }}"
                                    class="btn btn-sm btn-outline-primary" aria-label="Detail transaksi">
                                    <i class="bx bx-show"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bx bx-transfer-alt fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-0">
                                    {{ $search !== '' ? 'Tidak ada transaksi yang cocok.' : 'Belum ada transaksi pembayaran.' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="card-body border-top">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
@endsection
