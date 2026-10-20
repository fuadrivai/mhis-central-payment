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
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('invoices')
            ->whereNull('customer_name')
            ->update(['customer_name' => DB::raw('email')]);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('customer_name')->nullable(false)->change();
        });
    }
};
