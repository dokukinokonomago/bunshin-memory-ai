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
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('subscription_ends_at');
            $table->foreignId('archived_by_user_id')
                ->nullable()
                ->after('archived_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('archive_reason', 500)->nullable()->after('archived_by_user_id');
            $table->timestamp('deletion_requested_at')->nullable()->after('archive_reason');
            $table->timestamp('scheduled_deletion_at')->nullable()->after('deletion_requested_at');
            $table->timestamp('purged_at')->nullable()->after('scheduled_deletion_at');

            $table->index('archived_at');
            $table->index('scheduled_deletion_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropIndex(['scheduled_deletion_at']);
            $table->dropConstrainedForeignId('archived_by_user_id');
            $table->dropColumn([
                'archived_at',
                'archive_reason',
                'deletion_requested_at',
                'scheduled_deletion_at',
                'purged_at',
            ]);
        });
    }
};
