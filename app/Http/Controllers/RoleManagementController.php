<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->paginate(15);

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        return $this->form(new Role);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $permissions = $this->permissionsWithModuleView($data['permissions']);
        $slug = Str::slug($data['name'], '_');

        if ($slug === '') {
            return back()->withInput()->withErrors(['name' => 'Nama role harus mengandung huruf atau angka.']);
        }

        if (Role::query()->where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['name' => 'Role dengan nama tersebut sudah ada.']);
        }

        DB::transaction(function () use ($data, $permissions, $slug): void {
            $role = Role::query()->create([
                'slug' => $slug,
                'name' => $data['name'],
                'is_system' => false,
            ]);
            $role->permissions()->sync($permissions);
        });

        return redirect()->route('roles.index')->with('status', 'Role berhasil dibuat.');
    }

    public function edit(Role $role): View
    {
        return $this->form($role);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validatedData($request);
        $permissions = $this->permissionsWithModuleView($data['permissions']);

        DB::transaction(function () use ($data, $permissions, $role): void {
            $role->update(['name' => $data['name']]);

            if (! $role->is_system) {
                $role->permissions()->sync($permissions);
            }
        });

        return redirect()->route('roles.index')->with('status', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'Role sistem tidak dapat dihapus.');
        }

        if (User::query()->where('role', $role->slug)->exists()) {
            return back()->with('error', 'Role masih digunakan oleh pengguna dan tidak dapat dihapus.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('status', 'Role berhasil dihapus.');
    }

    private function form(Role $role): View
    {
        return view('roles.form', [
            'role' => $role,
            'permissionGroups' => Permission::query()->orderBy('module')->orderBy('action')->get()->groupBy('module'),
            'selectedPermissions' => $role->exists ? $role->permissions()->pluck('permissions.id')->all() : [],
        ]);
    }

    /**
     * @param  array<int, int>  $permissionIds
     * @return array<int, int>
     */
    private function permissionsWithModuleView(array $permissionIds): array
    {
        $selectedPermissions = Permission::query()
            ->whereIn('id', $permissionIds)
            ->get(['id', 'module', 'action']);
        $modules = $selectedPermissions->pluck('module')->unique();
        $viewPermissionIds = Permission::query()
            ->whereIn('module', $modules)
            ->where('action', 'view')
            ->pluck('id')
            ->all();

        return $selectedPermissions->pluck('id')
            ->merge($viewPermissionIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, permissions: array<int, int>}
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
        ]);

        $data['permissions'] = $data['permissions'] ?? [];

        return $data;
    }
}
