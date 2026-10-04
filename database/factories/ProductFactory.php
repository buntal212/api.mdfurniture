<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $nama = fake()->unique()->words(3, true);

        return ['category_id' => Category::factory(), 'kode' => fake()->unique()->bothify('PRD-###'), 'nama' => $nama, 'slug' => Str::slug($nama), 'stok' => 0, 'status_stok' => 'ready', 'aktif' => true, 'indexable' => true];
    }
}
