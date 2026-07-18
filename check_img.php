<?php
$img = 'C:\xampp\htdocs\pos\uploads\20260718_175721_Screenshot_2026-07-18-23-56-30-29_40deb401b9ffe8e1df2f1cc5ba480b12.jpg';
$info = getimagesize($img);
echo "Width: " . $info[0] . "\n";
echo "Height: " . $info[1] . "\n";
echo "Mime: " . $info['mime'] . "\n";

// Try to extract some basic info
$exif = @exif_read_data($img);
if ($exif) {
    echo "Make: " . ($exif['Make'] ?? 'N/A') . "\n";
    echo "Model: " . ($exif['Model'] ?? 'N/A') . "\n";
}
