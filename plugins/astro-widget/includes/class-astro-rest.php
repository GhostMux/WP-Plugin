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

  // HTTPS (unless WP_DEBUG), nonce, simple rate-limit
  public function check_permissions(WP_REST_Request $req) {
    if (!is_ssl() && !WP_DEBUG) return false;

    $nonce = $req->get_header('x-astro-nonce') ?: $req->get_param('_awnonce');
    if (!wp_verify_nonce($nonce, 'astrowidget_nonce')) return false;

    // Basic IP rate limit: 20 req / 10 min
    $ip  = $this->client_ip();
    $key = 'astrowidget_rl_' . md5($ip);
    $n   = (int) get_transient($key);
    if ($n >= (defined('ASTRO_WIDGET_RATE_LIMIT') ? (int)ASTRO_WIDGET_RATE_LIMIT : 20)) return false;
    set_transient($key, $n + 1, 10 * MINUTE_IN_SECONDS);

    return true;
  }

  public function handle_submit(WP_REST_Request $req) {
    $token = astrowidget_get_bloom_token();
    if (!$token) return new WP_REST_Response(['error' => 'Missing Bloom access token'], 500);

    $p = $req->get_json_params();

    // Accept MM-DD-YYYY from client and convert to YYYY-MM-DD
    $birth_mdy = trim((string)($p['birth_date'] ?? ''));
    $birth_ymd = $this->mdy_to_ymd($birth_mdy);

    $data = [
      'name'        => $this->short($p['name'] ?? '', 80),
      'email'       => sanitize_email($p['email'] ?? ''),
      'birth_date'  => $birth_ymd,                                    // converted for API
      'birth_time'  => trim((string)($p['birth_time'] ?? '')),         // HH:MM 24h
      'timezone'    => trim((string)($p['timezone'] ?? 'UTC')),        // IANA
      'location'    => $this->long($p['location'] ?? '', 140),
      'lat'         => isset($p['lat']) && $p['lat'] !== '' ? floatval($p['lat']) : null,
      'lng'         => isset($p['lng']) && $p['lng'] !== '' ? floatval($p['lng']) : null,
      'house_system'=> sanitize_key($p['house_system'] ?? 'placidus'),
      'language'    => sanitize_key($p['language'] ?? 'en'),
      'type'        => sanitize_key($p['type'] ?? 'natal'),
    ];

    // Validation
    if ($data['name'] === '' || !is_email($data['email']) ||
        !$this->is_ymd($data['birth_date']) || !$this->is_hm($data['birth_time']) ||
        !$this->is_tz($data['timezone']) || $data['location'] === '') {
      return new WP_REST_Response(['error' => 'Invalid or missing required fields'], 400);
    }
    if (!is_null($data['lat']) && ($data['lat'] < -90 || $data['lat'] > 90))   return new WP_REST_Response(['error' => 'Latitude out of range'], 400);
    if (!is_null($data['lng']) && ($data['lng'] < -180 || $data['lng'] > 180)) return new WP_REST_Response(['error' => 'Longitude out of range'], 400);

    $payload = [
      'type'   => $data['type'],
      'person' => [
        'name'       => $data['name'],
        'email'      => $data['email'],
        'birth_date' => $data['birth_date'],   // YYYY-MM-DD
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

    // Replace with your actual Bloom endpoint
    $endpoint = 'https://api.bloom.be/astro/1.0/horoscope';

    $args = [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'User-Agent'    => 'AstroWidget/'.(defined('ASTRO_WIDGET_VERSION')?ASTRO_WIDGET_VERSION:'dev').' (+'.home_url().')',
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

  // ===== helpers =====
  private function client_ip() {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
      if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
    }
    return '0.0.0.0';
  }
  private function is_ymd($s){ return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $s); }
  private function is_hm($s){  return (bool)preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $s); }
  private function is_tz($s){ static $tz=null; if($tz===null)$tz=timezone_identifiers_list(); return in_array($s,$tz,true); }
  private function short($s,$n){ $s=wp_strip_all_tags((string)$s); $s=trim(preg_replace('/\s+/', ' ', $s)); return mb_substr($s,0,$n); }
  private function long($s,$n){ return $this->short($s,$n); }

  // Convert MM-DD-YYYY → YYYY-MM-DD; return '' if invalid
  private function mdy_to_ymd($mdy) {
    if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])-(\d{4})$/', $mdy, $m)) return '';
    return "{$m[3]}-{$m[1]}-{$m[2]}";
  }
}
