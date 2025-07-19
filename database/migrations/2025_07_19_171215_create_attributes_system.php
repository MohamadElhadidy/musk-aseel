<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create attributes table (Size, Color, Material, etc.)
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // size, color, material
            $table->string('type'); // select, text, number, boolean, date
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true); // Can be used in filters
            $table->boolean('is_variant')->default(true); // Used for variants (size, color)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create attribute translations
        Schema::create('attribute_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->string('locale', 5);
            $table->string('name'); // Display name
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['attribute_id', 'locale']);
        });

        // Create attribute values (S, M, L for Size | Red, Blue for Color)
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->string('value'); // The actual value
            $table->string('color_hex')->nullable(); // For color swatches
            $table->string('image')->nullable(); // For texture/pattern swatches
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['attribute_id', 'value']);
        });

        // Create attribute value translations
        Schema::create('attribute_value_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_value_id')->constrained()->onDelete('cascade');
            $table->string('locale', 5);
            $table->string('label'); // Display label
            $table->timestamps();

            $table->unique(['attribute_value_id', 'locale']);
        });

        // Create attribute groups (Physical Attributes, Technical Specs)
        Schema::create('attribute_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create attribute group translations
        Schema::create('attribute_group_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_group_id')->constrained()->onDelete('cascade');
            $table->string('locale', 5);
            $table->string('name');
            $table->timestamps();

            $table->unique(['attribute_group_id', 'locale']);
        });

        // Link attributes to groups
        Schema::create('attribute_group_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_group_id')->constrained()->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'attribute_group_id']);
        });

        // Link attributes to categories (which attributes apply to which categories)
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'attribute_id']);
        });

        // Product attributes (non-variant attributes like brand story, care instructions)
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_value_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('text_value')->nullable(); // For text type attributes
            $table->decimal('number_value', 10, 2)->nullable(); // For number type attributes
            $table->boolean('boolean_value')->nullable(); // For boolean type attributes
            $table->date('date_value')->nullable(); // For date type attributes
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id']);
            $table->index('attribute_id');
        });



        // Product variant attributes (link variants to specific attribute values)
        Schema::create('product_variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_value_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(
                ['product_variant_id', 'attribute_id'],
                'pv_attr_unique'
            );

            $table->index(['attribute_id', 'attribute_value_id']);
        });

        // Attribute combinations for quick variant lookup
        Schema::create('product_variant_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->string('combination_string'); // "color:red|size:xl|material:cotton"
            $table->json('combination_array'); // [{"attribute_id": 1, "value_id": 5}, ...]
            $table->timestamps();

            $table->unique(['product_id', 'combination_string'], 'product_combination_unique');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_combinations');
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('category_attributes');
        Schema::dropIfExists('attribute_group_mappings');
        Schema::dropIfExists('attribute_group_translations');
        Schema::dropIfExists('attribute_groups');
        Schema::dropIfExists('attribute_value_translations');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_translations');
        Schema::dropIfExists('attributes');
    }
};
