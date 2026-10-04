<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::factory()->create([
            'nama' => 'Ruang Tamu',
            'slug' => 'ruang-tamu',
            'deskripsi' => 'Koleksi furniture untuk ruang tamu.',
            'urutan' => 1,
        ]);
    }
}
