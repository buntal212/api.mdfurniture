<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory()->create(['kode' => 'CTH-001', 'nama' => 'Meja Tamu Kayu Jati', 'slug' => 'meja-tamu-kayu-jati']);
    }
}
