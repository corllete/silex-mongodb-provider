<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Dependencies were installed with Composer and this is the main project
    $loader = require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../../autoload.php')) {
    // As a dependency in another project's `vendor`
    $loader = require_once __DIR__ . '/../../../../autoload.php';
} else {
    throw new Exception('Can\'t find autoload.php. Did you install dependencies with Composer?');
}