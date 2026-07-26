<?php
session_start();

$code = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
$_SESSION['captcha'] = $code;

$width = 360; $height = 120;
$image = imagecreatetruecolor($width, $height);

$bg = imagecolorallocate($image, 230, 230, 230);
imagefill($image, 0, 0, $bg);

// 1. 大量交錯的圓弧
for ($i = 0; $i < 15; $i++) {
    $line_color = imagecolorallocate($image, rand(100, 200), rand(100, 200), rand(100, 200));
    imagearc($image, rand(-50, $width+50), rand(-50, $height+50), rand(200, 600), rand(200, 600), 0, 360, $line_color);
}

// 2. 隨機雜點
for ($i = 0; $i < 500; $i++) {
    $pixel_color = imagecolorallocate($image, rand(50, 200), rand(50, 200), rand(50, 200));
    imagesetpixel($image, rand(0, $width), rand(0, $height), $pixel_color);
}

// 3. 多色扭曲文字
$font_path = __DIR__ . '/icon/ARLRDBD.TTF';
for ($i = 0; $i < strlen($code); $i++) {
    // 【關鍵】為每個字元產生隨機顏色 (RGB)
    $text_color = imagecolorallocate($image, rand(0, 150), rand(0, 150), rand(0, 150));
    
    $size = rand(35, 45);
    $angle = rand(-35, 35);
    $x = 30 + ($i * 55) + rand(-10, 10);
    $y = 60 + rand(-20, 20);
    
    if (file_exists($font_path)) {
        imagettftext($image, $size, $angle, $x, $y, $text_color, $font_path, $code[$i]);
    } else {
        imagestring($image, 5, $x, $y, $code[$i], $text_color);
    }
}

header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
exit;
?>