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
        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('billing_provider', 40);
            $table->string('provider_event_id');
            $table->string('event_type', 120);
            $table->boolean('livemode')->default(false);
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_customer_id')->nullable();
            $table->string('billing_subscription_id')->nullable();
            $table->string('payload_hash', 64);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 40)->default('received');
            $table->string('error_code', 80)->nullable();
            $table->string('error_message', 512)->nullable();

            $table->unique(
                ['billing_provider', 'provider_event_id'],
                'billing_webhook_provider_event_unique',
            );
            $table->index(['tenant_id', 'received_at']);
            $table->index(['billing_provider', 'billing_customer_id'], 'billing_webhook_provider_customer_index');
            $table->index(['billing_provider', 'billing_subscription_id'], 'billing_webhook_provider_subscription_index');
            $table->index(['processing_status', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
