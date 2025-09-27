<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Development mode
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(E_ALL & ~E_NOTICE);
}
?>