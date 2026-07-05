<?php

$size = (int)($_GET['size'] ?? 192);

if($size < 64){
    $size = 64;
}

if($size > 1024){
    $size = 1024;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

if(!function_exists('imagecreatetruecolor')){
    http_response_code(503);
    exit;
}

$img = imagecreatetruecolor($size, $size);
$bg = imagecolorallocate($img, 2, 132, 199);
$inner = imagecolorallocate($img, 6, 182, 212);
$white = imagecolorallocate($img, 255, 255, 255);

imagefilledrectangle($img, 0, 0, $size, $size, $bg);
$padding = (int)round($size * 0.14);
imagefilledrectangle(
    $img,
    $padding,
    $padding,
    $size - $padding,
    $size - $padding,
    $inner
);

$text = 'T';
$font = 5;
$textWidth = imagefontwidth($font) * strlen($text);
$textHeight = imagefontheight($font);
imagestring(
    $img,
    $font,
    (int)(($size - $textWidth) / 2),
    (int)(($size - $textHeight) / 2),
    $text,
    $white
);

imagepng($img);
imagedestroy($img);
