<?php
// Create upload directories if they don't exist
$directories = [
    'uploads/materials',
    'uploads/lab_schedules'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
        echo "Created directory: $dir\n";
    } else {
        echo "Directory already exists: $dir\n";
    }
}

echo "Upload directories setup complete!\n";
?> 