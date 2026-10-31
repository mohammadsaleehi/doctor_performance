<?php
/**
 * Test bootstrap for WP Performance Doctor.
 */

$autoloader = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}
