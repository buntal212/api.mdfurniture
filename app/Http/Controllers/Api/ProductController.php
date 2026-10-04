<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['category:id,nama', 'images'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('material', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $categoryId): Builder => $query->where('category_id', $categoryId))
            ->latest()
            ->simplePaginate($filters['per_page'] ?? 12)
            ->withQueryString();

        return response()->json($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create([
            ...$this->productData($request->validated()),
            'kode' => $this->generateCode(),
            'slug' => $this->uniqueSlug($request->string('nama')->toString()),
        ]);

        $this->storeImages($request->file('images', []), $product);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $product->load(['category:id,nama', 'images']),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->load(['category:id,nama', 'images'])]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $this->productData($request->validated());

        if (array_key_exists('nama', $data)) {
            $data['slug'] = $this->uniqueSlug($data['nama'], $product);
        }

        $product->update($data);
        $this->storeImages($request->file('images', []), $product);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product->fresh()->load(['category:id,nama', 'images']),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image);
            Storage::disk('public')->delete($image->thumbnailPath());
        }

        $product->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function productData(array $data): array
    {
        return collect($data)->only([
            'category_id', 'nama', 'deskripsi_singkat', 'deskripsi', 'harga', 'material',
            'panjang', 'lebar', 'tinggi', 'berat', 'stok', 'status_stok', 'featured',
            'aktif', 'meta_title', 'meta_description', 'canonical_url', 'og_title',
            'og_description', 'indexable',
        ])->all();
    }

    /** @param array<int, UploadedFile> $files */
    private function storeImages(array $files, Product $product): void
    {
        $nextOrder = (int) $product->images()->max('urutan') + 1;

        foreach ($files as $index => $file) {
            $path = $file->store('products', 'public');

            $image = $product->images()->create([
                'image' => $path,
                'alt_text' => $product->nama,
                'is_primary' => $product->images()->doesntExist() && $index === 0,
                'urutan' => $nextOrder + $index,
            ]);

            $this->createThumbnail($file, $image->thumbnailPath());
        }
    }

    private function createThumbnail(UploadedFile $file, string $thumbnailPath): void
    {
        if (! function_exists('imagewebp')) {
            return;
        }

        $source = @imagecreatefromstring($file->getContent());

        if ($source === false) {
            return;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, 480 / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagewebp($thumbnail, null, 78);
        $contents = ob_get_clean();

        if ($contents !== false) {
            Storage::disk('public')->put($thumbnailPath, $contents);
        }

        imagedestroy($source);
        imagedestroy($thumbnail);
    }

    private function generateCode(): string
    {
        do {
            $code = 'PRD-'.Str::upper(Str::random(10));
        } while (Product::query()->where('kode', $code)->exists());

        return $code;
    }

    private function uniqueSlug(string $name, ?Product $product = null): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $number = 2;

        while (Product::query()->where('slug', $slug)
            ->when($product, fn (Builder $query) => $query->whereKeyNot($product->getKey()))
            ->exists()) {
            $slug = "{$base}-{$number}";
            $number++;
        }

        return $slug;
    }
}
