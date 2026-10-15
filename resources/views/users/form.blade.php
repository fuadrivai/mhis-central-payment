@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">{{ $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h4>
        <p class="text-muted mb-0">Atur informasi akun, status, dan role akses.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
                @csrf
                @if ($user->exists)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama</label>
                        <input id="name" name="name" required value="{{ old('name', $user->name) }}"
                            class="form-control @error('name') is-invalid @enderror">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}"
                            class="form-control @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="role" class="form-label">Role</label>
                        <select id="role" name="role" required class="form-select @error('role') is-invalid @enderror">
                            <option value="">Pilih role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->slug }}" @selected(old('role', $user->role) === $role->slug)>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">
                            Kata sandi {{ $user->exists ? '(kosongkan jika tidak diubah)' : '' }}
                        </label>
                        <input id="password" name="password" type="password" autocomplete="new-password"
                            @required(! $user->exists)
                            class="form-control @error('password') is-invalid @enderror">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Konfirmasi kata sandi</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" @required(! $user->exists) class="form-control">
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                            <input id="is_active" name="is_active" type="checkbox" value="1"
                                class="form-check-input" @checked((bool) old('is_active', $user->is_active))>
                            <label for="is_active" class="form-check-label">Akun aktif</label>
                        </div>
                        @error('is_active')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
