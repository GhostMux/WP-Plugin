<?php
/**
 * Plugin Name: Astro Widget (Bloom Proxy, Zero-Trust)
 * Description: Secure shortcode + REST proxy for Bloom AstroAPI. Keeps API key server-side; strict validation; rate limiting; HTTPS-only.
 * Version: 0.2.0
 * Author: You
 */

if (!defined('ABSPATH')) exit;

define('ASTRO_WIDGET_VERSION', '0.2.0');
define('ASTRO_WIDGET_SLUG', 'astro-widget');

// ── Required: put your Bloom API key in wp-config.php (not in DB):
// define('BLOOM_API_KEY', 'YOUR_REAL_KEY');

// Optional: allowed front-end origins for REST calls (comma-separated). Defaults to site host.
if (!defined('ASTRO_WIDGET_ALLOWED_ORIGINS')) {
  define('ASTRO_WIDGET_ALLOWED_ORIGINS', ''); // e.g. 'https://example.com,https://staging.example.com'
}

// Optional: hard rate-limit (requests per 10 minutes per IP).
if (!defined('ASTRO_WIDGET_RATE_LIMIT')) {
  define('ASTRO_WIDGET_RATE_LIMIT', 20);
}

require_once __DIR__ . '/includes/class-astro-rest.php';
require_once __DIR__ . '/includes/class-astro-shortcode.php';

// Register REST routes
add_action('rest_api_init', function () {
  (new Astro_Widget_REST())->register_routes();
});

// Shortcode
add_action('init', function () {
  (new Astro_Widget_Shortcode())->register();
});

// Enqueue assets only on front end
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

// Security headers (very safe defaults; toggle via filter 'astrowidget_send_headers')
add_action('send_headers', function () {
  if (!apply_filters('astrowidget_send_headers', true)) return;
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: no-referrer');
  // Conservative CSP; adjust if theme blocks scripts/styles. Keep connect-src to self only (we call Bloom server-side).
  $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'";
  header('Content-Security-Policy: ' . $csp);
  header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}, 10);

// Helper: get API key from wp-config.php only
function astrowidget_get_api_key() {
  return defined('BLOOM_API_KEY') ? BLOOM_API_KEY : '';
}
