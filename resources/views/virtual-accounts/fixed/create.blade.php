@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Buat Fixed Virtual Account</h4>
            <p class="text-muted mb-0">Daftarkan siswa dan buat VA tetap melalui backend Mandiri H2H.</p>
        </div>
        <a href="{{ route('virtual-accounts.fixed.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Kembali
        </a>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-10">
            <form action="{{ route('virtual-accounts.fixed.store') }}" method="POST">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Data Cabang dan VA</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="bank_id" class="form-label">Bank</label>
                            <select id="bank_id" name="bank_id"
                                class="form-select @error('bank_id') is-invalid @enderror" required>
                                @if ($banks->count() !== 1)
                                    <option value="">Pilih bank</option>
                                @endif
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->id }}" @selected((string) old('bank_id', $banks->count() === 1 ? $bank->id : null) === (string) $bank->id)>
                                        {{ $bank->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('bank_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="branch_id" class="form-label">Cabang</label>
                            <select id="branch_id" name="branch_id"
                                class="form-select @error('branch_id') is-invalid @enderror" required>
                                <option value="">Pilih cabang</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" data-fixed-prefix="{{ $branch->va_fixed_prefix }}"
                                        @selected((string) old('branch_id') === (string) $branch->id)>
                                        {{ $branch->name }} ({{ $branch->code }}) — prefix {{ $branch->va_fixed_prefix }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="va_number_mode" class="form-label">Cara Menentukan Nomor VA</label>
                            <select id="va_number_mode" name="va_number_mode"
                                class="form-select @error('va_number_mode') is-invalid @enderror" required>
                                <option value="automatic" @selected(old('va_number_mode', 'automatic') === 'automatic')>
                                    Otomatis — prefix cabang + NIS
                                </option>
                                <option value="manual" @selected(old('va_number_mode') === 'manual')>
                                    Input nomor VA manual
                                </option>
                            </select>
                            @error('va_number_mode')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nis" class="form-label">NIS</label>
                                <input id="nis" name="nis" type="text" inputmode="numeric"
                                    class="form-control @error('nis') is-invalid @enderror"
                                    value="{{ old('nis') }}" required minlength="8" maxlength="8" pattern="[0-9]{8}" autocomplete="off">
                                <div class="form-text">NIS wajib tepat 8 digit. Untuk mode otomatis, VA terdiri dari prefix cabang 8 digit + NIS 8 digit.</div>
                                @error('nis')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3" id="manual-va-field" hidden>
                                <label for="manual_va_number" class="form-label">Nomor Fixed VA Manual</label>
                                <input id="manual_va_number" name="manual_va_number" type="text" inputmode="numeric"
                                    class="form-control font-monospace @error('manual_va_number') is-invalid @enderror"
                                    value="{{ old('manual_va_number') }}" minlength="16" maxlength="16" pattern="[0-9]{16}" autocomplete="off">
                                <div class="form-text" id="manual-va-prefix-hint">Nomor harus tepat 16 digit dan diawali prefix cabang.</div>
                                @error('manual_va_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="alert alert-info" role="status">
                            Nomor VA otomatis (16 digit): <code id="va-number-preview">Pilih cabang dan isi NIS</code>
                        </div>

                        <hr class="my-4">
                        <h5 class="mb-3">Data Siswa</h5>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Siswa / Nama VA</label>
                            <input id="name" name="name" type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" required maxlength="255" autocomplete="name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nisn" class="form-label">NISN <span class="text-muted">(opsional)</span></label>
                                <input id="nisn" name="nisn" type="text"
                                    class="form-control @error('nisn') is-invalid @enderror"
                                    value="{{ old('nisn') }}" maxlength="50">
                                @error('nisn')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="grade_class" class="form-label">Kelas <span class="text-muted">(opsional)</span></label>
                                <input id="grade_class" name="grade_class" type="text"
                                    class="form-control @error('grade_class') is-invalid @enderror"
                                    value="{{ old('grade_class') }}" maxlength="50">
                                @error('grade_class')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="parent_name" class="form-label">Nama Orang Tua / Wali <span class="text-muted">(opsional)</span></label>
                                <input id="parent_name" name="parent_name" type="text"
                                    class="form-control @error('parent_name') is-invalid @enderror"
                                    value="{{ old('parent_name') }}" maxlength="255">
                                @error('parent_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="parent_phone" class="form-label">No. Telepon Wali <span class="text-muted">(opsional)</span></label>
                                <input id="parent_phone" name="parent_phone" type="tel"
                                    class="form-control @error('parent_phone') is-invalid @enderror"
                                    value="{{ old('parent_phone') }}" maxlength="30" autocomplete="tel">
                                @error('parent_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="parent_email" class="form-label">Email Orang Tua / Wali</label>
                                <input id="parent_email" name="parent_email" type="email"
                                    class="form-control @error('parent_email') is-invalid @enderror"
                                    value="{{ old('parent_email') }}" required maxlength="255" autocomplete="email">
                                @error('parent_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="additional_email" class="form-label">Email Tambahan <span class="text-muted">(opsional)</span></label>
                                <input id="additional_email" name="additional_email" type="email"
                                    class="form-control @error('additional_email') is-invalid @enderror"
                                    value="{{ old('additional_email') }}" maxlength="255">
                                @error('additional_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="expired_at" class="form-label">Tanggal Kedaluwarsa VA</label>
                            <input id="expired_at" name="expired_at" type="datetime-local"
                                class="form-control @error('expired_at') is-invalid @enderror"
                                value="{{ old('expired_at') }}" required>
                            <div class="form-text">Tentukan batas masa aktif VA sesuai aturan sekolah/backend H2H.</div>
                            @error('expired_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-warning mb-0" role="alert">
                            <i class="bx bx-info-circle me-1"></i>
                            Fixed VA akan dibuat di H2H dengan nominal awal Rp 0,00. Tagihan SPP/Annual Fee dapat disinkronkan kemudian.
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('virtual-accounts.fixed.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-credit-card me-1"></i> Buat Fixed VA
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('content-script')
    <script>
        const branchSelect = document.getElementById('branch_id');
        const modeSelect = document.getElementById('va_number_mode');
        const nisInput = document.getElementById('nis');
        const manualField = document.getElementById('manual-va-field');
        const manualInput = document.getElementById('manual_va_number');
        const preview = document.getElementById('va-number-preview');
        const prefixHint = document.getElementById('manual-va-prefix-hint');

        function updateVaPreview() {
            const selectedBranch = branchSelect.options[branchSelect.selectedIndex];
            const prefix = selectedBranch?.dataset.fixedPrefix ?? '';
            const isManual = modeSelect.value === 'manual';

            manualField.hidden = !isManual;
            manualInput.required = isManual;

            if (isManual) {
                preview.textContent = manualInput.value || 'Masukkan nomor Fixed VA';
                prefixHint.textContent = prefix
                    ? `Nomor harus tepat 16 digit dan diawali prefix cabang ${prefix}.`
                    : 'Pilih cabang terlebih dahulu.';
                return;
            }

            preview.textContent = prefix && nisInput.value
                ? `${prefix}${nisInput.value}`
                : 'Pilih cabang dan isi NIS';
        }

        branchSelect.addEventListener('change', updateVaPreview);
        modeSelect.addEventListener('change', updateVaPreview);
        nisInput.addEventListener('input', updateVaPreview);
        manualInput.addEventListener('input', updateVaPreview);
        updateVaPreview();
    </script>
@endsection
