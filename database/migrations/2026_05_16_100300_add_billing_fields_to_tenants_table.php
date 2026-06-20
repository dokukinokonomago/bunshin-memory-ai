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
            $table->string('billing_provider', 40)->nullable()->after('purged_at');
            $table->string('billing_customer_id')->nullable()->after('billing_provider');
            $table->string('billing_subscription_id')->nullable()->after('billing_customer_id');
            $table->string('billing_price_id')->nullable()->after('billing_subscription_id');
            $table->boolean('billing_cancel_at_period_end')->default(false)->after('billing_price_id');
            $table->timestamp('billing_last_synced_at')->nullable()->after('billing_cancel_at_period_end');

            $table->unique(
                ['billing_provider', 'billing_customer_id'],
                'tenants_billing_provider_customer_unique',
            );
            $table->unique(
                ['billing_provider', 'billing_subscription_id'],
                'tenants_billing_provider_subscription_unique',
            );
            $table->index(
                ['billing_provider', 'billing_price_id'],
                'tenants_billing_provider_price_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique('tenants_billing_provider_customer_unique');
            $table->dropUnique('tenants_billing_provider_subscription_unique');
            $table->dropIndex('tenants_billing_provider_price_index');
            $table->dropColumn([
                'billing_provider',
                'billing_customer_id',
                'billing_subscription_id',
                'billing_price_id',
                'billing_cancel_at_period_end',
                'billing_last_synced_at',
            ]);
        });
    }
};
