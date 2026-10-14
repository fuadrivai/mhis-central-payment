<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'email')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'email')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('email');
        });
    }
};
