<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('module');
            $table->string('action');
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
        });

        $roles = DB::table('users')->distinct()->pluck('role')->filter()->unique()->values();
        $roles->push('super_admin', 'finance_pusat');

        foreach ($roles->unique() as $slug) {
            DB::table('roles')->insertOrIgnore([
                'slug' => $slug,
                'name' => str($slug)->replace('_', ' ')->title()->toString(),
                'is_system' => $slug === 'super_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $modules = [
            'dashboard' => ['view' => 'Lihat dashboard'],
            'invoices' => [
                'view' => 'Lihat tagihan',
                'create' => 'Buat tagihan',
                'update' => 'Ubah tagihan',
                'delete' => 'Batalkan tagihan',
            ],
            'transactions' => [
                'view' => 'Lihat transaksi',
                'create' => 'Buat transaksi',
                'update' => 'Ulangi callback',
                'delete' => 'Hapus transaksi',
            ],
            'reconciliations' => [
                'view' => 'Lihat rekonsiliasi',
                'create' => 'Impor rekonsiliasi',
                'update' => 'Ubah rekonsiliasi',
                'delete' => 'Hapus rekonsiliasi',
            ],
            'fixed_virtual_accounts' => [
                'view' => 'Lihat Fixed VA',
                'create' => 'Buat Fixed VA',
                'update' => 'Ubah Fixed VA',
                'delete' => 'Hapus Fixed VA',
            ],
            'dynamic_virtual_accounts' => [
                'view' => 'Lihat Dynamic VA',
                'create' => 'Buat Dynamic VA',
                'update' => 'Ubah Dynamic VA',
                'delete' => 'Hapus Dynamic VA',
            ],
            'branches' => [
                'view' => 'Lihat cabang',
                'create' => 'Buat cabang',
                'update' => 'Ubah cabang dan API key',
                'delete' => 'Hapus cabang',
            ],
            'callback_api_keys' => [
                'view' => 'Lihat pengaturan callback API',
                'create' => 'Buat callback API key',
                'update' => 'Ubah callback API key',
                'delete' => 'Hapus callback API key',
            ],
        ];
        $permissions = [];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action => $label) {
                $permissions[] = [
                    'key' => $module.'.'.$action,
                    'module' => $module,
                    'action' => $action,
                    'label' => $label,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('permissions')->insert($permissions);
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
