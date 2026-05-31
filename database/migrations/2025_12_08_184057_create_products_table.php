<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('reference')->unique(); // Product reference
            $table->foreignId('sub_category_id')->constrained('sub_categories'); // Category reference
            $table->decimal('price', 10, 2); // Product price
            $table->text('description'); // Product description
            $table->string('image_url')->nullable(); // File path for the image
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
