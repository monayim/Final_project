<?php
// Prevent multiple inclusions
if (defined('FUNCTIONS_LOADED')) {
    return; // Stop execution if already loaded
}
define('FUNCTIONS_LOADED', true);

// Check if functions are already declared to prevent redeclaration errors
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return trim(stripslashes(htmlspecialchars($input)));
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user']);
    }
}

if (!function_exists('hasRole')) {
    function hasRole($role) {
        return isset($_SESSION['user']) && $_SESSION['user']['role'] === $role;
    }
}

if (!function_exists('safe_session_start')) {
    function safe_session_start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout() {
        $timeout = 3600; // 1 hour
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
            session_unset();
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}
?>