<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_xx_xx_xxxxxx_create_categories_table.php
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); // Auto-increment ID
            $table->string('name'); // Category name
            $table->string('slug')->unique(); // Slug for SEO-friendly URLs
            $table->timestamps(); // Created at and Updated at timestamps
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
