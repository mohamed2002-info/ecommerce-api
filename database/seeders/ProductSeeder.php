<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\SubCategory;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $pcPortableGamerSubCategory = SubCategory::where('name', 'PC Portable Gamer')->first();
        $pcPortableProSubCategory = SubCategory::where('name', 'PC Portable Pro')->first();
        $playstationSubCategory = SubCategory::where('name', 'Playstation')->first();

        // Seed products
        Product::create([
            'name' => 'Pc Portable Gamer ASUS TUF Gaming A15 TUF507NV',
            'reference' => 'TUF507NV-LP100W',
            'sub_category_id' => $pcPortableGamerSubCategory->id, // Reference the category by its ID
            'price' => 3349,
            'image_url' => '/images/Product1.jpg',
            'description' => 'Écran 15.6", Full HD (1920 x 1080), IPS, anti-reflets - Taux de rafraîchissement: 144 Hz G-Sync - Processeur AMD Ryzen 7 7735HS...',
        ]);

        Product::create([
            'name' => 'Pc Portable Gamer ASUS TUF Gaming F15 TUF507VV',
            'reference' => 'TUF507VV-LP197W',
            'sub_category_id' => $pcPortableGamerSubCategory->id,
            'price' => 3999,
            'image_url' => '/images/Product2.jpg',
            'description' => 'Écran 15.6", Full HD (1920 x 1080), IPS, anti-reflets - Taux de rafraîchissement: 144 Hz G-Sync - Processeur Intel Core i7-13620H...',
        ]);

        Product::create([
            'name' => 'Pc Portable Gamer HP Victus Gaming 15-fb0037nk',
            'reference' => '9U1B8EA',
            'sub_category_id' => $pcPortableGamerSubCategory->id,
            'price' => 2255,
            'image_url' => '/images/Product3.jpg',
            'description' => 'Écran Full HD 15.6" (1920 x 1080), antireflet - Taux de rafraîchissement 60 Hz - Processeur AMD Ryzen 5 5600H...',
        ]);

        Product::create([
            'name' => 'Pc Portable Gamer MSI Thin 15 B13U / i5-13420H',
            'reference' => 'B13UCX-2607XFR-24',
            'sub_category_id' => $pcPortableGamerSubCategory->id,
            'price' => 2397,
            'image_url' => '/images/Product4.jpg',
            'description' => 'Écran 15.6" Full HD (1920 x 1080), IPS, 144 Hz - Processeur Intel Core i5-13420H 13e génération...',
        ]);

        Product::create([
            'name' => 'Pc Portable Gamer ASUS ROG Strix G16 G614',
            'reference' => 'G614JIR-N4063W',
            'sub_category_id' => $pcPortableGamerSubCategory->id,
            'price' => 7999,
            'image_url' => '/images/Product5.jpg',
            'description' => 'Écran ROG Nebula Display 16" QHD+ (2560 x 1600, WQXGA), anti-reflet, 240Hz, G-Sync, HDR...',
        ]);

        Product::create([
            'name' => 'Pc Portable Lenovo Légion Pro 7',
            'reference' => '82WQ00A6FG-2Y',
            'sub_category_id' => $pcPortableGamerSubCategory->id,
            'price' => 11259,
            'image_url' => '/images/Product6.jpg',
            'description' => 'Écran 16" WQXGA (2560 x 1600 px) IPS antireflet - Display HDR - Taux de rafraîchissement 240 Hz...',
        ]);

        Product::create([
            'name' => 'PC Portable Gigabyte G6KF',
            'reference' => 'GB-G6KF-I7-32',
            'sub_category_id' => $pcPortableGamerSubCategory->id,
            'price' => 3819,
            'image_url' => '/images/Product7.jpg',
            'description' => 'Écran 16" Full HD+ (1920 x 1200) WUXGA, 165 Hz - Processeur Intel Core i7-13620H 13e génération...',
        ]);

        Product::create([
            'name' => 'Pc portable Dell Gaming G15 5530',
            'reference' => 'G15-5530-I74050-3Y',
            'sub_category_id' => $pcPortableGamerSubCategory->id,
            'price' => 3959,
            'image_url' => '/images/Product8.jpg',
            'description' => 'Ecran 15.6" FHD 165Hz - Processeur Intel Core i7-13650HX, up to 4.9 Ghz, 24 Mo de mémoire cache...',
        ]);

        Product::create([
            'name' => 'Apple MacBook Pro M3 Pro 14"',
            'reference' => 'MRX33FN/A',
            'sub_category_id' => $pcPortableProSubCategory->id,  // Reference the "Pc Portable Pro" category
            'price' => 8779,
            'image_url' => '/images/Product9.jpg',
            'description' => 'Écran 14" Liquid Retina XDR - Résolution: 3024 x 1964 pixels - Processeur Apple M3 Pro (11 cœurs/GPU14 cœurs)...',
        ]);

        Product::create([
            'name' => 'Pc Portable Dell Latitude 7330 2 en 1',
            'reference' => '7330-I7-3Y',
            'sub_category_id' => $pcPortableProSubCategory->id,
            'price' => 6359,
            'image_url' => '/images/Product10.jpg',
            'description' => 'Ecran 13,3" FHD - Tactile - Processeur Intel Core i7-1265U, up to 4.80 GHz, 12 Mo de mémoire cache...',
        ]);

        Product::create([
            'name' => 'Console De Jeux SONY Playstation 5 Slim + Jeux FIFA FC25',
            'reference' => 'BU-PS5SLIM-FC25',
            'sub_category_id' => $playstationSubCategory->id,  // Reference the "Playstation" category
            'price' => 3199,
            'image_url' => '/images/Product11.jpg',
            'description' => 'Console De Jeux SONY Playstation 5 Slim - Chipset graphique: AMD RDNA 2 10.28 TFLOPs...',
        ]);

        Product::create([
            'name' => 'Console De Jeux SONY Playstation 4 Slim 500Go - Noir',
            'reference' => 'PS4-500G-SLIM-BLACK',
            'sub_category_id' => $playstationSubCategory->id,
            'price' => 1459,
            'image_url' => '/images/Product12.jpg',
            'description' => 'Console De Jeux SONY Playstation 4 Slim - Processeur: x86-64 AMD Jaguar Octo-Core...',
        ]);
        
    }
}
