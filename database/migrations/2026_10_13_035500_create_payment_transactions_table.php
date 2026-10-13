<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('virtual_account_id')->constrained('virtual_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('paid_amount', 14, 2);
            $table->string('payment_reference_no', 100)->nullable()->unique();
            $table->string('channel', 50)->nullable();
            $table->dateTime('paid_at')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
