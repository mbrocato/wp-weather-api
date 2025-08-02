<?php
/**
 * Plugin Name: Custom WordPress Weather API
 * Plugin URI: https://github.com/mbrocato/wp-weather-api
 * Description: Fetches weather data from a local Raspberry Pi station and displays it via shortcodes/widgets.
 * Version: 1.0
 * Author: Marc Brocato
 * Author URI: https://github.com/mbrocato
 * License: MIT
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('WP_WEATHER_API_VERSION', '1.0');
define('WP_WEATHER_API_PATH', plugin_dir_path(__FILE__));
define('WP_WEATHER_API_URL', plugin_dir_url(__FILE__));

// Include API handler
require_once WP_WEATHER_API_PATH . 'includes/api-handler.php';

// Register REST API endpoint
add_action('rest_api_init', 'wp_weather_register_endpoint');

function wp_weather_register_endpoint() {
    register_rest_route('weather/v1', '/current', array(
        'methods' => 'GET',
        'callback' => 'wp_weather_get_data',
        'permission_callback' => '__return_true',  // Public for simplicity; add auth if needed
    ));
}

function wp_weather_get_data(WP_REST_Request $request) {
    $pi_url = 'http://raspberrypi.local:3000/weather/current';  // Pi endpoint from elewin repo
    $api_key = get_option('wp_weather_api_key', '');  // Stored in WP settings

    $response = wp_remote_get($pi_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 10  // Handle network delays
    ));

    if (is_wp_error($response)) {
        return new WP_REST_Response(array('error' => $response->get_error_message()), 500);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_REST_Response(array('error' => 'Invalid JSON from Pi'), 500);
    }

    // Handle edge cases: e.g., missing data
    if (empty($data['temperature'])) {
        return new WP_REST_Response(array('error' => 'Incomplete data from Pi'), 400);
    }

    return new WP_REST_Response($data, 200);
}

// Shortcode to display weather
add_shortcode('weather_display', 'wp_weather_shortcode');

function wp_weather_shortcode($atts) {
    $response = wp_remote_get(rest_url('weather/v1/current'));
    if (is_wp_error($response)) {
        return '<p>Error fetching weather data.</p>';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data)) {
        return '<p>No weather data available.</p>';
    }

    ob_start();
    ?>
    <div class="weather-widget">
        <h3>Current Weather</h3>
        <p>Temperature: <?php echo esc_html($data['temperature']); ?>°C</p>
        <p>Humidity: <?php echo esc_html($data['humidity']); ?>%</p>
        <p>Wind Speed: <?php echo esc_html($data['wind_speed']); ?> km/h</p>
    </div>
    <?php
    return ob_get_clean();
}

// Widget class for sidebar display
class WP_Weather_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('wp_weather_widget', 'Weather Display', array('description' => 'Displays weather from Raspberry Pi'));
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo do_shortcode('[weather_display]');
        echo $args['after_widget'];
    }
}
add_action('widgets_init', function() { register_widget('WP_Weather_Widget'); });

// Admin settings page for API key
add_action('admin_menu', 'wp_weather_add_settings_page');

function wp_weather_add_settings_page() {
    add_options_page('Weather API Settings', 'Weather API', 'manage_options', 'wp-weather-api', 'wp_weather_settings_callback');
}

function wp_weather_settings_callback() {
    ?>
    <div class="wrap">
        <h1>Weather API Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_weather_options');
            do_settings_sections('wp-weather-api');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'wp_weather_register_settings');

function wp_weather_register_settings() {
    register_setting('wp_weather_options', 'wp_weather_api_key');
    add_settings_section('wp_weather_main', 'Main Settings', null, 'wp-weather-api');
    add_settings_field('wp_weather_api_key', 'Pi API Key', 'wp_weather_api_key_callback', 'wp-weather-api', 'wp_weather_main');
}

function wp_weather_api_key_callback() {
    $value = get_option('wp_weather_api_key', '');
    echo '<input type="text" name="wp_weather_api_key" value="' . esc_attr($value) . '" />';
}

// Activation hook: Create custom table if needed (example for caching)
register_activation_hook(__FILE__, 'wp_weather_activate');

function wp_weather_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'weather_cache';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        data text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
