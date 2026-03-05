// namespace App\Services;

// class WebpService
// { -->
// public static function convert(
// string $src,
// string $dest,
// int $quality = 70
// ): bool {

// if (! is_file($src)) {
// return false;
// }

// $mime = mime_content_type($src);

// if ($mime === 'image/webp') {
// return copy($src, $dest);
// }

// if ($mime === 'image/jpeg') {
// $img = imagecreatefromjpeg($src);
// } elseif ($mime === 'image/png') {
// $img = imagecreatefrompng($src);
// imagepalettetotruecolor($img);
// } else {
// return false;
// }

// @mkdir(dirname($dest), 0755, true);

// imagewebp($img, $dest, $quality);
// imagedestroy($img);

// return true;
// }





//




namespace App\Services;

class WebpService
{
public static function convert(
string $src,
string $dest,
int $quality = 60,
?int $width = null,
?int $height = null
): bool {
if (!file_exists($src)) return false;

$mime = mime_content_type($src);

if ($mime === 'image/jpeg') {
$image = imagecreatefromjpeg($src);
} elseif ($mime === 'image/png') {
$image = imagecreatefrompng($src);
imagepalettetotruecolor($image);
imagealphablending($image, true);
imagesavealpha($image, true);
} elseif ($mime === 'image/webp') {
$image = imagecreatefromwebp($src);
} else {
return false;
}

// 🔥 RESIZE if width & height provided
if ($width && $height) {
$resized = imagecreatetruecolor($width, $height);

imagealphablending($resized, false);
imagesavealpha($resized, true);

imagecopyresampled(
$resized,
$image,
0, 0, 0, 0,
$width, $height,
imagesx($image),
imagesy($image)
);

imagedestroy($image);
$image = $resized;
}

@mkdir(dirname($dest), 0755, true);

imagewebp($image, $dest, $quality);
imagedestroy($image);

return true;
}
}