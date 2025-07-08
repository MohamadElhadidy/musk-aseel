<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // {"en": "Standard Shipping", "ar": "الشحن العادي"}
            $table->json('description');
            $table->decimal('base_cost', 10, 2);
            $table->enum('calculation_type', ['flat', 'weight_based', 'price_based']);
            $table->json('rates')->nullable(); // For weight/price based calculations
            $table->integer('min_days')->default(1);
            $table->integer('max_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // {"en": "Zone 1", "ar": "المنطقة 1"}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_zone_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->onDelete('cascade');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['shipping_zone_id', 'city_id']);
        });

        Schema::create('shipping_method_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->onDelete('cascade');
            $table->foreignId('shipping_zone_id')->constrained()->onDelete('cascade');
            $table->decimal('cost_override', 10, 2)->nullable();
            $table->timestamps();
            
            $table->unique(['shipping_method_id', 'shipping_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_method_zone');
        Schema::dropIfExists('shipping_zone_cities');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_methods');
    }
};