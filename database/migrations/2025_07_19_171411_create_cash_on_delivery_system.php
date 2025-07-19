<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add COD-specific fields to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_cod')->default(false)->after('payment_method_id');
            $table->decimal('cod_fee', 10, 2)->default(0)->after('is_cod');
            $table->decimal('amount_to_collect', 10, 2)->nullable()->after('cod_fee');
            $table->timestamp('payment_collected_at')->nullable()->after('amount_to_collect');
            $table->foreignId('collected_by')->nullable()->after('payment_collected_at')
                  ->constrained('users')->onDelete('set null'); // Delivery person/admin who collected
        });

        // Create delivery persons table
        Schema::create('delivery_persons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('id_number')->nullable(); // National ID or employee ID
            $table->boolean('is_active')->default(true);
            $table->decimal('cod_balance', 10, 2)->default(0); // Current COD amount they have
            $table->timestamps();
        });

        // Create COD collections table for tracking
        Schema::create('cod_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('restrict');
            $table->foreignId('delivery_person_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount_collected', 10, 2);
            $table->decimal('cod_fee', 10, 2)->default(0);
            $table->enum('status', ['pending', 'collected', 'deposited', 'reconciled']);
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('deposited_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('deposited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('deposit_reference')->nullable(); // Bank deposit slip number
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Create delivery assignments table
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_person_id')->constrained()->onDelete('restrict');
            $table->enum('status', ['assigned', 'accepted', 'picked_up', 'out_for_delivery', 'delivered', 'failed', 'returned']);
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->integer('delivery_attempts')->default(0);
            $table->date('scheduled_date')->nullable();
            $table->string('time_slot')->nullable(); // morning, afternoon, evening
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable(); // For GPS tracking
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        // Create COD remittance table for bulk deposits
        Schema::create('cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('remittance_number')->unique();
            $table->foreignId('delivery_person_id')->constrained()->onDelete('restrict');
            $table->decimal('total_amount', 10, 2);
            $table->integer('orders_count');
            $table->enum('status', ['pending', 'submitted', 'verified', 'deposited', 'reconciled']);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('deposited_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('bank_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Link orders to remittances
        Schema::create('cod_remittance_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remittance_id')->constrained('cod_remittances')->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            
            $table->unique(['remittance_id', 'order_id']);
        });

        // Update shipping methods to include COD availability
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->boolean('supports_cod')->default(false)->after('is_active');
            $table->decimal('cod_fee', 10, 2)->default(0)->after('supports_cod');
            $table->enum('cod_fee_type', ['fixed', 'percentage'])->default('fixed')->after('cod_fee');
        });

        // Add COD settings to settings that should be added
        // Example settings to add via seeder:
        // - cod.enabled (boolean)
        // - cod.fee (decimal)
        // - cod.fee_type (fixed/percentage)
        // - cod.max_order_amount (decimal)
        // - cod.min_order_amount (decimal)
        // - cod.available_cities (array of city_ids)
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_cod', 'cod_fee', 'amount_to_collect', 'payment_collected_at', 'collected_by']);
        });
        
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn(['supports_cod', 'cod_fee', 'cod_fee_type']);
        });
        
        Schema::dropIfExists('cod_remittance_orders');
        Schema::dropIfExists('cod_remittances');
        Schema::dropIfExists('delivery_assignments');
        Schema::dropIfExists('cod_collections');
        Schema::dropIfExists('delivery_persons');
    }
};