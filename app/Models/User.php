<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'slug')->with('permissions');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->roleModel?->permissions->contains('key', $permission) ?? false;
    }

    public function landingRouteName(): string
    {
        foreach ([
            'dashboard.view' => 'home',
            'invoices.view' => 'invoices.index',
            'transactions.view' => 'transactions.index',
            'reconciliations.view' => 'reconciliations.index',
            'fixed_virtual_accounts.view' => 'virtual-accounts.fixed.index',
            'dynamic_virtual_accounts.view' => 'virtual-accounts.dynamic.index',
            'branches.view' => 'settings.branches.index',
            'callback_api_keys.view' => 'settings.callback-api-key.index',
            'reports.view' => 'reports.index',
            'audit_logs.view' => 'audit-logs.index',
        ] as $permission => $route) {
            if ($this->hasPermission($permission)) {
                return $route;
            }
        }

        return 'password.change';
    }
}
