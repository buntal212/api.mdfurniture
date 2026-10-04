<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kode', 50)->unique();
            $table->string('nama', 200);
            $table->string('slug', 220)->unique();
            $table->string('deskripsi_singkat', 500)->nullable();
            $table->longText('deskripsi')->nullable();
            $table->decimal('harga', 15, 2)->nullable();
            $table->string('material', 150)->nullable();
            $table->decimal('panjang', 10, 2)->nullable();
            $table->decimal('lebar', 10, 2)->nullable();
            $table->decimal('tinggi', 10, 2)->nullable();
            $table->decimal('berat', 10, 2)->nullable();
            $table->integer('stok')->default(0);
            $table->enum('status_stok', ['ready', 'preorder', 'out_of_stock'])->default('ready');
            $table->boolean('featured')->default(false)->index();
            $table->boolean('aktif')->default(true)->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->boolean('indexable')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
