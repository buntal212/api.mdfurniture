<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'image', 'alt_text', 'is_primary', 'urutan'])]
class ProductImage extends Model
{
    protected $appends = ['image_url', 'thumbnail_url'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        if (app()->runningInConsole()) {
            return Storage::disk('public')->url($this->image);
        }

        return request()->getSchemeAndHttpHost().'/storage/'.$this->image;
    }

    public function getThumbnailUrlAttribute(): string
    {
        $path = $this->thumbnailPath();

        if (! Storage::disk('public')->exists($path)) {
            return $this->image_url;
        }

        if (app()->runningInConsole()) {
            return Storage::disk('public')->url($path);
        }

        return request()->getSchemeAndHttpHost().'/api/v2/catalog/thumbnails/'.rawurlencode(basename($path));
    }

    public function thumbnailPath(): string
    {
        return 'products/thumbnails/'.pathinfo($this->image, PATHINFO_FILENAME).'.webp';
    }
}
