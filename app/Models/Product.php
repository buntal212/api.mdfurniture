<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'kode', 'nama', 'slug', 'deskripsi_singkat', 'deskripsi', 'harga', 'material', 'panjang', 'lebar', 'tinggi', 'berat', 'stok', 'status_stok', 'featured', 'aktif', 'meta_title', 'meta_description', 'canonical_url', 'og_title', 'og_description', 'indexable'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['harga' => 'decimal:2', 'panjang' => 'decimal:2', 'lebar' => 'decimal:2', 'tinggi' => 'decimal:2', 'berat' => 'decimal:2', 'featured' => 'boolean', 'aktif' => 'boolean', 'indexable' => 'boolean'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('urutan');
    }
}
