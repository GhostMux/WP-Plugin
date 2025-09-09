<?php
if (!defined('ABSPATH')) exit;

class Astro_Widget_REST {
  public function register_routes() {
    register_rest_route('astro/v1', '/horoscope', [
      'methods'  => 'POST',
      'callback' => [$this, 'handle_submit'],
      'permission_callback' => [$this, 'check_permissions'],
    ]);
  }

  public function check_permissions(WP_REST_Request $req) {
    if (!is_ssl() && !WP_DEBUG) return false;
    $nonce = $req->get_header('x-astro-nonce') ?: $req->get_param('_awnonce');
    if (!wp_verify_nonce($nonce, 'astrowidget_nonce')) return false;
    return true;
  }

  public function handle_submit(WP_REST_Request $req) {
    $api_key = astrowidget_get_api_key();
    if (!$api_key) return new WP_REST_Response(['error' => 'Missing API key'], 500);

    $p = $req->get_json_params();
    $data = [
      'name'       => sanitize_text_field($p['name'] ?? ''),
      'email'      => sanitize_email($p['email'] ?? ''),
      'birth_date' => sanitize_text_field($p['birth_date'] ?? ''),
      'birth_time' => sanitize_text_field($p['birth_time'] ?? ''),
      'timezone'   => sanitize_text_field($p['timezone'] ?? 'UTC'),
      'location'   => sanitize_text_field($p['location'] ?? ''),
      'lat'        => isset($p['lat']) ? floatval($p['lat']) : null,
      'lng'        => isset($p['lng']) ? floatval($p['lng']) : null,
      'house_system'=> sanitize_text_field($p['house_system'] ?? 'placidus'),
      'language'   => sanitize_text_field($p['language'] ?? 'en'),
      'type'       => sanitize_text_field($p['type'] ?? 'natal'),
    ];

    $payload = [
      'type'   => $data['type'],
      'person' => [
        'name'       => $data['name'],
        'email'      => $data['email'],
        'birth_date' => $data['birth_date'],
        'birth_time' => $data['birth_time'],
        'timezone'   => $data['timezone'],
        'location'   => [
          'text' => $data['location'],
          'lat'  => $data['lat'],
          'lng'  => $data['lng'],
        ],
      ],
      'options' => [
        'house_system' => $data['house_system'],
        'language'     => $data['language'],
      ],
    ];

    $endpoint = 'https://api.bloom.be/api/places';
    $args = [
      'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
      ],
      'body'    => wp_json_encode($payload),
      'timeout' => 20,
    ];

    $res = wp_remote_post($endpoint, $args);
    if (is_wp_error($res)) return new WP_REST_Response(['error' => $res->get_error_message()], 502);

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);

    return new WP_REST_Response($json ?: ['raw' => $body], $code ?: 200);
  }
}
