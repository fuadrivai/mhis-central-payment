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
        Schema::create('branch_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')
                ->unique()
                ->constrained('payment_transactions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->text('webhook_url')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_webhook_deliveries');
    }
};
