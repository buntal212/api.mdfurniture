<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_access_categories(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_manage_categories_without_an_image(): void
    {
        $token = User::factory()->create()->createToken('test')->plainTextToken;

        $createResponse = $this->withToken($token)->postJson('/api/v1/categories', [
            'nama' => 'Ruang Tamu',
            'deskripsi' => 'Koleksi furniture untuk ruang tamu.',
            'urutan' => 1,
            'aktif' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('message', 'Kategori berhasil ditambahkan.')
            ->assertJsonPath('data.nama', 'Ruang Tamu')
            ->assertJsonPath('data.slug', 'ruang-tamu')
            ->assertJsonPath('data.gambar', null);

        $category = Category::query()->sole();

        $this->withToken($token)
            ->getJson('/api/v1/categories?search=ruang')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $category->id);

        $this->withToken($token)
            ->putJson("/api/v1/categories/{$category->id}", [
                'nama' => 'Ruang Keluarga',
                'deskripsi' => 'Koleksi furniture untuk ruang keluarga.',
                'urutan' => 2,
                'aktif' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'ruang-keluarga')
            ->assertJsonPath('data.aktif', false);

        $this->withToken($token)
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Kategori berhasil dihapus.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
