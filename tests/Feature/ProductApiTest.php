<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_uploaded_product_image_is_normalized_to_a_webp_canvas(): void
    {
        Storage::fake('public');
        $token = User::factory()->create()->createToken('test')->plainTextToken;
        $category = Category::factory()->create();

        $this->withToken($token)->post('/api/v1/products', [
            'category_id' => $category->id,
            'nama' => 'Meja Lanskap',
            'images' => [UploadedFile::fake()->image('meja.jpg', 1600, 800)],
        ])->assertCreated()->assertJsonPath('data.images.0.alt_text', 'Meja Lanskap');

        $image = ProductImage::query()->firstOrFail();

        $this->assertStringStartsWith('products/', $image->image);
        $this->assertStringEndsWith('.webp', $image->image);
        Storage::disk('public')->assertExists([$image->image, $image->thumbnailPath()]);

        $dimensions = getimagesizefromstring(Storage::disk('public')->get($image->image));

        $this->assertSame(1200, $dimensions[0]);
        $this->assertSame(1500, $dimensions[1]);
        $this->assertSame(IMAGETYPE_WEBP, $dimensions[2]);
    }
}
