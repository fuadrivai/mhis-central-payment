@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Edit Fixed Virtual Account</h4>
            <p class="text-muted mb-0">
                Perubahan akan dikirim ke backend bank sebelum disimpan pada sistem.
            </p>
        </div>
        <a href="{{ route('virtual-accounts.fixed.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="fw-semibold mb-2">Periksa kembali data berikut:</p>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-8 col-lg-10">
            <form action="{{ route('virtual-accounts.fixed.update', $virtualAccount) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Data Siswa dan VA</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-4">
                            <dt class="col-sm-4">Nomor VA</dt>
                            <dd class="col-sm-8"><code>{{ $virtualAccount->virtual_account_no }}</code></dd>
                            <dt class="col-sm-4">NIS</dt>
                            <dd class="col-sm-8">{{ $virtualAccount->student->nis }}</dd>
                            <dt class="col-sm-4">Cabang / Bank</dt>
                            <dd class="col-sm-8">{{ $virtualAccount->branch->name }} / {{ $virtualAccount->bank->name }}</dd>
                        </dl>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Siswa / Nama VA</label>
                            <input id="name" name="name" type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $virtualAccount->student->name) }}" required maxlength="255">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="grade_class" class="form-label">Grade</label>
                            <input id="grade_class" name="grade_class" type="text"
                                class="form-control @error('grade_class') is-invalid @enderror"
                                value="{{ old('grade_class', $virtualAccount->student->grade_class) }}" maxlength="50">
                            @error('grade_class')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="parent_email" class="form-label">Email Utama</label>
                                <input id="parent_email" name="parent_email" type="email"
                                    class="form-control @error('parent_email') is-invalid @enderror"
                                    value="{{ old('parent_email', $virtualAccount->student->parent_email) }}" required maxlength="255">
                                @error('parent_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="additional_email" class="form-label">Email Tambahan <span class="text-muted">(opsional)</span></label>
                                <input id="additional_email" name="additional_email" type="email"
                                    class="form-control @error('additional_email') is-invalid @enderror"
                                    value="{{ old('additional_email', $virtualAccount->student->additional_email) }}" maxlength="255">
                                @error('additional_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="expired_at" class="form-label">Tanggal Kedaluwarsa VA</label>
                            <input id="expired_at" name="expired_at" type="datetime-local"
                                class="form-control @error('expired_at') is-invalid @enderror"
                                value="{{ old('expired_at', $virtualAccount->expired_at?->format('Y-m-d\TH:i')) }}" required>
                            @error('expired_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info mb-0" role="note">
                            NIS dan nomor VA tidak dapat diubah. Data lokal hanya disimpan setelah backend bank menerima perubahan.
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('virtual-accounts.fixed.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
