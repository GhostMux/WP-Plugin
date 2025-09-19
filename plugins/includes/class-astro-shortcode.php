<?php
if (!defined('ABSPATH')) exit;

class Astro_Widget_Shortcode {
  public function register() {
    add_shortcode('astro_widget', [$this, 'render']);
  }

  public function render($atts = [], $content = '') {
    ob_start(); ?>
    <div class="astro-widget">
      <form id="astro-form" novalidate>
        <div><label>Full Name <input type="text" name="name" required /></label></div>
        <div><label>Email <input type="email" name="email" required /></label></div>
        <div><label>Birth Date (YYYY-MM-DD) <input type="text" name="birth_date" required /></label></div>
        <div><label>Birth Time (HH:MM 24h) <input type="text" name="birth_time" required /></label></div>
        <div><label>Timezone (IANA) <input type="text" name="timezone" placeholder="America/New_York" required /></label></div>
        <div><label>Birthplace (City, Country) <input type="text" name="location" required /></label></div>
        <div><label>Latitude <input type="number" step="0.000001" name="lat" /></label></div>
        <div><label>Longitude <input type="number" step="0.000001" name="lng" /></label></div>
        <div>
          <label>House System
            <select name="house_system">
              <option value="placidus" selected>Placidus</option>
              <option value="koch">Koch</option>
              <option value="whole_sign">Whole Sign</option>
              <option value="equal">Equal</option>
              <option value="meridian">Meridian</option>
            </select>
          </label>
        </div>
        <div>
          <label>Language
            <select name="language">
              <option value="en" selected>English</option>
              <option value="es">Spanish</option>
              <option value="fr">French</option>
            </select>
          </label>
        </div>
        <input type="hidden" name="_awnonce" value="<?php echo esc_attr( wp_create_nonce('astrowidget_nonce') ); ?>" />
        <div><button type="submit">Generate Horoscope</button></div>
        <div><div id="astro-output" aria-live="polite"></div></div>
      </form>
    </div>
    <?php
    return ob_get_clean();
  }
}
