<?php

declare(strict_types=1);

namespace App;

/**
 * 回の写真。storage/photos/ に保存し、/photos/{名前} で表示する。
 * スマホの写真は大きいので、GD があれば横1600pxに縮めて JPEG で保存する。
 */
final class Photos
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const MAX_WIDTH = 1600;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function dir(): string
    {
        return APP_ROOT . '/storage/photos';
    }

    /**
     * アップロードされた写真を保存する。ファイルがなければ name も error も null。
     *
     * @param array $file $_FILES['photo']
     * @return array{name: ?string, error: ?string}
     */
    public static function store(array $file): array
    {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code === UPLOAD_ERR_NO_FILE) {
            return ['name' => null, 'error' => null];
        }
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return ['name' => null, 'error' => '写真が大きすぎます（8MBまで）。'];
        }
        if ($code !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['name' => null, 'error' => '写真を受け取れませんでした。もう一度お試しください。'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['name' => null, 'error' => '写真が大きすぎます（8MBまで）。'];
        }
        try {
            return ['name' => self::saveImage((string) $file['tmp_name']), 'error' => null];
        } catch (\RuntimeException $e) {
            return ['name' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * 画像ファイルを縮めて保存し、ファイル名を返す。画像でなければ例外。
     */
    public static function saveImage(string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);
        $mime = $info['mime'] ?? '';
        if ($info === false || !isset(self::TYPES[$mime])) {
            throw new \RuntimeException('写真は JPEG・PNG・WebP の画像にしてください。');
        }
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('写真の保存先（storage/photos）を作れません。');
        }
        $base = bin2hex(random_bytes(12));

        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring((string) file_get_contents($sourcePath));
            if ($image !== false) {
                $image = self::fixOrientation($image, $sourcePath, $mime);
                $width = imagesx($image);
                if ($width > self::MAX_WIDTH) {
                    $scaled = imagescale($image, self::MAX_WIDTH, -1, IMG_BICUBIC);
                    if ($scaled !== false) {
                        imagedestroy($image);
                        $image = $scaled;
                    }
                }
                $name = $base . '.jpg';
                $ok = imagejpeg($image, "{$dir}/{$name}", 84);
                imagedestroy($image);
                if ($ok) {
                    return $name;
                }
            }
        }
        // GD がない・読めないときは、そのまま保存する
        $name = $base . '.' . self::TYPES[$mime];
        if (!copy($sourcePath, "{$dir}/{$name}")) {
            throw new \RuntimeException('写真を保存できませんでした。');
        }
        return $name;
    }

    public static function delete(?string $name): void
    {
        $path = $name === null ? null : self::path($name);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    /** 名前が正しい形なら、ファイルの場所を返す */
    public static function path(string $name): ?string
    {
        return preg_match('/\A[0-9a-f]{24}\.(jpg|png|webp)\z/', $name) ? self::dir() . '/' . $name : null;
    }

    public static function url(?string $name): ?string
    {
        return $name === null || $name === '' ? null : '/photos/' . $name;
    }

    /** 写真を返す（/photos/{名前}）。なければ 404 */
    public static function serve(string $name): void
    {
        $path = self::path($name);
        if ($path === null || !is_file($path)) {
            abort_not_found();
        }
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=2592000, immutable');
        readfile($path);
    }

    /** スマホの写真は向きの情報（EXIF）だけで回っていることがあるので、実際に回す */
    private static function fixOrientation(\GdImage $image, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);
        return $rotated;
    }
}
