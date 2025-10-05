<?php
if (!defined('ABSPATH')) exit;

class Astro_Widget_REST {

  public function register_routes() {
    register_rest_route('astro/v1', '/horoscope', [
      'methods'             => 'POST',
      'callback'            => [$this, 'handle_submit'],
      'permission_callback' => [$this, 'check_permissions'],
    ]);
  }

  /**
   * Zero-trust gate: HTTPS (unless WP_DEBUG), valid nonce, basic rate limiting.
   */
  public function check_permissions(WP_REST_Request $req) {
    // Require HTTPS in production
    if (!is_ssl() && !WP_DEBUG) return false;

    // Nonce from header or body
    $nonce = $req->get_header('x-astro-nonce') ?: $req->get_param('_awnonce');
    if (!wp_verify_nonce($nonce, 'astrowidget_nonce')) return false;

    // Simple IP rate limit: N req / 10 min
    $limit = defined('ASTRO_WIDGET_RATE_LIMIT') ? (int) ASTRO_WIDGET_RATE_LIMIT : 20;
    $ip    = $this->client_ip();
    $key   = 'astrowidget_rl_' . md5($ip);
    $hits  = (int) get_transient($key);
    if ($hits >= $limit) return false;
    set_transient($key, $hits + 1, 10 * MINUTE_IN_SECONDS);

    return true;
  }

  /**
   * Handle POST /astro/v1/horoscope
   * Accepts:
   * - birth_date  (MM/DD/YYYY)
   * - birth_time  (hh:mm:ss AM/PM)
   * - timezone    (IANA, e.g. America/New_York)
   * - name, email, location, lat, lng, house_system, language, type
   */
  public function handle_submit(WP_REST_Request $req) {
    $token = astrowidget_get_bloom_token();
    if (!$token) {
      return new WP_REST_Response(['error' => 'Missing Bloom access token'], 500);
    }

    $p = $req->get_json_params();

    // Convert incoming UI formats to API formats
    $birth_mdy = trim((string)($p['birth_date'] ?? ''));              // MM/DD/YYYY
    $birth_ymd = $this->mdy_to_ymd_slash($birth_mdy);                 // YYYY-MM-DD

    $time_12   = trim((string)($p['birth_time'] ?? ''));              // hh:mm:ss AM/PM
    $time_24s  = $this->time12_to_24_with_seconds($time_12);          // HH:MM:SS

    // Collect/sanitize
    $data = [
      'name'         => $this->short($p['name'] ?? '', 80),
      'email'        => sanitize_email($p['email'] ?? ''),
      'birth_date'   => $birth_ymd,
      'birth_time'   => $time_24s,
      'timezone'     => trim((string)($p['timezone'] ?? 'UTC')),
      'location'     => $this->long($p['location'] ?? '', 140),
      'lat'          => isset($p['lat']) && $p['lat'] !== '' ? floatval($p['lat']) : null,
      'lng'          => isset($p['lng']) && $p['lng'] !== '' ? floatval($p['lng']) : null,
      'house_system' => sanitize_key($p['house_system'] ?? 'placidus'),
      'language'     => sanitize_key($p['language'] ?? 'en'),
      'type'         => sanitize_key($p['type'] ?? 'natal'),
    ];

    // Validate core fields
    if (
      $data['name'] === '' ||
      !is_email($data['email']) ||
      !$this->is_ymd($data['birth_date']) ||
      !$this->is_hms($data['birth_time']) ||
      !$this->is_tz($data['timezone']) ||
      $data['location'] === ''
    ) {
      return new WP_REST_Response(['error' => 'Invalid or missing required fields'], 400);
    }
    if (!is_null($data['lat']) && ($data['lat'] < -90 || $data['lat'] > 90)) {
      return new WP_REST_Response(['error' => 'Latitude out of range'], 400);
    }
    if (!is_null($data['lng']) && ($data['lng'] < -180 || $data['lng'] > 180)) {
      return new WP_REST_Response(['error' => 'Longitude out of range'], 400);
    }

    // Build payload for Bloom
    $payload = [
      'type'   => $data['type'],
      'person' => [
        'name'       => $data['name'],
        'email'      => $data['email'],
        'birth_date' => $data['birth_date'],     // YYYY-MM-DD
        'birth_time' => $data['birth_time'],     // HH:MM:SS
        'timezone'   => $data['timezone'],       // IANA zone
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

    // Bloom endpoint (replace with correct path if different)
    $endpoint = 'https://api.bloom.be/astro/1.0/horoscope';

    $args = [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'User-Agent'    => 'AstroWidget/' . (defined('ASTRO_WIDGET_VERSION') ? ASTRO_WIDGET_VERSION : 'dev') . ' (+'.home_url().')',
      ],
      'body'    => wp_json_encode($payload),
      'timeout' => 20,
    ];

    $res = wp_remote_post($endpoint, $args);
    if (is_wp_error($res)) {
      return new WP_REST_Response(['error' => $res->get_error_message()], 502);
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);

    return new WP_REST_Response($json ?: ['raw' => $body], $code ?: 200);
  }

  // ===== Helpers =====

  private function client_ip() {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
      if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
    }
    return '0.0.0.0';
  }

  // MM/DD/YYYY -> YYYY-MM-DD ; '' if invalid
  private function mdy_to_ymd_slash($mdy) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])\/(\d{4})$/', $mdy)) return '';
    $dt = DateTime::createFromFormat('m/d/Y', $mdy);
    return $dt ? $dt->format('Y-m-d') : '';
  }

  // "hh:mm:ss AM/PM" -> "HH:MM:SS" ; '' if invalid
  private function time12_to_24_with_seconds($t12) {
    $t12 = strtoupper(trim(preg_replace('/\s+/', ' ', $t12)));
    // Accept "3:05:09 PM" or "03:05:09PM"
    if (!preg_match('/^(0?[1-9]|1[0-2]):[0-5]\d:[0-5]\d\s?(AM|PM)$/', $t12)) return '';
    // Normalize to "hh:mm:ss AM/PM" for parsing
    $t12 = preg_replace('/(AM|PM)$/', ' $1', $t12);
    $dt  = DateTime::createFromFormat('h:i:s A', $t12);
    return $dt ? $dt->format('H:i:s') : '';
  }

  private function is_ymd($s) {  // YYYY-MM-DD
    return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $s);
  }
  private function is_hms($s) {  // HH:MM:SS
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $s);
  }
  private function is_tz($s) {   // IANA zone
    static $tz = null;
    if ($tz === null) $tz = timezone_identifiers_list();
    return in_array($s, $tz, true);
  }

  private function short($s, $n) { // trim + collapse whitespace + limit
    $s = wp_strip_all_tags((string) $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return mb_substr($s, 0, $n);
  }
  private function long($s, $n) { return $this->short($s, $n); }
}
