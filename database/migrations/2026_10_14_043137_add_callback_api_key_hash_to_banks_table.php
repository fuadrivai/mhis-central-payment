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
        if (! Schema::hasColumn('banks', 'callback_api_key_hash')) {
            Schema::table('banks', function (Blueprint $table): void {
                $table->string('callback_api_key_hash', 64)->nullable()->after('driver');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('banks', 'callback_api_key_hash')) {
            Schema::table('banks', function (Blueprint $table): void {
                $table->dropColumn('callback_api_key_hash');
            });
        }
    }
};
