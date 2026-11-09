<?php
declare(strict_types=1);

/**
 * Player photos. The phone shrinks the photo before upload (app.js), so a normal upload is a ~40KB JPEG that is
 * stored as-is; anything else is re-encoded here.
 *
 * Every photo gets a new file name (bib + timestamp + random) that is saved in `player_img`, so a retaken photo is a new
 * url and nothing - Cloudflare, the S3 cache headers, the phones - can show the old one.
 *
 * PHOTO_STORAGE=s3     upload to S3_BUCKET/S3_PREFIX (production)
 * PHOTO_STORAGE=local  write to PHOTO_DIR, served by Apache at /photos/ (local dev)
 * PHOTO_URL is the display url with {file} and {w} (pixel width) placeholders.
 */
final class Photos
{
    private const MAX_SIDE = 640;
    private const MAX_BYTES = 12 * 1024 * 1024;

    public static function url(?string $file, int $width = 320): ?string
    {
        if (!$file) {
            return null;
        }
        $template = env('PHOTO_URL', '/photos/{file}');
        return str_replace(['{file}', '{w}'], [rawurlencode($file), (string) $width], $template);
    }

    /** Stores the photo and returns its file name, or throws InvalidArgumentException for a bad image. */
    public static function store(int $bib, string $bytes): string
    {
        $jpeg = self::normalize($bytes);
        $file = sprintf('player-image-%d-%d%s.jpg', $bib, time(), bin2hex(random_bytes(2)));

        if (env('PHOTO_STORAGE', 'local') === 's3') {
            self::putS3($file, $jpeg);
        } else {
            $dir = env('PHOTO_DIR', APP_ROOT . '/storage/photos');
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Cannot create $dir");
            }
            if (file_put_contents("$dir/$file", $jpeg) === false) {
                throw new RuntimeException("Cannot write $dir/$file");
            }
        }
        return $file;
    }

    /** Small JPEGs pass through; anything big, rotated or not a JPEG is re-encoded with GD. */
    private static function normalize(string $bytes): string
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Photo is too large');
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('That file is not a photo');
        }
        [$width, $height, $type] = $info;
        $orientation = 1;
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
            $orientation = (int) ($exif['Orientation'] ?? 1);
        }
        if ($type === IMAGETYPE_JPEG && $orientation === 1 && max($width, $height) <= self::MAX_SIDE) {
            return $bytes;
        }

        $image = imagecreatefromstring($bytes);
        if ($image === false) {
            throw new InvalidArgumentException('That photo could not be read');
        }
        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
        $scale = min(1, self::MAX_SIDE / max(imagesx($image), imagesy($image)));
        if ($scale < 1) {
            $image = imagescale($image, (int) round(imagesx($image) * $scale), (int) round(imagesy($image) * $scale), IMG_BICUBIC);
        }
        ob_start();
        imagejpeg($image, null, 82);
        return (string) ob_get_clean();
    }

    /**
     * Credentials: AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY through env() (so they can be in the settings secret),
     * otherwise the SDK's default chain - the EC2 instance role.
     */
    private static function putS3(string $file, string $jpeg): void
    {
        require_once APP_ROOT . '/vendor/autoload.php';
        $config = ['region' => env('S3_REGION', 'us-east-1')];
        if (env('AWS_ACCESS_KEY_ID') !== '') {
            $config['credentials'] = ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY')];
        }
        $s3 = new Aws\S3\S3Client($config);
        $object = [
            'Bucket'       => env('S3_BUCKET', 'cdn.cybernode.com'),
            'Key'          => env('S3_PREFIX', 'i/rwll/player-images/baseball/') . $file,
            'Body'         => $jpeg,
            'ContentType'  => 'image/jpeg',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ];
        if (env('S3_ACL', 'public-read') !== 'none') {
            $object['ACL'] = env('S3_ACL', 'public-read');
        }
        $s3->putObject($object);
    }
}
