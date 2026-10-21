@extends('template.main')

@section('content-style')
    <style>
        .bulk-upload-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(33, 43, 54, 0.58);
            backdrop-filter: blur(5px);
        }

        .bulk-upload-dialog {
            width: min(100%, 440px);
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, 0.24);
        }

        .bulk-upload-icon {
            display: inline-flex;
            width: 3.5rem;
            height: 3.5rem;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            background: rgba(105, 108, 255, 0.12);
            color: var(--bs-primary);
            font-size: 1.75rem;
        }

        @media (prefers-reduced-motion: reduce) {
            .bulk-upload-overlay .spinner-border {
                animation-duration: 1.5s;
            }
        }
    </style>
@endsection

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Bulk Import Fixed VA</h4>
            <p class="text-muted mb-0">Import data siswa dan daftarkan Fixed VA melalui backend bank.</p>
        </div>
        <a href="{{ route('virtual-accounts.fixed.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="fw-semibold mb-2">File belum diproses. Perbaiki kesalahan berikut:</p>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-8 col-lg-10">
            <form id="bulk-fixed-va-form" action="{{ route('virtual-accounts.fixed.bulk.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Cabang, Bank, dan File CSV</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="branch_id" class="form-label">Pilih Cabang</label>
                            <select id="branch_id" name="branch_id"
                                class="form-select @error('branch_id') is-invalid @enderror" required>
                                <option value="">Pilih cabang terlebih dahulu</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>
                                        {{ $branch->name }} ({{ $branch->code }}) — prefix {{ $branch->va_fixed_prefix }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="bank_id" class="form-label">Pilih Bank</label>
                            <select id="bank_id" name="bank_id"
                                class="form-select @error('bank_id') is-invalid @enderror" required>
                                <option value="">Pilih bank</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>
                                        {{ $bank->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('bank_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="student_file" class="form-label">File data siswa (CSV)</label>
                            <input id="student_file" name="student_file" type="file" accept=".csv,text/csv"
                                class="form-control @error('student_file') is-invalid @enderror" required>
                            @error('student_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Maksimal 5 MB dan 500 baris data. File tidak diproses jika ada baris yang tidak valid.</div>
                        </div>

                        <div class="alert alert-info mb-0" role="note">
                            <div class="fw-semibold mb-1">Format CSV wajib mengikuti urutan kolom berikut:</div>
                            <code>nis,nama_siswa,grade,email,tgl_expired_va</code>
                            <div class="small mt-2">
                                Tanggal dapat ditulis sebagai YYYY-MM-DD (berlaku sampai 23:59) atau YYYY-MM-DD HH:MM.
                                Jika memuat dua email, gunakan satu kolom yang dikutip, misalnya
                                <code>"wali@example.com,keluarga@example.com"</code>; email kedua disimpan sebagai email tambahan.
                            </div>
                            <a href="{{ route('virtual-accounts.fixed.bulk.template') }}" class="btn btn-sm btn-outline-primary mt-3">
                                <i class="bx bx-download me-1"></i> Unduh Template CSV
                            </a>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0" role="alert">
                            Nomor VA dibuat otomatis dari prefix cabang + NIS. Setiap baris akan didaftarkan ke bank yang dipilih;
                            jika respons H2H tidak pasti, periksa statusnya sebelum mencoba ulang.
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('virtual-accounts.fixed.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button id="bulk-fixed-va-submit" type="submit" class="btn btn-primary">
                            <i class="bx bx-upload me-1"></i> Import dan Buat Fixed VA
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="bulk-upload-overlay" class="bulk-upload-overlay d-none" aria-hidden="true">
        <div class="card bulk-upload-dialog" role="status" aria-live="polite" aria-atomic="true">
            <div class="card-body p-4 p-sm-5 text-center">
                <div class="bulk-upload-icon mb-3">
                    <i class="bx bx-cloud-upload" aria-hidden="true"></i>
                </div>
                <h4 class="mb-2">Sedang memproses data</h4>
                <p class="text-muted mb-4">
                    File sedang dikirim dan Fixed VA akan dibuat satu per satu. Mohon tunggu dan jangan tutup halaman ini.
                </p>
                <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                    <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
                    <span class="fw-semibold">Menghubungi backend bank...</span>
                </div>
                <div class="progress mb-3" style="height: 6px;" aria-hidden="true">
                    <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                </div>
                <div id="bulk-upload-details" class="small text-muted text-break"></div>
            </div>
        </div>
    </div>
@endsection

@section('content-script')
    <script>
        const bulkForm = document.getElementById('bulk-fixed-va-form');
        const bulkSubmitButton = document.getElementById('bulk-fixed-va-submit');
        const bulkUploadOverlay = document.getElementById('bulk-upload-overlay');
        const bulkUploadDetails = document.getElementById('bulk-upload-details');
        let bulkUploadStarted = false;

        bulkForm.addEventListener('submit', function () {
            if (bulkUploadStarted) {
                return;
            }

            bulkUploadStarted = true;
            bulkSubmitButton.disabled = true;
            bulkSubmitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Sedang memproses...';
            bulkForm.setAttribute('aria-busy', 'true');

            const selectedBranch = bulkForm.querySelector('#branch_id').selectedOptions[0]?.textContent.trim();
            const selectedBank = bulkForm.querySelector('#bank_id').selectedOptions[0]?.textContent.trim();
            const selectedFile = bulkForm.querySelector('#student_file').files[0]?.name;
            bulkUploadDetails.textContent = [selectedBranch, selectedBank, selectedFile].filter(Boolean).join(' · ');

            bulkUploadOverlay.classList.remove('d-none');
            bulkUploadOverlay.setAttribute('aria-hidden', 'false');
        });
    </script>
@endsection
