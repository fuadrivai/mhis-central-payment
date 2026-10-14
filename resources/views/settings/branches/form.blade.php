@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">{{ $branch->exists ? 'Edit Cabang' : 'Tambah Cabang' }}</h4>
            <p class="text-muted mb-0">Atur identitas cabang dan prefix VA yang digunakan oleh sistem pembayaran.</p>
        </div>
        <a href="{{ route('settings.branches.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Kembali
        </a>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-10">
            <form
                action="{{ $branch->exists ? route('settings.branches.update', $branch) : route('settings.branches.store') }}"
                method="POST">
                @csrf
                @if ($branch->exists)
                    @method('PUT')
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Informasi Cabang</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Cabang</label>
                            <input id="name" name="name" type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $branch->name) }}" required maxlength="255" autocomplete="organization">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="code" class="form-label">Kode Cabang</label>
                            <input id="code" name="code" type="text"
                                class="form-control text-uppercase @error('code') is-invalid @enderror"
                                value="{{ old('code', $branch->code) }}" required maxlength="20" pattern="[A-Za-z0-9_-]+"
                                @readonly($hasFinancialRecords)>
                            <div class="form-text">Contoh: BTR, BGK, SMG. Gunakan kode unik yang stabil.</div>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="va_fixed_prefix" class="form-label">Prefix Fixed VA</label>
                                <input id="va_fixed_prefix" name="va_fixed_prefix" type="text" inputmode="numeric"
                                    class="form-control font-monospace @error('va_fixed_prefix') is-invalid @enderror"
                                    value="{{ old('va_fixed_prefix', $branch->va_fixed_prefix) }}" required minlength="8"
                                    maxlength="8" pattern="[0-9]{8}" @readonly($hasVirtualAccounts)>
                                <div class="form-text">Prefix Fixed VA harus tepat 8 digit. Contoh Bintaro: 85765010</div>
                                @error('va_fixed_prefix')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="va_dynamic_prefix" class="form-label">Prefix Dynamic VA</label>
                                <input id="va_dynamic_prefix" name="va_dynamic_prefix" type="text" inputmode="numeric"
                                    class="form-control font-monospace @error('va_dynamic_prefix') is-invalid @enderror"
                                    value="{{ old('va_dynamic_prefix', $branch->va_dynamic_prefix) }}" required maxlength="20"
                                    pattern="[0-9]+" @readonly($hasVirtualAccounts)>
                                <div class="form-text">Contoh Bintaro: 85765991</div>
                                @error('va_dynamic_prefix')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="webhook_url" class="form-label">Webhook URL</label>
                            <input id="webhook_url" name="webhook_url" type="url"
                                class="form-control @error('webhook_url') is-invalid @enderror"
                                value="{{ old('webhook_url', $branch->webhook_url) }}" maxlength="2048"
                                placeholder="https://sistem-cabang.example.com/webhooks/payment">
                            <div class="form-text">
                                URL tujuan notifikasi pembayaran untuk sistem cabang ini. Gunakan alamat HTTPS yang dapat diakses server.
                            </div>
                            @error('webhook_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if ($hasVirtualAccounts)
                            <div class="alert alert-warning" role="alert">
                                Prefix VA dikunci karena cabang ini sudah memiliki Virtual Account.
                            </div>
                        @endif

                        @if ($hasInvoices)
                            <div class="alert alert-warning" role="alert">
                                Kode cabang dikunci karena cabang ini sudah memiliki invoice.
                            </div>
                        @endif

                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_active" value="0">
                            <input id="is_active" name="is_active" type="checkbox" value="1"
                                class="form-check-input" @checked(old('is_active', $branch->exists ? $branch->is_active : true))>
                            <label for="is_active" class="form-check-label">Cabang aktif</label>
                            <div class="form-text">Cabang nonaktif tidak boleh digunakan untuk permintaan pembayaran baru.</div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('settings.branches.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-save me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </form>

            @if ($branch->exists)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">API Key Cabang</h5>
                    </div>
                    <div class="card-body">
                        @if ($branch->api_key)
                            <label for="branch-api-key" class="form-label">Key aktif</label>
                            <div class="input-group">
                                <input id="branch-api-key" type="text" class="form-control font-monospace"
                                    value="{{ $branch->api_key }}" readonly autocomplete="off" spellcheck="false">
                                <button type="button" class="btn btn-outline-primary" data-copy-target="branch-api-key">
                                    <i class="bx bx-copy me-1"></i> Salin
                                </button>
                            </div>
                            <div class="form-text">
                                Key ini bersifat rahasia. Hanya tampil untuk Super Admin; jangan dibagikan atau disimpan di source code.
                            </div>
                            <div class="small text-success mt-2 d-none" role="status" data-copy-feedback>
                                API key berhasil disalin.
                            </div>
                        @else
                            <p class="text-muted mb-0">
                                Belum ada API key untuk cabang ini. Gunakan tombol <strong>Rotasi Key</strong> pada daftar cabang
                                untuk membuat key.
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            @if (! $branch->exists)
                <div class="alert alert-info mt-4" role="alert">
                    API key cabang akan dibuat otomatis dan ditampilkan satu kali setelah data berhasil disimpan.
                </div>
            @endif
        </div>
    </div>
@endsection

@section('content-script')
    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const input = document.getElementById(button.dataset.copyTarget);
                const feedback = button.closest('.card-body').querySelector('[data-copy-feedback]');

                try {
                    await navigator.clipboard.writeText(input.value);
                    feedback.textContent = 'API key berhasil disalin.';
                    feedback.classList.remove('d-none', 'text-warning');
                    feedback.classList.add('text-success');
                } catch {
                    input.focus();
                    input.select();
                    feedback.textContent = 'Penyalinan otomatis gagal. Key sudah dipilih; tekan Ctrl+C untuk menyalin.';
                    feedback.classList.remove('d-none', 'text-success');
                    feedback.classList.add('text-warning');
                }
            });
        });
    </script>
@endsection
