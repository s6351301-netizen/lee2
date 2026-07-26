<?php
session_start();
$image = imagecreatetruecolor(100, 30);
$bg = imagecolorallocate($image, 200, 200, 200);
imagefill($image, 0, 0, $bg);
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>