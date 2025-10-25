<?php
if (!defined('ABSPATH')) exit;

// Polylang stub
if (!function_exists('pll_get_post')) {
    function pll_get_post($post_id) {
        return $post_id; // Return original if Polylang not active
    }
}

// Bookmark stub (until module is ready)
if (!function_exists('wem_is_bookmarked')) {
    function wem_is_bookmarked($post_id, $user_id = null) {
        return false; // Default: nothing bookmarked yet
    }
}
