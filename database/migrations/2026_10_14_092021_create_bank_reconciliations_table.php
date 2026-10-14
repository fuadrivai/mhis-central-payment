<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('amount_mismatch_count')->default(0);
            $table->unsignedInteger('not_found_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('missing_from_bank_count')->default(0);
            $table->timestamps();

            $table->index(['bank_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
