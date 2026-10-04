<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nama = fake()->unique()->words(2, true);

        return [
            'nama' => $nama,
            'slug' => Str::slug($nama),
            'deskripsi' => fake()->optional()->sentence(),
            'urutan' => fake()->numberBetween(0, 20),
            'aktif' => true,
        ];
    }
}
