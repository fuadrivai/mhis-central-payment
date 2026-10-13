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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('payment_type_id')->nullable()->constrained('payment_types')->cascadeOnUpdate()->nullOnDelete();
            $table->string('va_type', 20)->default('dynamic')->index();
            $table->string('invoice_number', 50)->unique();
            $table->string('source', 100)->index();
            $table->string('bill_code', 100);
            $table->string('bill_name');
            $table->string('customer_name');
            $table->string('customer_phone', 30)->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('fee_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('expired_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['branch_id', 'source', 'bill_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
