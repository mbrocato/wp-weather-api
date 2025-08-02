<?php
// Empty for now; can add advanced handlers like caching here
function wp_weather_cache_data($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'weather_cache';
    $wpdb->insert($table_name, array('data' => json_encode($data)));
}
