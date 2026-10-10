<?php
// Create necessary upload directories
$directories = [
    '../uploads/courses/',
    '../uploads/courses/thumbnails/',
    '../uploads/lessons/',
    '../uploads/assignments/',
    '../uploads/profile/',
    '../uploads/temp/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        echo "Created directory: $dir<br>";
    } else {
        echo "Directory exists: $dir<br>";
    }
}

echo "<h3>All upload directories are ready!</h3>";
?>