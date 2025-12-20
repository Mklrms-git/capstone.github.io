<?php
// Prevent direct access
if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}

// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format currency
function formatCurrency($amount) {
    // Handle null, empty, or non-numeric values
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        return '₱0.00';
    }
    return '₱' . number_format($amount, 2);
}

// Calculate age from date
function calculateAge($birthDate) {
    if (empty($birthDate)) {
        return 0;
    }
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $diff = $today->diff($birth);
    return $diff->y;
}

// Format date - Returns format: "December 07, 2025"
function formatDate($date, $format = 'F d, Y') {
    if (empty($date)) {
        return '';
    }
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        return date($format, $timestamp);
    } catch (Exception $e) {
        return '';
    }
}

// Format date with time - Returns format: "December 07, 2025 1:14 AM"
function formatDateTime($date, $format = 'F d, Y g:i A') {
    if (empty($date)) {
        return '';
    }
    try {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        return date($format, $timestamp);
    } catch (Exception $e) {
        return '';
    }
}

// Format time
function formatTime($time, $format = 'g:i A') {
    if (empty($time)) {
        return '';
    }
    try {
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            return '';
        }
        return date($format, $timestamp);
    } catch (Exception $e) {
        return '';
    }
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 
        ceil($length/strlen($x)))), 1, $length);
}

// Generate a temporary password for new users
function generateTemporaryPassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '@#$%';
    
    // Ensure at least one character from each category
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest with random characters from all categories
    $all = $uppercase . $lowercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    // Shuffle the password to make it more random
    return str_shuffle($password);
}

// Normalize phone number to +63 format
// Accepts: 09123456789, +639123456789, 639123456789, etc.
// Returns: +639123456789
function normalizePhoneNumber($phone) {
    if (empty($phone)) {
        return '';
    }
    
    // Remove all non-digit characters except +
    $cleaned = preg_replace('/[^\d+]/', '', $phone);
    
    // If already starts with +63, return as is (after cleaning)
    if (preg_match('/^\+63\d{10}$/', $cleaned)) {
        return $cleaned;
    }
    
    // If starts with 63 (without +), add +
    if (preg_match('/^63\d{10}$/', $cleaned)) {
        return '+' . $cleaned;
    }
    
    // If starts with 0, replace with +63
    if (preg_match('/^0(\d{10})$/', $cleaned, $matches)) {
        return '+63' . $matches[1];
    }
    
    // If it's 10 digits starting with 9, assume it's missing the leading 0 or 63
    if (preg_match('/^9\d{9}$/', $cleaned)) {
        return '+63' . $cleaned;
    }
    
    // Return as is if it doesn't match any pattern
    return $phone;
}

// Format phone number for display (always shows +63)
function formatPhoneNumber($phone) {
    if (empty($phone)) {
        return '';
    }
    
    $normalized = normalizePhoneNumber($phone);
    
    // If normalization failed, return original
    if (empty($normalized) || !preg_match('/^\+63\d{10}$/', $normalized)) {
        return $phone;
    }
    
    return $normalized;
}

// Validate phone number (strictly enforces 11 digits total: +63 + 10 digits OR 0 + 10 digits OR 10 digits starting with 9)
// Letters are not allowed, country code is fixed to +63
function validatePhoneNumber($phone) {
    if (empty($phone)) {
        return false;
    }
    
    // Remove all non-digit characters except +
    $cleaned = preg_replace('/[^\d+]/', '', $phone);
    
    // Check if it matches valid format with exactly 10 digits after prefix:
    // +639123456789 (13 chars: +63 + exactly 10 digits)
    // 639123456789 (12 digits: 63 + exactly 10 digits)
    // 09123456789 (11 digits: 0 + exactly 10 digits)
    // 9123456789 (10 digits: just the 10 digits, will be normalized to +63 format)
    // Must have exactly 10 digits after the prefix (no more, no less) OR exactly 10 digits starting with 9
    $digitsOnly = preg_replace('/[^\d]/', '', $cleaned);
    
    // Accept formats with prefix:
    // +63 or 63 prefix: 12 digits total (63 + 10)
    // 0 prefix: 11 digits total (0 + 10)
    // No prefix, starting with 9: 10 digits
    return (preg_match('/^(\+63|63)\d{10}$/', $cleaned) && strlen($digitsOnly) === 12) ||
           (preg_match('/^0\d{10}$/', $cleaned) && strlen($digitsOnly) === 11) ||
           (preg_match('/^9\d{9}$/', $cleaned) && strlen($digitsOnly) === 10);
}

// Convert phone number to input format (09 format for easier input)
function phoneToInputFormat($phone) {
    if (empty($phone)) {
        return '';
    }
    
    $normalized = normalizePhoneNumber($phone);
    
    // Convert +639123456789 to 09123456789 for input fields
    if (preg_match('/^\+63(\d{10})$/', $normalized, $matches)) {
        return '0' . $matches[1];
    }
    
    return $phone;
}

// Debug function
function debug($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}