<?php
/**
 * Script de diagnostic pour vérifier les extensions PHP disponibles
 * Accédez à: http://127.0.0.1:8000/check-extensions.php
 */

header('Content-Type: application/json');

$info = [
    'php_version' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'loaded_extensions' => get_loaded_extensions(),
    'gd' => [
        'loaded' => extension_loaded('gd'),
        'functions' => [
            'imagecreatefromjpeg' => function_exists('imagecreatefromjpeg'),
            'imagecreatefrompng' => function_exists('imagecreatefrompng'),
            'imagecreatefromgif' => function_exists('imagecreatefromgif'),
            'imagecreatefromwebp' => function_exists('imagecreatefromwebp'),
            'imagewebp' => function_exists('imagewebp'),
        ],
        'info' => function_exists('gd_info') ? gd_info() : null,
    ],
    'imagick' => [
        'loaded' => extension_loaded('imagick'),
        'formats' => extension_loaded('imagick') ? \Imagick::queryFormats() : null,
    ],
    'ini_files' => [
        'loaded' => php_ini_loaded_file(),
        'scanned' => php_ini_scanned_files(),
    ],
];

echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

