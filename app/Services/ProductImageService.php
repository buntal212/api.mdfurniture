<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImageService
{
    private const string WatermarkText = 'md furni and craft probolinggo';

    private const int CanvasWidth = 1200;

    private const int CanvasHeight = 1500;

    private const int ThumbnailHeight = 480;

    private const int WebpQuality = 85;

    /**
     * @return array{image: string, thumbnail: string}
     */
    public function store(UploadedFile $file): array
    {
        $normalizedImage = $this->normalize($file);
        $thumbnail = null;
        $path = 'products/'.Str::uuid().'.webp';
        $thumbnailPath = 'products/thumbnails/'.pathinfo($path, PATHINFO_FILENAME).'.webp';
        $disk = Storage::disk('public');

        try {
            $thumbnail = $this->createThumbnail($normalizedImage);
            $this->watermark($normalizedImage);
            $this->watermark($thumbnail);

            if (! $disk->put($path, $this->encodeWebp($normalizedImage, self::WebpQuality))) {
                throw new RuntimeException('Gambar produk tidak dapat disimpan.');
            }

            if (! $disk->put($thumbnailPath, $this->encodeWebp($thumbnail, self::WebpQuality))) {
                $disk->delete($path);

                throw new RuntimeException('Thumbnail gambar produk tidak dapat disimpan.');
            }

            return [
                'image' => $path,
                'thumbnail' => $thumbnailPath,
            ];
        } finally {
            imagedestroy($normalizedImage);

            if ($thumbnail instanceof GdImage) {
                imagedestroy($thumbnail);
            }
        }
    }

    public function normalize(UploadedFile $file): GdImage
    {
        $this->ensureImageProcessingIsAvailable();

        $contents = file_get_contents($file->getRealPath());
        $source = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Gambar produk tidak dapat diproses.');
        }

        $orientedImage = $source;

        try {
            $orientedImage = $this->applyExifOrientation($source, $file);

            if ($orientedImage !== $source) {
                imagedestroy($source);
            }

            return $this->fitToCanvas($orientedImage);
        } finally {
            imagedestroy($orientedImage);
        }
    }

    private function ensureImageProcessingIsAvailable(): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Pemrosesan gambar produk belum tersedia pada server.');
        }
    }

    private function applyExifOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $metadata = @exif_read_data($file->getRealPath());
        $orientation = is_array($metadata) ? (int) ($metadata['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 90),
            6 => $this->rotate($image, -90),
            7 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), -90),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    private function rotate(GdImage $image, int $angle): GdImage
    {
        $background = imagecolorallocate($image, 255, 255, 255);
        $rotated = imagerotate($image, $angle, $background);

        if (! $rotated instanceof GdImage) {
            throw new RuntimeException('Orientasi gambar produk tidak dapat diproses.');
        }

        return $rotated;
    }

    private function fitToCanvas(GdImage $source): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::CanvasWidth / $sourceWidth, self::CanvasHeight / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $canvas = $this->createWhiteCanvas(self::CanvasWidth, self::CanvasHeight);

        imagecopyresampled(
            $canvas,
            $source,
            (int) floor((self::CanvasWidth - $width) / 2),
            (int) floor((self::CanvasHeight - $height) / 2),
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        );

        return $canvas;
    }

    private function createThumbnail(GdImage $source): GdImage
    {
        $height = self::ThumbnailHeight;
        $width = (int) round(self::CanvasWidth / self::CanvasHeight * $height);
        $thumbnail = $this->createWhiteCanvas($width, $height);

        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source),
        );

        return $thumbnail;
    }

    private function createWhiteCanvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        return $canvas;
    }

    private function watermark(GdImage $image): void
    {
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
    }

    private function encodeWebp(GdImage $image, int $quality): string
    {
        ob_start();
        $written = imagewebp($image, null, $quality);
        $contents = ob_get_clean();

        if (! $written || $contents === false) {
            throw new RuntimeException('Gambar produk tidak dapat dikonversi ke WebP.');
        }

        return $contents;
    }
}
