<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Store;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Expands the catalog with more electronics categories, sub-categories, and
 * products. Idempotent: re-running won't create duplicates. Each product gets
 * a real (royalty-free) image downloaded into public/images and per-store
 * stock across the three boutiques.
 *
 *   php artisan db:seed --class=CatalogExpansionSeeder
 */
class CatalogExpansionSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all();
        if ($stores->isEmpty()) {
            $this->command->warn('No stores found — run migrations first.');
            return;
        }

        // category => [ subCategory => [ products... ] ]
        // each product: [name, ref, price, description, imageSeed]
        $catalog = [
            'Tablettes' => [
                'Tablettes Android' => [
                    ['Samsung Galaxy Tab S9', 'SM-X710-256', 2199, 'Écran AMOLED 11", Snapdragon 8 Gen 2, 256 Go, S Pen inclus', 'tabletandroid1'],
                    ['Xiaomi Pad 6', 'XIA-PAD6-128', 1099, 'Écran 11" 144Hz, Snapdragon 870, 128 Go, batterie 8840 mAh', 'tabletandroid2'],
                    ['Lenovo Tab P12', 'LEN-P12-256', 1399, 'Écran 12.7" 2.9K, MediaTek Dimensity 7050, 256 Go', 'tabletandroid3'],
                ],
                'iPad' => [
                    ['Apple iPad Air M2', 'IPAD-AIR-M2-256', 2899, 'Écran Liquid Retina 11", puce M2, 256 Go, compatible Apple Pencil Pro', 'ipad1'],
                    ['Apple iPad 10e Gen', 'IPAD-10-64', 1599, 'Écran 10.9" Liquid Retina, puce A14 Bionic, 64 Go', 'ipad2'],
                ],
            ],
            'Audio' => [
                'Casques' => [
                    ['Sony WH-1000XM5', 'SONY-XM5-BLK', 1299, 'Casque sans fil à réduction de bruit, 30h autonomie, Hi-Res Audio', 'headphone1'],
                    ['Bose QuietComfort Ultra', 'BOSE-QCU-BLK', 1499, 'Réduction de bruit immersive, son spatial, confort premium', 'headphone2'],
                    ['JBL Tune 770NC', 'JBL-770NC', 399, 'Casque Bluetooth ANC, 70h autonomie, charge rapide', 'headphone3'],
                ],
                'Écouteurs' => [
                    ['Apple AirPods Pro 2', 'APP-PRO2-USBC', 1099, 'Réduction de bruit active, audio adaptatif, boîtier USB-C', 'earbuds1'],
                    ['Samsung Galaxy Buds3 Pro', 'SAM-BUDS3-PRO', 799, 'Écouteurs ANC, son 360, résistants à l\'eau IP57', 'earbuds2'],
                    ['Nothing Ear (a)', 'NOTH-EAR-A', 349, 'Écouteurs ANC, 42dB, autonomie 42h, design transparent', 'earbuds3'],
                ],
            ],
            'Accessoires' => [
                'Claviers' => [
                    ['Logitech MX Keys S', 'LOG-MXKEYS-S', 459, 'Clavier sans fil rétroéclairé, multi-appareils, USB-C', 'keyboard1'],
                    ['Keychron K8 Pro', 'KEY-K8-PRO', 549, 'Clavier mécanique sans fil, hot-swap, RGB, QMK/VIA', 'keyboard2'],
                ],
                'Souris' => [
                    ['Logitech MX Master 3S', 'LOG-MX3S', 389, 'Souris ergonomique 8K DPI, défilement MagSpeed, silencieuse', 'mouse1'],
                    ['Razer DeathAdder V3', 'RAZ-DAV3', 449, 'Souris gaming 30K DPI, 59g, switches optiques Gen-3', 'mouse2'],
                ],
            ],
            'Écrans' => [
                'Moniteurs Gaming' => [
                    ['LG UltraGear 27GR95QE', 'LG-27GR95QE', 3299, 'OLED 27" QHD 240Hz, 0.03ms, G-Sync, HDR10', 'monitor1'],
                    ['Samsung Odyssey G7', 'SAM-ODY-G7-32', 2499, 'Incurvé 32" QHD 240Hz, 1ms, FreeSync Premium Pro', 'monitor2'],
                ],
                'Moniteurs Bureautique' => [
                    ['Dell UltraSharp U2723QE', 'DELL-U2723QE', 2799, '4K IPS Black 27", USB-C hub, 99% sRGB, ergonomique', 'monitor3'],
                ],
            ],
            'Montres Connectées' => [
                'Smartwatches' => [
                    ['Apple Watch Series 9', 'AW-S9-45-GPS', 1799, 'GPS 45mm, écran Retina toujours actif, détection accidents', 'watch1'],
                    ['Samsung Galaxy Watch6', 'SAM-GW6-44', 1199, 'Écran AMOLED 44mm, suivi santé avancé, Wear OS', 'watch2'],
                    ['Garmin Venu 3', 'GAR-VENU3', 1699, 'AMOLED, GPS, suivi sommeil & énergie, 14 jours autonomie', 'watch3'],
                ],
            ],
        ];

        $createdProducts = 0;

        foreach ($catalog as $categoryName => $subCategories) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => Str::slug($categoryName)]
            );

            foreach ($subCategories as $subName => $products) {
                $subCategory = SubCategory::firstOrCreate(
                    ['name' => $subName, 'category_id' => $category->id],
                    ['slug' => Str::slug($subName) . '-' . Str::random(5)]
                );

                foreach ($products as [$name, $ref, $price, $description, $imageSeed]) {
                    // Skip if a product with this reference already exists.
                    if (Product::where('reference', $ref)->exists()) {
                        continue;
                    }

                    $imageUrl = $this->downloadImage($imageSeed);

                    $product = Product::create([
                        'name' => $name,
                        'reference' => $ref,
                        'sub_category_id' => $subCategory->id,
                        'price' => $price,
                        'description' => $description,
                        'image_url' => $imageUrl,
                    ]);

                    // Per-store stock (varied; occasionally 0 to show out-of-stock).
                    foreach ($stores as $store) {
                        ProductStock::updateOrCreate(
                            ['product_id' => $product->id, 'store_id' => $store->id],
                            ['quantity' => rand(0, 18)]
                        );
                    }

                    $createdProducts++;
                    $this->command->info("Added: {$name}");
                }
            }
        }

        $this->command->info("Done. {$createdProducts} new products added.");
    }

    /**
     * Download a royalty-free image into public/images and return its web path.
     * Falls back to null (no image) if the download fails.
     */
    private function downloadImage(string $seed): ?string
    {
        $filename = 'cat-' . $seed . '.jpg';
        $dir = public_path('images');
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($fullPath)) {
            return '/images/' . $filename;
        }

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        try {
            // Deterministic per-seed image so re-runs are stable.
            $url = "https://picsum.photos/seed/{$seed}/700/700";
            $data = @file_get_contents($url);
            if ($data === false || strlen($data) < 1000) {
                return null;
            }
            file_put_contents($fullPath, $data);
            return '/images/' . $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
