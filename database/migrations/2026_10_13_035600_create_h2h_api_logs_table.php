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
        Schema::create('h2h_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('virtual_account_id')->nullable()->constrained('virtual_accounts')->cascadeOnUpdate()->nullOnDelete();
            $table->string('endpoint');
            $table->string('http_method', 10);
            $table->unsignedSmallInteger('response_status')->nullable()->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h2h_api_logs');
    }
};
