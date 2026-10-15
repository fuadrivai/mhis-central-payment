@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <a href="{{ route('invoices.show', $transaction->invoice) }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Kembali ke Invoice
        </a>
        <div>
            <h4 class="mb-1">Detail Transaksi #{{ $transaction->id }}</h4>
            <p class="text-muted mb-0">{{ $transaction->invoice?->invoice_number ?? 'Invoice tidak tersedia' }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <h5 class="card-header">Informasi Transaksi</h5>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">ID Transaksi</dt>
                        <dd class="col-sm-7">{{ $transaction->id }}</dd>

                        <dt class="col-sm-5">ID Cabang</dt>
                        <dd class="col-sm-7">{{ $transaction->branch_id }}</dd>

                        <dt class="col-sm-5">ID Bank</dt>
                        <dd class="col-sm-7">{{ $transaction->bank_id }}</dd>

                        <dt class="col-sm-5">ID Invoice</dt>
                        <dd class="col-sm-7">{{ $transaction->invoice_id }}</dd>

                        <dt class="col-sm-5">ID Virtual Account</dt>
                        <dd class="col-sm-7">{{ $transaction->virtual_account_id }}</dd>

                        <dt class="col-sm-5">Waktu Bayar</dt>
                        <dd class="col-sm-7">{{ $transaction->paid_at?->format('d M Y H:i:s') ?? '-' }}</dd>

                        <dt class="col-sm-5">Nominal Dibayar</dt>
                        <dd class="col-sm-7">Rp. {{ number_format((float) $transaction->paid_amount, 0, '.', ',') }}</dd>

                        <dt class="col-sm-5">Bank</dt>
                        <dd class="col-sm-7">{{ $transaction->bank?->name ?? '-' }}</dd>

                        <dt class="col-sm-5">Channel</dt>
                        <dd class="col-sm-7">{{ $transaction->channel ?? '-' }}</dd>

                        <dt class="col-sm-5">No. Referensi</dt>
                        <dd class="col-sm-7">{{ $transaction->payment_reference_no ?? '-' }}</dd>

                        <dt class="col-sm-5">Payment Request ID</dt>
                        <dd class="col-sm-7">{{ $transaction->payment_request_id ?? '-' }}</dd>

                        <dt class="col-sm-5">Dibuat</dt>
                        <dd class="col-sm-7">{{ $transaction->created_at?->format('d M Y H:i:s') ?? '-' }}</dd>

                        <dt class="col-sm-5">Diperbarui</dt>
                        <dd class="col-sm-7">{{ $transaction->updated_at?->format('d M Y H:i:s') ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <h5 class="card-header">Invoice, Cabang, dan Virtual Account</h5>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Invoice</dt>
                        <dd class="col-sm-7">
                            @if ($transaction->invoice)
                                <a href="{{ route('invoices.show', $transaction->invoice) }}">
                                    {{ $transaction->invoice->invoice_number }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>

                        <dt class="col-sm-5">Bill Code</dt>
                        <dd class="col-sm-7">{{ $transaction->invoice?->bill_code ?? '-' }}</dd>

                        <dt class="col-sm-5">Deskripsi Invoice</dt>
                        <dd class="col-sm-7">{{ $transaction->invoice?->bill_name ?? '-' }}</dd>

                        <dt class="col-sm-5">Status Invoice</dt>
                        <dd class="col-sm-7">{{ $transaction->invoice?->displayStatus() ?? '-' }}</dd>

                        <dt class="col-sm-5">Cabang</dt>
                        <dd class="col-sm-7">{{ $transaction->branch?->name ?? '-' }}</dd>

                        <dt class="col-sm-5">Nomor Virtual Account</dt>
                        <dd class="col-sm-7">{{ $transaction->virtualAccount?->virtual_account_no ?? '-' }}</dd>

                        <dt class="col-sm-5">Nama Virtual Account</dt>
                        <dd class="col-sm-7">{{ $transaction->virtualAccount?->virtual_account_name ?? '-' }}</dd>

                        <dt class="col-sm-5">Customer Number</dt>
                        <dd class="col-sm-7">{{ $transaction->virtualAccount?->customer_no ?? '-' }}</dd>

                        <dt class="col-sm-5">Jenis Virtual Account</dt>
                        <dd class="col-sm-7">{{ $transaction->virtualAccount?->va_type ?? '-' }}</dd>

                        <dt class="col-sm-5">Status Virtual Account</dt>
                        <dd class="col-sm-7">{{ $transaction->virtualAccount?->status ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <h5 class="card-header">Callback ke Cabang</h5>
                <div class="card-body">
                    @if ($transaction->webhookDelivery)
                        <dl class="row">
                            <dt class="col-sm-3">Status</dt>
                            <dd class="col-sm-9">{{ ucfirst($transaction->webhookDelivery->status) }}</dd>

                            <dt class="col-sm-3">URL Tujuan</dt>
                            <dd class="col-sm-9">{{ $transaction->webhookDelivery->webhook_url ?? 'Belum dikonfigurasi' }}</dd>

                            <dt class="col-sm-3">Jumlah Percobaan</dt>
                            <dd class="col-sm-9">{{ $transaction->webhookDelivery->attempts }}</dd>

                            <dt class="col-sm-3">HTTP Response</dt>
                            <dd class="col-sm-9">{{ $transaction->webhookDelivery->response_status ?? '-' }}</dd>

                            <dt class="col-sm-3">Percobaan Terakhir</dt>
                            <dd class="col-sm-9">{{ $transaction->webhookDelivery->last_attempt_at?->format('d M Y H:i:s') ?? '-' }}</dd>

                            <dt class="col-sm-3">Berhasil Dikirim</dt>
                            <dd class="col-sm-9">{{ $transaction->webhookDelivery->delivered_at?->format('d M Y H:i:s') ?? '-' }}</dd>

                            @if ($transaction->webhookDelivery->last_error)
                                <dt class="col-sm-3">Keterangan Error</dt>
                                <dd class="col-sm-9">{{ $transaction->webhookDelivery->last_error }}</dd>
                            @endif
                        </dl>
                    @else
                        <p class="text-muted">Callback cabang belum pernah dijadwalkan untuk transaksi ini.</p>
                    @endif

                    @if (auth()->user()->hasPermission('transactions.update'))
                        <p class="text-muted">
                            Callback dikirim tanpa autentikasi HTTP tambahan. Pastikan endpoint cabang menggunakan HTTPS
                            dan mendeduplikasi event berdasarkan ID callback.
                        </p>

                        <form method="POST" action="{{ route('transactions.callback.retry', $transaction) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bx bx-refresh me-1"></i> Kirim Ulang Callback
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @if ($transaction->webhookDelivery)
            <div class="col-12">
                <div class="card">
                    <h5 class="card-header">Payload Callback Cabang</h5>
                    <div class="card-body">
                        <pre class="mb-0 text-wrap"><code>{{ json_encode($transaction->webhookDelivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
                        @if ($transaction->webhookDelivery->response_body)
                            <h6 class="mt-4">Response Terakhir dari Cabang</h6>
                            <pre class="mb-0 text-wrap"><code>{{ $transaction->webhookDelivery->response_body }}</code></pre>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card">
                <h5 class="card-header">Payload Callback</h5>
                <div class="card-body">
                    @if ($transaction->raw_payload)
                        <pre class="mb-0 text-wrap"><code>{{ json_encode($transaction->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
                    @else
                        <p class="text-muted mb-0">Payload callback tidak tersedia.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
