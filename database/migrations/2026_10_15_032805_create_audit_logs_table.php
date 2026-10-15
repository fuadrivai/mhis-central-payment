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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->string('actor_type', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('method', 10);
            $table->string('route_name')->nullable();
            $table->string('path');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->unsignedSmallInteger('status');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['route_name', 'created_at']);
        });

        DB::table('permissions')->insertOrIgnore([
            'key' => 'audit_logs.view',
            'module' => 'audit_logs',
            'action' => 'view',
            'label' => 'Lihat audit log',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('key', 'audit_logs.view')->delete();
        Schema::dropIfExists('audit_logs');
    }
};
