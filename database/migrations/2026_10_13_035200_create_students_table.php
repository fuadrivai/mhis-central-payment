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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nis', 50);
            $table->string('nisn', 50)->nullable();
            $table->string('name');
            $table->string('grade_class', 50)->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->string('fixed_va_number', 50)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['branch_id', 'nis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
