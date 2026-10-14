<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional, so every step is guarded to allow re-running after a partial failure.
        if (! Schema::hasTable('banks')) {
            Schema::create('banks', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name');
                $table->string('driver', 30);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $mandiriId = DB::table('banks')->where('code', 'mandiri')->value('id')
            ?? DB::table('banks')->insertGetId([
                'code' => 'mandiri',
                'name' => 'Bank Mandiri',
                'driver' => 'mandiri',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if (! Schema::hasColumn('virtual_accounts', 'bank_id')) {
            Schema::table('virtual_accounts', function (Blueprint $table) {
                $table->foreignId('bank_id')->nullable()->after('branch_id')
                    ->constrained('banks')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('h2h_api_logs', 'bank_id')) {
            Schema::table('h2h_api_logs', function (Blueprint $table) {
                $table->foreignId('bank_id')->nullable()->after('branch_id')
                    ->constrained('banks')->cascadeOnUpdate()->nullOnDelete();
            });
        }

        DB::table('virtual_accounts')->whereNull('bank_id')->update(['bank_id' => $mandiriId]);
        DB::table('h2h_api_logs')->whereNull('bank_id')->update(['bank_id' => $mandiriId]);

        Schema::table('virtual_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable(false)->change();
        });

        // New unique indexes first: MySQL needs an index on invoice_id/bank_id for their foreign keys.
        if (! Schema::hasIndex('virtual_accounts', ['bank_id', 'virtual_account_no'], 'unique')) {
            Schema::table('virtual_accounts', function (Blueprint $table) {
                $table->unique(['bank_id', 'virtual_account_no']);
            });
        }

        if (! Schema::hasIndex('virtual_accounts', ['invoice_id', 'bank_id', 'va_type'], 'unique')) {
            Schema::table('virtual_accounts', function (Blueprint $table) {
                $table->unique(['invoice_id', 'bank_id', 'va_type']);
            });
        }

        if (Schema::hasIndex('virtual_accounts', ['virtual_account_no'], 'unique')) {
            Schema::table('virtual_accounts', function (Blueprint $table) {
                $table->dropUnique(['virtual_account_no']);
            });
        }

        if (Schema::hasIndex('virtual_accounts', ['invoice_id', 'va_type'], 'unique')) {
            Schema::table('virtual_accounts', function (Blueprint $table) {
                $table->dropUnique(['invoice_id', 'va_type']);
            });
        }

        if (Schema::hasColumn('students', 'fixed_va_number')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropUnique(['fixed_va_number']);
                $table->dropColumn('fixed_va_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('fixed_va_number', 50)->nullable()->after('parent_phone');
        });

        DB::table('virtual_accounts')
            ->where('va_type', 'fixed')
            ->whereNotNull('student_id')
            ->orderBy('id')
            ->each(function (object $virtualAccount): void {
                DB::table('students')
                    ->where('id', $virtualAccount->student_id)
                    ->whereNull('fixed_va_number')
                    ->update(['fixed_va_number' => $virtualAccount->virtual_account_no]);
            });

        Schema::table('students', function (Blueprint $table) {
            $table->unique('fixed_va_number');
        });

        Schema::table('virtual_accounts', function (Blueprint $table) {
            $table->unique('virtual_account_no');
            $table->unique(['invoice_id', 'va_type']);
        });

        Schema::table('virtual_accounts', function (Blueprint $table) {
            $table->dropUnique(['invoice_id', 'bank_id', 'va_type']);
            $table->dropUnique(['bank_id', 'virtual_account_no']);
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::table('h2h_api_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::dropIfExists('banks');
    }
};
