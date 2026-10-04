<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get(['id', 'nama', 'slug'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'nama' => $category->nama,
                'slug' => $category->slug,
            ]);

        return response()->json($categories);
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $products = Product::query()
            ->where('aktif', true)
            ->whereHas('category', fn ($query) => $query->where('aktif', true))
            ->with(['category:id,nama,slug', 'images'])
            ->latest()
            ->simplePaginate(25, ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'nama' => $product->nama,
                'slug' => $product->slug,
                'deskripsi_singkat' => $product->deskripsi_singkat,
                'harga' => $product->harga,
                'category' => $product->category ? [
                    'nama' => $product->category->nama,
                    'slug' => $product->category->slug,
                ] : null,
                'images' => $product->images->take(1)->map(fn ($image): array => [
                    'thumbnail_url' => $image->thumbnail_url,
                    'alt_text' => $image->alt_text,
                ])->all(),
            ]);

        return response()->json($products);
    }
}
