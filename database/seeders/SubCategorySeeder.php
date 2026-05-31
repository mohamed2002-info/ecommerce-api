<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubCategory;
use Illuminate\Support\Str;

class SubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SubCategory::create([
            'name' => 'PC Portable Pro',
            'category_id' => 1,  // Assuming '1' is the 'PC Portable' category ID
            'slug' => Str::slug('PC Portable Pro'),
        ]);
        
        SubCategory::create([
            'name' => 'PC Portable Gamer',
            'category_id' => 1,  // Assuming '1' is the 'PC Portable' category ID
            'slug' => Str::slug('PC Portable Gamer'),
        ]);
        
        SubCategory::create([
            'name' => 'Playstation',
            'category_id' => 2,  // Assuming '2' is the 'Consoles' category ID
            'slug' => Str::slug('Playstation'),
        ]);
    }
}
