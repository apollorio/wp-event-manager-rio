<?php
if (!defined('ABSPATH')) exit;

function wem_safe_strlen($value) {
    return strlen(is_string($value) ? $value : '');
}

function wem_is_empty_string($value) {
    return wem_safe_strlen($value) === 0;
}

function wem_safe_count($value) {
    return is_countable($value) ? count($value) : 0;
}

function wem_safe_array($value) {
    return is_array($value) ? $value : array();
}
