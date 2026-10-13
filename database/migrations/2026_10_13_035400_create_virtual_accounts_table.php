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
        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnUpdate()->nullOnDelete();
            $table->string('va_type', 20)->default('dynamic')->index();
            $table->string('partner_service_id', 20);
            $table->string('customer_no', 50);
            $table->string('virtual_account_no', 50)->unique();
            $table->string('virtual_account_name');
            $table->decimal('current_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->json('h2h_response_raw')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'va_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_accounts');
    }
};
