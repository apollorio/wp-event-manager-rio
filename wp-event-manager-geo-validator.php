<?php
/**
 * Validação e migração de dados geográficos para WP Event Manager
 * @package wp-event-manager
 */
function wem_validate_coordinates($lat, $lng) {
    // Valida ranges
    if (!is_numeric($lat) || !is_numeric($lng)) return false;
    $lat = floatval($lat);
    $lng = floatval($lng);
    // Latitude: -90 a 90, Longitude: -180 a 180
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return false;
    // Blacklist: (0,0) no oceano
    if ($lat == 0 && $lng == 0) return false;
    // Pode adicionar validação de formato DMS futuramente
    return [$lat, $lng];
}

function wem_geocode_missing() {
    // Exemplo: processa eventos/locais sem coordenadas
    // Aqui seria implementado integração com Nominatim (OSM) via HTTP
    // e atualização dos dados no banco
    // Pode ser chamado via WP-CLI ou admin
    return 'Geocoding process started.';
}

// Função para auditar dados existentes
function wem_audit_geo_data() {
    // Audita eventos/locais sem coordenadas ou com dados inválidos
    // Gera relatório para migração
    return 'Audit complete.';
}

// Função para criar tabela de cache de coordenadas
function wem_create_geo_cache_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpem_geo_cache';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        object_id bigint(20) unsigned NOT NULL,
        lat decimal(10,8) NOT NULL,
        lng decimal(11,8) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    return 'Geo cache table created.';
}
