<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'nama' => ['required', 'string', 'max:200'],
            'deskripsi_singkat' => ['nullable', 'string', 'max:500'],
            'deskripsi' => ['nullable', 'string'],
            'harga' => ['nullable', 'decimal:0,2'],
            'material' => ['nullable', 'string', 'max:150'],
            'panjang' => ['nullable', 'decimal:0,2'],
            'lebar' => ['nullable', 'decimal:0,2'],
            'tinggi' => ['nullable', 'decimal:0,2'],
            'berat' => ['nullable', 'decimal:0,2'],
            'stok' => ['nullable', 'integer', 'min:0'],
            'status_stok' => ['nullable', Rule::in(['ready', 'preorder', 'out_of_stock'])],
            'featured' => ['nullable', 'boolean'],
            'aktif' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'indexable' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=8000,max_height=8000', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.array' => 'Daftar foto tidak valid. Silakan pilih ulang foto produk.',
            'images.max' => 'Maksimal 10 foto yang dapat diunggah sekaligus.',
            'images.*.image' => 'Foto tidak dapat dibaca. Pilih file gambar JPG, PNG, atau WebP yang masih bisa dibuka.',
            'images.*.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
            'images.*.dimensions' => 'Ukuran foto terlalu besar. Gunakan foto dengan lebar dan tinggi maksimal 8000 piksel.',
            'images.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
        ];
    }
}
