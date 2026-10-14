@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Callback API Key</h4>
        <p class="text-muted mb-0">Kelola autentikasi callback pembayaran dari server internal Bank Mandiri.</p>
    </div>

    @if (session('callback_api_key'))
        <div class="alert alert-warning" role="alert">
            <h6 class="alert-heading mb-2">
                <i class="bx bx-key me-1"></i> Simpan callback API key
            </h6>
            <p class="mb-2">
                Key ini hanya ditampilkan sekarang. Berikan melalui secret manager yang aman kepada tim bank dan jangan
                simpan di source code.
            </p>
            <code class="d-block bg-white rounded p-3 text-break user-select-all">{{ session('callback_api_key') }}</code>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Status API Key</h5>
            @if ($isConfigured)
                <p>
                    <span class="badge bg-label-success">Terkonfigurasi</span>
                    <span class="text-muted ms-2">Nilai key tidak dapat dilihat kembali.</span>
                </p>
            @else
                <p><span class="badge bg-label-warning">Belum dibuat</span></p>
            @endif

            <p class="text-muted">
                Server Mandiri harus mengirim key pada header <code>X-API-KEY</code> untuk setiap callback.
                Membuat key baru akan langsung menonaktifkan key sebelumnya.
            </p>

            <form method="POST" action="{{ route('settings.callback-api-key.store') }}"
                @if ($isConfigured)
                    onsubmit="return confirm('Membuat key baru akan langsung menonaktifkan key yang sedang dipakai bank. Lanjutkan?');"
                @endif>
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-refresh me-1"></i>
                    {{ $isConfigured ? 'Generate Ulang API Key' : 'Generate API Key' }}
                </button>
            </form>
        </div>
    </div>
@endsection
