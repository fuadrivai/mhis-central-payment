<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('payment_transactions', 'bank_id')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->foreignId('bank_id')->nullable()->after('branch_id')
                    ->constrained('banks')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('payment_transactions', 'payment_request_id')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->string('payment_request_id', 100)->nullable()->after('payment_reference_no');
            });
        }

        DB::table('payment_transactions')->whereNull('bank_id')->update([
            'bank_id' => DB::raw(
                '(SELECT bank_id FROM virtual_accounts WHERE virtual_accounts.id = payment_transactions.virtual_account_id)',
            ),
        ]);

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('bank_id')->nullable(false)->change();
        });

        if (Schema::hasIndex('payment_transactions', ['payment_reference_no'], 'unique')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->dropUnique(['payment_reference_no']);
            });
        }

        if (! Schema::hasIndex('payment_transactions', ['bank_id', 'payment_reference_no'], 'unique')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->unique(['bank_id', 'payment_reference_no']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('payment_transactions', ['bank_id', 'payment_reference_no'], 'unique')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->dropUnique(['bank_id', 'payment_reference_no']);
            });
        }

        if (! Schema::hasIndex('payment_transactions', ['payment_reference_no'], 'unique')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->unique('payment_reference_no');
            });
        }

        if (Schema::hasColumn('payment_transactions', 'payment_request_id')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->dropColumn('payment_request_id');
            });
        }

        if (Schema::hasColumn('payment_transactions', 'bank_id')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('bank_id');
            });
        }
    }
};
