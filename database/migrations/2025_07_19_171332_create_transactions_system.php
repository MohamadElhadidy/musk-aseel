<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique(); // Internal transaction ID
            $table->string('gateway_transaction_id')->nullable(); // Payment gateway's transaction ID
            $table->morphs('transactionable'); // Can be linked to orders, refunds, etc.
            $table->string('gateway'); // paypal, stripe, razorpay, etc.
            $table->enum('type', ['payment', 'refund', 'partial_refund', 'authorization', 'capture', 'void']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'expired']);
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 3);
            $table->json('gateway_request')->nullable(); // Request sent to gateway
            $table->json('gateway_response')->nullable(); // Full response from gateway
            $table->string('gateway_status')->nullable(); // Gateway's status code
            $table->text('gateway_message')->nullable(); // Gateway's response message
            $table->string('reference_number')->nullable(); // For bank transfers, checks
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['transactionable_type', 'transactionable_id'], 'transactionable_unique');

            $table->index('gateway');
            $table->index('status');
            $table->index('created_at');
        });

        // Update payments table to link with transactions
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('order_id')->constrained()->onDelete('set null');
        });

        // Create payment methods table for saved payment methods
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('gateway'); // stripe, paypal, etc.
            $table->string('type'); // card, bank_account, paypal, etc.
            $table->string('gateway_customer_id')->nullable(); // Customer ID at payment gateway
            $table->string('gateway_payment_method_id')->nullable(); // Payment method ID at gateway
            $table->json('details'); // Masked card number, bank details, etc.
            $table->boolean('is_default')->default(false);
            $table->timestamp('expires_at')->nullable(); // For cards
            $table->timestamps();

            $table->index('user_id');
            $table->index(['gateway', 'gateway_customer_id']);
        });

        // Create transaction logs for detailed tracking
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->string('event'); // request_sent, response_received, webhook_received, etc.
            $table->json('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('created_at');
        });

        // Create webhooks table for payment gateway webhooks
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('event_id')->nullable(); // Gateway's event ID
            $table->string('event_type'); // payment.success, payment.failed, etc.
            $table->json('payload'); // Full webhook payload
            $table->json('headers')->nullable(); // Webhook headers for verification
            $table->enum('status', ['pending', 'processed', 'failed', 'ignored']);
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'event_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropColumn('transaction_id');
        });

        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('transaction_logs');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('transactions');
    }
};
