<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // What the promotion discounts.
            //   product   -> a single product (also covered by the pivot)
            //   products  -> a set of products (via the pivot)
            //   category  -> every product under a category (via category_id)
            //   all       -> the whole catalog
            $table->enum('target_type', ['product', 'products', 'category', 'all'])->default('products');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            // How the discount is calculated.
            //   percentage -> value is a percent (0-100)
            //   fixed      -> value is an absolute amount in the catalog currency
            // (bogo / free_shipping / custom are reserved for phase 2 and validated out for now)
            $table->enum('discount_type', ['percentage', 'fixed', 'bogo', 'free_shipping', 'custom'])->default('percentage');
            $table->decimal('value', 10, 2)->default(0);

            // Scheduling + resolution.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('priority')->default(0); // higher wins when promotions overlap
            $table->enum('status', ['active', 'paused'])->default('active');

            // Phase-2 scaffolding (columns exist so the model/API are forward-compatible;
            // not yet enforced by the engine).
            $table->unsignedInteger('max_uses')->nullable();      // total redemption cap
            $table->unsignedInteger('uses_count')->default(0);    // redemptions so far
            $table->string('audience')->default('all');           // 'all' | future user-group key
            $table->boolean('auto_random_weekly')->default(false); // weekly random-product picker (phase 2)

            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index('target_type');
        });

        // Products explicitly attached to a promotion (target_type = product/products).
        Schema::create('promotion_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['promotion_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
    }
};
