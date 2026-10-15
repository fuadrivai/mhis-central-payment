<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        $users = User::query()
            ->with('roleModel')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('role', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->appends(['search' => $search]);

        return view('users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
            'is_active' => ['required', 'boolean'],
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        return redirect()->route('users.index')->with('status', 'Pengguna berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
            'is_active' => ['required', 'boolean'],
        ]);

        $isDisablingLastSuperAdmin = $user->is_active
            && $user->role === 'super_admin'
            && (! $data['is_active'] || $data['role'] !== 'super_admin');

        if ($isDisablingLastSuperAdmin && User::query()->where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['role' => 'Super admin aktif terakhir tidak dapat dinonaktifkan atau diubah role-nya.']);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        if ($data['password'] !== null) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()->route('users.index')->with('status', 'Pengguna berhasil diperbarui.');
    }
}
