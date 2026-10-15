<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'key' => 'reports.view',
            'module' => 'reports',
            'action' => 'view',
            'label' => 'Lihat laporan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permission_role')
            ->whereIn('permission_id', DB::table('permissions')->select('id')->where('key', 'reports.view'))
            ->delete();
        DB::table('permissions')->where('key', 'reports.view')->delete();
    }
};
