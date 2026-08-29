<?php
// backend/helpers/validation.php

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            return "Field '$field' is required";
        }
    }
    return null;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhoneNumber($phone) {
    return preg_match('/^[0-9]{7,15}$/', $phone);
}

function validateAmount($amount) {
    return is_numeric($amount) && $amount > 0;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function validateString($str, $minLength = 1, $maxLength = 255) {
    $len = strlen($str);
    return $len >= $minLength && $len <= $maxLength;
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}