<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThumbnailCacheTest extends TestCase
{
    public function test_thumbnail_is_served_with_a_long_cache_lifetime(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/thumbnails/contoh.webp', 'thumbnail');

        $response = $this->get('/api/v2/catalog/thumbnails/contoh.webp');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }
}
