<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran {{ $invoice->bill_code }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #172033;
            background: #f3f6fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 32px 16px;
            display: grid;
            place-items: center;
        }

        main {
            width: min(100%, 560px);
            padding: 32px;
            border: 1px solid #e1e7f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 36px rgb(23 32 51 / 8%);
        }

        .school-brand {
            margin-bottom: 28px;
            text-align: center;
        }

        .school-brand img {
            display: block;
            width: 64px;
            height: 64px;
            margin: 0 auto 10px;
            object-fit: contain;
        }

        .school-brand p {
            margin: 0;
            color: #5b667a;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.5rem;
        }

        .intro,
        .label,
        .feedback {
            color: #5b667a;
        }

        .intro {
            margin: 0 0 24px;
        }

        dl {
            display: grid;
            grid-template-columns: minmax(120px, 1fr) 2fr;
            gap: 14px 20px;
            margin: 0;
        }

        dt {
            color: #5b667a;
        }

        dd {
            margin: 0;
            overflow-wrap: anywhere;
            font-weight: 600;
        }

        .total {
            color: #1456c0;
            font-size: 1.2rem;
        }

        .va-section {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e7ebf2;
        }

        .label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .va-row {
            display: flex;
            gap: 10px;
        }

        input,
        button {
            min-height: 46px;
            border-radius: 8px;
            font: inherit;
        }

        input {
            min-width: 0;
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #cbd4e1;
            color: #172033;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        button {
            padding: 10px 16px;
            border: 0;
            background: #1456c0;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        button:disabled {
            background: #8994a6;
            cursor: not-allowed;
        }

        .feedback {
            min-height: 1.5em;
            margin: 10px 0 0;
            font-size: 0.9rem;
        }

        .feedback.success {
            color: #167346;
        }

        .feedback.error {
            color: #a83232;
        }

        @media (max-width: 440px) {
            main {
                padding: 24px 20px;
            }

            dl {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            dd {
                margin-bottom: 10px;
            }

            .va-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <main>
        <div class="school-brand">
            <img src="{{ asset('assets/img/6462fbd7cab78.png') }}" alt="Logo Mutiara Harapan Islamic School">
            <p>MUTIARA HARAPAN ISLAMIC SCHOOL</p>
        </div>
        <h1>Detail Pembayaran</h1>
        <p class="intro">Gunakan informasi berikut untuk menyelesaikan pembayaran Anda.</p>

        <dl>
            <dt>Bill Code</dt>
            <dd>{{ $invoice->bill_code }}</dd>

            <dt>Deskripsi</dt>
            <dd>{{ $invoice->bill_name }}</dd>

            <dt>Total Tagihan</dt>
            <dd class="total">Rp. {{ number_format((float) $invoice->total_amount, 0, '.', ',') }}</dd>

            <dt>Status Invoice</dt>
            <dd>{{ ucfirst($invoice->displayStatus()) }}</dd>

            @if ($canPay)
                <dt>Bank</dt>
                <dd>{{ $invoice->virtualAccount?->bank?->name ?? '-' }}</dd>
            @endif

            <dt>Batas Pembayaran</dt>
            <dd>{{ $invoice->expired_at?->format('d M Y H:i') ?? '-' }}</dd>
        </dl>

        <section class="va-section" aria-labelledby="va-heading">
            <label class="label" id="va-heading" for="va-number">Nomor Virtual Account</label>
            @if ($canPay)
                <div class="va-row">
                    <input
                        id="va-number"
                        type="text"
                        value="{{ $invoice->virtualAccount->virtual_account_no }}"
                        readonly
                        aria-describedby="copy-feedback"
                    >
                    <button type="button" id="copy-va">Salin VA</button>
                </div>
                <p class="feedback" id="copy-feedback" role="status" aria-live="polite"></p>
            @else
                <p class="feedback error" role="status">
                    Pembayaran tidak tersedia untuk invoice {{ $invoice->displayStatus() }}.
                </p>
            @endif
        </section>
    </main>

    <script>
        const copyButton = document.getElementById('copy-va');
        const vaNumber = document.getElementById('va-number');
        const feedback = document.getElementById('copy-feedback');

        copyButton?.addEventListener('click', async () => {
            try {
                if (!navigator.clipboard?.writeText) {
                    throw new Error('Clipboard API unavailable');
                }

                await navigator.clipboard.writeText(vaNumber.value);
                feedback.textContent = 'Nomor VA berhasil disalin.';
                feedback.className = 'feedback success';
            } catch {
                vaNumber.focus();
                vaNumber.select();
                feedback.textContent = 'Gagal menyalin otomatis. Nomor VA telah dipilih; salin secara manual.';
                feedback.className = 'feedback error';
            }
        });
    </script>
</body>
</html>
