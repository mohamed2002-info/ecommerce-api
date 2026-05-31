<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Pc Portable',
            'slug' => Str::slug('Pc Portable'),
        ]);
        Category::create([
            'name' => 'Consoles',
            'slug' => Str::slug('Consoles'),
        ]);
    }
}
