@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Kembali
        </a>
        <div>
            <h4 class="mb-1">Buat Invoice</h4>
            <p class="text-muted mb-0">Invoice akan dibuat untuk cabang yang dipilih dan didaftarkan sebagai Dynamic VA.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Periksa kembali data invoice:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('invoices.store') }}">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <h5 class="card-header">Detail Tagihan</h5>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="branch_id" class="form-label">Cabang <span class="text-danger">*</span></label>
                                <select class="form-select" id="branch_id" name="branch_id" required>
                                    <option value="">Pilih cabang</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                                            {{ $branch->name }} ({{ $branch->code }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Invoice dan nomor VA ini akan menjadi milik cabang yang dipilih.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="bank_id" class="form-label">Bank penyedia VA <span class="text-danger">*</span></label>
                                <select class="form-select" id="bank_id" name="bank_id" required>
                                    <option value="">Pilih bank</option>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>
                                            {{ $bank->name }} ({{ $bank->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="external_id" class="form-label">External ID / Bill Code <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="external_id" name="external_id"
                                        value="{{ old('external_id', $externalId) }}" maxlength="100" required>
                                    <button type="button" class="btn btn-outline-secondary" id="generate-external-id">
                                        <i class="bx bx-refresh me-1"></i> Buat acak
                                    </button>
                                </div>
                                <div class="form-text">ID otomatis menggunakan awalan <code>mutiar-</code> dan 16 karakter acak.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0.01"
                                    step="0.01" value="{{ old('amount') }}" required>
                            </div>

                            <div class="col-md-6">
                                <label for="expired_at" class="form-label">Tanggal kedaluwarsa <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="expired_at" name="expired_at"
                                    value="{{ old('expired_at', now()->addDays(7)->format('Y-m-d\TH:i')) }}"
                                    min="{{ now()->format('Y-m-d\TH:i') }}" required>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="3" maxlength="255"
                                    required>{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-receipt me-1"></i> Buat Invoice
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="bx bx-info-circle me-1"></i> Penentuan cabang</h6>
                    <p class="mb-0">Pilih cabang yang akan menerima pembayaran. Cabang ini disimpan pada invoice dan digunakan untuk prefix Dynamic VA serta pencatatan transaksi.</p>
                </div>
                <div class="alert alert-warning">
                    <h6 class="alert-heading"><i class="bx bx-credit-card me-1"></i> Pembuatan VA</h6>
                    <p class="mb-0">Setelah invoice disimpan, sistem langsung mengirim permintaan pembuatan Dynamic VA ke bank yang dipilih.</p>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('content-script')
    <script>
        document.getElementById('generate-external-id').addEventListener('click', function () {
            const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
            const maxValidByte = 256 - (256 % alphabet.length);
            let randomPart = '';

            while (randomPart.length < 16) {
                const bytes = crypto.getRandomValues(new Uint8Array(32));

                for (const byte of bytes) {
                    if (byte < maxValidByte) {
                        randomPart += alphabet[byte % alphabet.length];
                    }

                    if (randomPart.length === 16) {
                        break;
                    }
                }
            }

            document.getElementById('external_id').value = 'mutiar-' + randomPart;
        });
    </script>
@endsection
