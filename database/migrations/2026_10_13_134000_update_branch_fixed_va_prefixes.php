<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $prefixes = [
            'BTR' => ['8576501', '85765010'],
            'BGK' => ['8576502', '85765020'],
            'SMG' => ['8576503', '85765030'],
        ];

        foreach ($prefixes as $code => [$oldPrefix, $newPrefix]) {
            $branch = DB::table('branches')->where('code', $code)->first();

            if ($branch === null || $branch->va_fixed_prefix !== $oldPrefix) {
                continue;
            }

            $hasStudents = DB::table('students')->where('branch_id', $branch->id)->exists();
            $hasFixedVirtualAccounts = DB::table('virtual_accounts')
                ->where('branch_id', $branch->id)
                ->where('va_type', 'fixed')
                ->exists();

            if ($hasStudents || $hasFixedVirtualAccounts) {
                throw new RuntimeException(
                    "Cannot update Fixed VA prefix for branch {$code}: student or Fixed VA records already exist. Reconcile existing VA numbers with H2H first.",
                );
            }

            DB::table('branches')
                ->where('id', $branch->id)
                ->update(['va_fixed_prefix' => $newPrefix]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefixes = [
            'BTR' => ['85765010', '8576501'],
            'BGK' => ['85765020', '8576502'],
            'SMG' => ['85765030', '8576503'],
        ];

        foreach ($prefixes as $code => [$newPrefix, $oldPrefix]) {
            $branch = DB::table('branches')->where('code', $code)->first();

            if ($branch === null || $branch->va_fixed_prefix !== $newPrefix) {
                continue;
            }

            $hasStudents = DB::table('students')->where('branch_id', $branch->id)->exists();
            $hasFixedVirtualAccounts = DB::table('virtual_accounts')
                ->where('branch_id', $branch->id)
                ->where('va_type', 'fixed')
                ->exists();

            if ($hasStudents || $hasFixedVirtualAccounts) {
                throw new RuntimeException(
                    "Cannot rollback Fixed VA prefix for branch {$code}: student or Fixed VA records already exist.",
                );
            }

            DB::table('branches')
                ->where('id', $branch->id)
                ->update(['va_fixed_prefix' => $oldPrefix]);
        }
    }
};
