<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $categories = Category::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('nama', 'like', "%{$search}%")
                        ->orWhere('deskripsi', 'like', "%{$search}%");
                });
            })
            ->orderBy('urutan')
            ->orderBy('nama')
            ->simplePaginate($filters['per_page'] ?? 12)
            ->withQueryString();

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['nama', 'deskripsi', 'urutan', 'aktif']);
        $data['slug'] = $this->makeUniqueSlug($data['nama']);

        $category = Category::query()->create($data);

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => $category]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->safe()->only(['nama', 'deskripsi', 'urutan', 'aktif']);

        if (array_key_exists('nama', $data)) {
            $data['slug'] = $this->makeUniqueSlug($data['nama'], $category);
        }

        $category->update($data);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }

    /**
     * Create a unique slug from a category name.
     */
    private function makeUniqueSlug(string $nama, ?Category $category = null): string
    {
        $baseSlug = Str::slug($nama) ?: 'kategori';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $category)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Determine whether a slug is used by another category.
     */
    private function slugExists(string $slug, ?Category $category = null): bool
    {
        $query = Category::query()->where('slug', $slug);

        if ($category) {
            $query->whereKeyNot($category->getKey());
        }

        return $query->exists();
    }
}
