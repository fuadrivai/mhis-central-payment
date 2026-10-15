@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Pengguna</h4>
            <p class="text-muted mb-0">Kelola akun, role, dan status akses pengguna.</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bx bx-user-plus me-1"></i> Tambah Pengguna
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('users.index') }}">
                <div class="input-group" style="max-width: 520px;">
                    <input type="search" class="form-control" name="search" value="{{ $search }}"
                        placeholder="Cari nama, email, atau role" aria-label="Cari pengguna">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bx bx-search me-1"></i> Cari
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Hapus</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="fw-semibold">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->roleModel?->name ?? str($user->role)->replace('_', ' ')->title() }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bx bx-edit-alt me-1"></i> Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="bx bx-user fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-0">Tidak ada pengguna yang cocok.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-body border-top">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
