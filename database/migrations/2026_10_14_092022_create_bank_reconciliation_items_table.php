<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_reconciliation_items')) {
            Schema::create('bank_reconciliation_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('bank_reconciliation_id')
                    ->constrained('bank_reconciliations')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreignId('payment_transaction_id')
                    ->nullable()
                    ->constrained('payment_transactions')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->unsignedInteger('row_number')->nullable();
                $table->string('reference_no', 100);
                $table->decimal('statement_amount', 14, 2)->nullable();
                $table->decimal('internal_amount', 14, 2)->nullable();
                $table->dateTime('statement_paid_at')->nullable();
                $table->string('status', 32)->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();

                $table->index(['bank_reconciliation_id', 'status'], 'bank_recon_items_run_status_idx');
                $table->index(['bank_reconciliation_id', 'reference_no'], 'bank_recon_items_run_ref_idx');
            });
        }

        if (! Schema::hasIndex('bank_reconciliation_items', 'bank_recon_items_run_ref_idx')) {
            Schema::table('bank_reconciliation_items', function (Blueprint $table): void {
                $table->index(['bank_reconciliation_id', 'reference_no'], 'bank_recon_items_run_ref_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_items');
    }
};
