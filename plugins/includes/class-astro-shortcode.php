<?php
if (!defined('ABSPATH')) exit;

class Astro_Widget_Shortcode {
  public function register() {
    add_shortcode('astro_widget', [$this, 'render']);
  }

  public function render($atts = [], $content = '') {
    ob_start(); ?>
    <div class="astro-widget">
      <form id="astro-form" novalidate autocomplete="off">
        <div class="row">
          <label>Full Name
            <input type="text" name="name" required maxlength="80" autocomplete="name" />
          </label>
        </div>

        <div class="row">
          <label>Email
            <input type="email" name="email" required maxlength="120" autocomplete="email" />
          </label>
        </div>

        <div class="row grid">
          <label>Birth Date (YYYY-MM-DD)
            <input type="text" name="birth_date" placeholder="1994-08-19" required pattern="\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])" />
          </label>
          <label>Birth Time (HH:MM 24h)
            <input type="text" name="birth_time" placeholder="14:30" required pattern="([01]\d|2[0-3]):([0-5]\d)" />
          </label>
        </div>

        <div class="row">
          <label>Timezone (IANA, e.g., America/New_York)
            <input type="text" name="timezone" placeholder="America/New_York" required />
          </label>
        </div>

        <div class="row">
          <label>Birthplace (City, Country)
            <input type="text" name="location" placeholder="Atlanta, USA" required maxlength="140" />
          </label>
        </div>

        <details class="adv">
          <summary>Advanced Options (optional)</summary>
          <div class="row grid">
            <label>Latitude
              <input type="number" step="0.000001" min="-90" max="90" name="lat" />
            </label>
            <label>Longitude
              <input type="number" step="0.000001" min="-180" max="180" name="lng" />
            </label>
          </div>
          <div class="row grid">
            <label>House System
              <select name="house_system">
                <option value="placidus" selected>Placidus</option>
                <option value="koch">Koch</option>
                <option value="whole_sign">Whole Sign</option>
                <option value="equal">Equal</option>
                <option value="meridian">Meridian</option>
              </select>
            </label>
            <label>Language
              <select name="language">
                <option value="en" selected>English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="it">Italian</option>
                <option value="pt">Portuguese</option>
                <option value="nl">Dutch</option>
                <option value="pl">Polish</option>
                <option value="ru">Russian</option>
              </select>
            </label>
          </div>
          <div class="row">
            <label>Type
              <select name="type">
                <option value="natal" selected>Natal</option>
                <option value="transit">Transit</option>
                <option value="solar_return">Solar Return</option>
                <option value="lunar_return">Lunar Return</option>
                <option value="synastry">Synastry</option>
                <option value="composite">Composite</option>
                <option value="zodiac_compatibility">Zodiac Compatibility</option>
                <option value="chinese">Chinese</option>
              </select>
            </label>
          </div>
        </details>

        <input type="hidden" name="_awnonce" value="<?php echo esc_attr( wp_create_nonce('astrowidget_nonce') ); ?>" />

        <div class="row">
          <button type="submit">Generate Horoscope</button>
        </div>

        <div class="row">
          <div id="astro-output" aria-live="polite"></div>
        </div>
      </form>
    </div>
    <?php
    return ob_get_clean();
  }
}
