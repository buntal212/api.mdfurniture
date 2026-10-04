<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_store_product_without_an_image(): void
    {
        $token = User::factory()->create()->createToken('test')->plainTextToken;
        $category = Category::factory()->create();

        $this->withToken($token)->postJson('/api/v1/products', ['category_id' => $category->id, 'kode' => 'PRD-001', 'nama' => 'Meja Jati', 'stok' => 2, 'og_image' => 'tidak-disimpan.jpg'])
            ->assertCreated()->assertJsonPath('data.nama', 'Meja Jati')->assertJsonPath('data.og_image', null);
    }
}
