@extends('template.main')

@section('content-style')
    <style>
        .copy-notification {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 1080;
            padding: 12px 18px;
            border-radius: 8px;
            background: #172033;
            color: #fff;
            box-shadow: 0 8px 24px rgb(23 32 51 / 20%);
            opacity: 0;
            pointer-events: none;
            transform: translateY(8px);
            transition: opacity 160ms ease, transform 160ms ease;
        }

        .copy-notification.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media print {
            #layout-menu,
            .layout-navbar,
            .content-footer,
            .content-backdrop,
            .layout-overlay,
            .invoice-actions,
            .invoice-back-link,
            .copy-notification {
                display: none !important;
            }

            .layout-container,
            .layout-page,
            .content-wrapper,
            .container-xxl {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .card {
                break-inside: avoid;
                box-shadow: none !important;
            }
        }
    </style>
@endsection

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 invoice-back-link">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i> Kembali
            </a>
            <div>
                <h4 class="mb-1">{{ $invoice->invoice_number }}</h4>
                <p class="text-muted mb-0">{{ $invoice->bill_name }}</p>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 invoice-actions">
            @if ($invoice->status === 'pending' && auth()->user()->hasPermission('invoices.delete'))
                @if ($invoice->expired_at?->isFuture()
                    && $invoice->va_type === 'dynamic'
                    && $invoice->virtualAccount?->status === 'active')
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                        data-bs-target="#void-invoice-modal">
                        Void
                    </button>
                @else
                    <button type="button" class="btn btn-outline-danger" disabled>Void</button>
                @endif
            @endif
            @if ($paymentUrl)
                <button type="button" class="btn btn-outline-primary" id="copy-payment-link"
                    data-payment-url="{{ $paymentUrl }}">
                    Copy Payment Link
                </button>
            @else
                <button type="button" class="btn btn-outline-primary" disabled
                    title="Payment link tersedia untuk invoice pending dengan VA aktif.">
                    Copy Payment Link
                </button>
            @endif
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bx bx-file me-1"></i> PDF
            </button>
        </div>
    </div>
    <div id="copy-notification" class="copy-notification" role="status" aria-live="polite"></div>

    @if (auth()->user()->hasPermission('invoices.delete')
        && $invoice->status === 'pending'
        && $invoice->expired_at?->isFuture()
        && $invoice->va_type === 'dynamic'
        && $invoice->virtualAccount?->status === 'active')
        <div class="modal fade" id="void-invoice-modal" tabindex="-1" aria-labelledby="void-invoice-modal-title"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="void-invoice-modal-title">Konfirmasi Void Invoice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Virtual Account akan dinonaktifkan di bank. Invoice akan kedaluwarsa jika permintaan berhasil.
                        Apakah Anda yakin ingin melanjutkan?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <form method="POST" action="{{ route('invoices.void', $invoice) }}">
                            @csrf
                            <button type="submit" class="btn btn-danger">Ya, Void Invoice</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <h5 class="card-header">Detail Invoice</h5>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">@include('invoices._status')</dd>

                        <dt class="col-sm-4">Bill Code</dt>
                        <dd class="col-sm-8">{{ $invoice->bill_code }}</dd>

                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8">{{ $invoice->bill_name }}</dd>

                        <dt class="col-sm-4">Cabang</dt>
                        <dd class="col-sm-8">{{ $invoice->branch?->name ?? '-' }}</dd>

                        <dt class="col-sm-4">Payer</dt>
                        <dd class="col-sm-8">
                            {{ $invoice->customer_name }}
                            @if ($invoice->student)
                                <div class="small text-muted">NIS {{ $invoice->student->nis }}</div>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Payer Email</dt>
                        <dd class="col-sm-8">{{ $invoice->email ?? '-' }}</dd>

                        <dt class="col-sm-4">Telepon</dt>
                        <dd class="col-sm-8">{{ $invoice->customer_phone ?? '-' }}</dd>

                        <dt class="col-sm-4">Jenis Pembayaran</dt>
                        <dd class="col-sm-8">{{ $invoice->paymentType?->name ?? '-' }}</dd>

                        <dt class="col-sm-4">Source</dt>
                        <dd class="col-sm-8">{{ $invoice->source }}</dd>

                        <dt class="col-sm-4">Created Date</dt>
                        <dd class="col-sm-8">{{ $invoice->created_at?->format('d M Y H:i') }}</dd>

                        <dt class="col-sm-4">Kedaluwarsa</dt>
                        <dd class="col-sm-8">{{ $invoice->expired_at?->format('d M Y H:i') ?? '-' }}</dd>

                        <dt class="col-sm-4">Tanggal Bayar</dt>
                        <dd class="col-sm-8">{{ $invoice->paid_at?->format('d M Y H:i') ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <h5 class="card-header">Nominal</h5>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">Amount</dt>
                        <dd class="col-6 text-end">Rp. {{ number_format((float) $invoice->amount, 0, '.', ',') }}</dd>
                        <dt class="col-6">Fee</dt>
                        <dd class="col-6 text-end">Rp. {{ number_format((float) $invoice->fee_amount, 0, '.', ',') }}</dd>
                        <dt class="col-6">Total Amount</dt>
                        <dd class="col-6 text-end fw-bold">Rp. {{ number_format((float) $invoice->total_amount, 0, '.', ',') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <h5 class="card-header">Virtual Account</h5>
                <div class="card-body">
                    @if ($invoice->virtualAccount)
                        <code class="fs-5 user-select-all">{{ $invoice->virtualAccount->virtual_account_no }}</code>
                        <div class="text-muted mt-1">{{ $invoice->virtualAccount->bank?->name ?? '-' }}</div>
                        <div class="mt-2">
                            <span class="badge bg-label-{{ $invoice->virtualAccount->status === 'active' ? 'success' : 'warning' }}">
                                {{ $invoice->virtualAccount->status }}
                            </span>
                        </div>
                    @else
                        <span class="text-muted">VA belum dibuat.</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <h5 class="card-header">Detail Transaksi</h5>
                <div class="card-body">
                    @forelse ($invoice->paymentTransactions as $transaction)
                        <div class="card border mb-3">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h6 class="mb-0">
                                    Transaksi
                                    <a href="{{ route('transactions.show', $transaction) }}">
                                        #{{ $transaction->id }}
                                    </a>
                                </h6>
                                <span class="badge bg-label-success">Berhasil</span>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">Waktu Bayar</dt>
                                    <dd class="col-sm-8">{{ $transaction->paid_at?->format('d M Y H:i:s') ?? '-' }}</dd>

                                    <dt class="col-sm-4">Bank</dt>
                                    <dd class="col-sm-8">{{ $transaction->bank?->name ?? '-' }}</dd>

                                    <dt class="col-sm-4">Cabang</dt>
                                    <dd class="col-sm-8">{{ $transaction->branch?->name ?? '-' }}</dd>

                                    <dt class="col-sm-4">Virtual Account</dt>
                                    <dd class="col-sm-8">{{ $transaction->virtualAccount?->virtual_account_no ?? '-' }}</dd>

                                    <dt class="col-sm-4">Channel</dt>
                                    <dd class="col-sm-8">{{ $transaction->channel ?? '-' }}</dd>

                                    <dt class="col-sm-4">No. Referensi</dt>
                                    <dd class="col-sm-8">{{ $transaction->payment_reference_no ?? '-' }}</dd>

                                    <dt class="col-sm-4">Payment Request ID</dt>
                                    <dd class="col-sm-8">{{ $transaction->payment_request_id ?? '-' }}</dd>

                                    <dt class="col-sm-4">Dibayar</dt>
                                    <dd class="col-sm-8">Rp. {{ number_format((float) $transaction->paid_amount, 0, '.', ',') }}</dd>
                                </dl>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted mb-0 py-3">Belum ada pembayaran.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content-script')
    <script>
        const copyNotification = document.getElementById('copy-notification');
        let notificationTimeout;

        function showCopyNotification(message) {
            copyNotification.textContent = message;
            copyNotification.classList.add('is-visible');
            window.clearTimeout(notificationTimeout);
            notificationTimeout = window.setTimeout(() => {
                copyNotification.classList.remove('is-visible');
            }, 2500);
        }

        async function copyText(text) {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
                return;
            }

            const temporaryInput = document.createElement('textarea');
            temporaryInput.value = text;
            temporaryInput.setAttribute('readonly', '');
            temporaryInput.style.position = 'fixed';
            temporaryInput.style.opacity = '0';
            document.body.appendChild(temporaryInput);
            temporaryInput.select();
            const copied = document.execCommand('copy');
            temporaryInput.remove();

            if (!copied) {
                throw new Error('Unable to copy address.');
            }
        }

        document.getElementById('copy-payment-link')?.addEventListener('click', async function () {
            const paymentUrl = this.dataset.paymentUrl;

            try {
                await copyText(paymentUrl);
                showCopyNotification('Address has been copied');
            } catch {
                showCopyNotification('Unable to copy address');
            }
        });
    </script>
@endsection
