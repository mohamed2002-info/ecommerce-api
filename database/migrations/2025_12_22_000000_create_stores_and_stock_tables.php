<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // e.g. "Boutique Sfax"
            $table->string('city');           // e.g. "Sfax"
            $table->string('slug')->unique(); // e.g. "sfax"
            $table->timestamps();
        });

        // Stock of a product at a specific store.
        Schema::create('product_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'store_id']);
        });

        // Seed the three boutiques.
        DB::table('stores')->insert([
            ['name' => 'Boutique Sfax',   'city' => 'Sfax',   'slug' => 'sfax',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Boutique Tunis',  'city' => 'Tunis',  'slug' => 'tunis',  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Boutique Sousse', 'city' => 'Sousse', 'slug' => 'sousse', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock');
        Schema::dropIfExists('stores');
    }
};
