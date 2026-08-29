<?php
// backend/helpers/sanitize.php

function sanitizeString($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

function sanitizePhone($phone) {
    return preg_replace('/[^0-9+]/', '', $phone);
}

function sanitizeUrl($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function sanitizeAmount($amount) {
    return floatval(preg_replace('/[^0-9.]/', '', $amount));
}

function sanitizeArray($array) {
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = sanitizeArray($value);
        } else {
            $array[$key] = sanitizeString($value);
        }
    }
    return $array;
}

function escapeJson($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}