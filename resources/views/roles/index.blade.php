@extends('template.main')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Role &amp; Permissions</h4>
            <p class="text-muted mb-0">Tentukan izin lihat, tambah, ubah, dan hapus untuk setiap role.</p>
        </div>
        <a href="{{ route('roles.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Tambah Role
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Jumlah Izin</th>
                        <th>Pengguna</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $role->name }}</span>
                                @if ($role->is_system)
                                    <span class="badge bg-label-warning ms-1">Sistem</span>
                                @endif
                            </td>
                            <td>{{ $role->is_system ? 'Akses penuh' : number_format($role->permissions_count) }}</td>
                            <td>{{ number_format($role->users_count) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bx bx-edit-alt me-1"></i> Atur Izin
                                </a>
                                @unless ($role->is_system)
                                    <form method="POST" action="{{ route('roles.destroy', $role) }}" class="d-inline"
                                        onsubmit="return confirm('Hapus role ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5">Belum ada role.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($roles->hasPages())
            <div class="card-body border-top">{{ $roles->links() }}</div>
        @endif
    </div>
@endsection
