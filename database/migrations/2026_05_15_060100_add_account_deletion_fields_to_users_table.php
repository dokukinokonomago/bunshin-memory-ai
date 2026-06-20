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
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable()->after('remember_token');
            $table->timestamp('anonymized_at')->nullable()->after('deleted_at');

            $table->index(['tenant_id', 'role', 'account_status', 'deleted_at'], 'users_tenant_role_status_deleted_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_tenant_role_status_deleted_index');
            $table->dropColumn(['deleted_at', 'anonymized_at']);
        });
    }
};
