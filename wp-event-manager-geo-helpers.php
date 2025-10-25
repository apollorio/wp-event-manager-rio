<?php
/**
 * Helpers para validação geográfica
 * @package wp-event-manager
 */
function wem_has_valid_coordinates($lat, $lng) {
    return is_numeric($lat) && is_numeric($lng) && abs($lat) <= 90 && abs($lng) <= 180;
}

function wem_safe_address($address) {
    return wem_is_empty_string($address) ? __('No address', 'wp-event-manager') : $address;
}
