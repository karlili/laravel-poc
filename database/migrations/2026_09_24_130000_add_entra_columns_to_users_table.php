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
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('azure_oid', 64)->nullable()->unique()->after('email');
            $table->string('azure_tenant_id', 64)->nullable()->after('azure_oid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['azure_oid']);
            $table->dropColumn(['azure_oid', 'azure_tenant_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
