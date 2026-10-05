<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private const string WatermarkText = 'md furni and craft probolinggo';

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

    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless((string) $image->product_id === (string) $product->getKey(), 404);

        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete([$image->image, $image->thumbnailPath()]);
        $image->delete();

        if ($wasPrimary) {
            $product->images()->first()?->update(['is_primary' => true]);
        }

        return response()->json([
            'message' => 'Foto produk berhasil dihapus.',
            'data' => $product->fresh()->load(['category:id,nama', 'images']),
        ]);
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
            $this->watermarkImage($path);

            $image = $product->images()->create([
                'image' => $path,
                'alt_text' => $product->nama,
                'is_primary' => $product->images()->doesntExist() && $index === 0,
                'urutan' => $nextOrder + $index,
            ]);

            $this->createThumbnail($path, $image->thumbnailPath());
        }
    }

    private function createThumbnail(string $sourcePath, string $thumbnailPath): void
    {
        if (! function_exists('imagewebp')) {
            return;
        }

        $source = @imagecreatefromstring(Storage::disk('public')->get($sourcePath));

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
        $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
        imagefill($thumbnail, 0, 0, $transparent);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagewebp($thumbnail, null, 70);
        $contents = ob_get_clean();

        if ($contents !== false) {
            Storage::disk('public')->put($thumbnailPath, $contents);
            $this->watermarkImage($thumbnailPath);
        }

        imagedestroy($source);
        imagedestroy($thumbnail);
    }

    private function watermarkImage(string $path): void
    {
        $disk = Storage::disk('public');
        $image = @imagecreatefromstring($disk->get($path));

        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $padding = max(4, min(16, (int) round(min($width, $height) * 0.02)));
        $font = 5;

        while ($font > 1 && imagefontwidth($font) * strlen(self::WatermarkText) > $width - ($padding * 2)) {
            $font--;
        }

        $textWidth = imagefontwidth($font) * strlen(self::WatermarkText);
        $textHeight = imagefontheight($font);
        $x = max($padding, $width - $textWidth - $padding);
        $y = max($padding, $height - $textHeight - $padding);

        imagealphablending($image, true);

        $background = imagecolorallocatealpha($image, 0, 0, 0, 72);
        $textColor = imagecolorallocatealpha($image, 255, 232, 190, 10);
        imagefilledrectangle(
            $image,
            max(0, $x - $padding),
            max(0, $y - $padding),
            min($width - 1, $x + $textWidth + $padding),
            min($height - 1, $y + $textHeight + $padding),
            $background,
        );
        imagestring($image, $font, $x, $y, self::WatermarkText, $textColor);

        ob_start();
        $written = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => imagejpeg($image, null, 85),
            'png' => imagepng($image, null, 6),
            'webp' => imagewebp($image, null, 80),
            default => false,
        };
        $contents = ob_get_clean();

        if ($written && $contents !== false) {
            $disk->put($path, $contents);
        }

        imagedestroy($image);
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
