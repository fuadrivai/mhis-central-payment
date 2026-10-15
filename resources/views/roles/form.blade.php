@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">{{ $role->exists ? 'Atur Izin Role' : 'Tambah Role' }}</h4>
        <p class="text-muted mb-0">Pilih aksi yang dapat dilakukan oleh pengguna dengan role ini. Aksi pada modul juga memberi akses lihat.</p>
    </div>

    <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
        @csrf
        <input type="hidden" name="permissions_submitted" value="1">
        @if ($role->exists)
            @method('PUT')
        @endif
        @php($checkedPermissions = old('permissions_submitted')
            ? (is_array(old('permissions')) ? old('permissions') : [])
            : $selectedPermissions)

        <div class="card mb-4">
            <div class="card-body">
                <label for="name" class="form-label">Nama role</label>
                <input id="name" name="name" required maxlength="100" value="{{ old('name', $role->name) }}"
                    class="form-control @error('name') is-invalid @enderror" style="max-width: 520px;">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if ($role->is_system)
                    <div class="form-text">Role sistem super admin selalu memiliki akses penuh; izin di bawah tidak dapat diubah.</div>
                @endif
            </div>
        </div>

        @foreach ($permissionGroups as $module => $permissions)
            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ str($module)->replace('_', ' ')->title() }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($permissions as $permission)
                            <div class="col-sm-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                        id="permission-{{ $permission->id }}" value="{{ $permission->id }}"
                                        @disabled($role->is_system)
                                        @checked($role->is_system || in_array($permission->id, $checkedPermissions))>
                                    <label class="form-check-label" for="permission-{{ $permission->id }}">
                                        {{ $permission->label }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        @error('permissions')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @foreach ($errors->get('permissions.*') as $message)
            <div class="alert alert-danger">{{ $message }}</div>
        @endforeach

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Simpan Role</button>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
@endsection
