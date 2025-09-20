<?php
/**
 * Plugin Name: Astro Widget (Bloom Proxy, Zero-Trust)
 * Description: Secure shortcode + REST proxy for Bloom AstroAPI. Token is kept server-side in wp-config.php.
 * Version: 0.2.1
 * Author: You
 */

if (!defined('ABSPATH')) exit;

define('ASTRO_WIDGET_VERSION', '0.2.1');
define('ASTRO_WIDGET_SLUG', 'astro-widget');

// Optional config
if (!defined('ASTRO_WIDGET_ALLOWED_ORIGINS')) {
  define('ASTRO_WIDGET_ALLOWED_ORIGINS', ''); // e.g. 'https://example.com,https://staging.example.com'
}
if (!defined('ASTRO_WIDGET_RATE_LIMIT')) {
  define('ASTRO_WIDGET_RATE_LIMIT', 20); // requests / 10 minutes / IP
}

require_once __DIR__ . '/includes/class-astro-rest.php';
require_once __DIR__ . '/includes/class-astro-shortcode.php';

// Register REST
add_action('rest_api_init', function () {
  (new Astro_Widget_REST())->register_routes();
});

// Shortcode
add_action('init', function () {
  (new Astro_Widget_Shortcode())->register();
});

// Assets
add_action('wp_enqueue_scripts', function () {
  $nonce = wp_create_nonce('astrowidget_nonce');

  wp_enqueue_script(
    'astro-widget-js',
    plugins_url('assets/astro-widget.js', __FILE__),
    [],
    ASTRO_WIDGET_VERSION,
    true
  );

  wp_localize_script('astro-widget-js', 'AstroWidgetCfg', [
    'endpoint' => esc_url_raw(rest_url('astro/v1/horoscope')),
    'nonce'    => $nonce,
  ]);

  wp_enqueue_style(
    'astro-widget-css',
    plugins_url('assets/astro-widget.css', __FILE__),
    [],
    ASTRO_WIDGET_VERSION
  );
});

// Security headers (conservative CSP; disable via filter if needed)
add_action('send_headers', function () {
  if (!apply_filters('astrowidget_send_headers', true)) return;
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: no-referrer');
  $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'";
  header('Content-Security-Policy: ' . $csp);
  header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}, 10);

// ===== Helpers =====

// Read Bloom bearer token from wp-config.php
function astrowidget_get_bloom_token() {
  return defined('BLOOM_ACCESS_TOKEN') ? BLOOM_ACCESS_TOKEN : '';
}
