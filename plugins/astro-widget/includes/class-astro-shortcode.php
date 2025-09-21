<?php
if (!defined('ABSPATH')) exit;

class Astro_Widget_Shortcode {
  public function register() {
    add_shortcode('astro_widget', [$this, 'render']);
  }

  public function render($atts = [], $content = '') {
    // Ensure assets load on pages using the shortcode
    wp_enqueue_script('astro-widget-js');
    wp_enqueue_style('astro-widget-css');

    $atts = shortcode_atts([
      'type'         => 'natal',
      'house_system' => 'placidus',
      'language'     => 'en',
      'timezone'     => 'America/New_York',
    ], $atts, 'astro_widget');

    $endpoint = esc_url( rest_url('astro/v1/horoscope') );
    $nonce    = esc_attr( wp_create_nonce('astrowidget_nonce') );

    // Build timezone options from PHP's IANA list
    $tz_list = timezone_identifiers_list();
    // Group by continent for a nicer dropdown
    $grouped = [];
    foreach ($tz_list as $tz) {
      $parts = explode('/', $tz, 2);
      $group = $parts[0];
      $label = isset($parts[1]) ? $parts[1] : $parts[0];
      $grouped[$group][] = $tz;
    }

    ob_start(); ?>
    <div class="astro-widget">
      <form id="astro-form"
            novalidate
            autocomplete="off"
            data-endpoint="<?php echo $endpoint; ?>"
            data-nonce="<?php echo $nonce; ?>">

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
          <label>Birth Date (MM-DD-YYYY)
            <input type="text"
                   name="birth_date"
                   placeholder="08-19-1994"
                   required
                   pattern="(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])-\d{4}" />
          </label>

          <label>Birth Time (HH:MM 24h)
            <input type="text"
                   name="birth_time"
                   placeholder="14:30"
                   required
                   pattern="([01]\d|2[0-3]):([0-5]\d)" />
          </label>
        </div>

        <div class="row">
          <label>Timezone (IANA)
            <select name="timezone" required>
              <?php foreach ($grouped as $group => $zones): ?>
                <optgroup label="<?php echo esc_attr($group); ?>">
                  <?php foreach ($zones as $z): ?>
                    <option value="<?php echo esc_attr($z); ?>" <?php selected($atts['timezone'], $z); ?>>
                      <?php echo esc_html($z); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
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
                <option value="placidus"     <?php selected($atts['house_system'], 'placidus'); ?>>Placidus</option>
                <option value="koch"         <?php selected($atts['house_system'], 'koch'); ?>>Koch</option>
                <option value="whole_sign"   <?php selected($atts['house_system'], 'whole_sign'); ?>>Whole Sign</option>
                <option value="equal"        <?php selected($atts['house_system'], 'equal'); ?>>Equal</option>
                <option value="meridian"     <?php selected($atts['house_system'], 'meridian'); ?>>Meridian</option>
              </select>
            </label>

            <label>Language
              <select name="language">
                <option value="en" <?php selected($atts['language'], 'en'); ?>>English</option>
                <option value="es" <?php selected($atts['language'], 'es'); ?>>Spanish</option>
                <option value="fr" <?php selected($atts['language'], 'fr'); ?>>French</option>
                <option value="de" <?php selected($atts['language'], 'de'); ?>>German</option>
                <option value="it" <?php selected($atts['language'], 'it'); ?>>Italian</option>
                <option value="pt" <?php selected($atts['language'], 'pt'); ?>>Portuguese</option>
                <option value="nl" <?php selected($atts['language'], 'nl'); ?>>Dutch</option>
                <option value="pl" <?php selected($atts['language'], 'pl'); ?>>Polish</option>
                <option value="ru" <?php selected($atts['language'], 'ru'); ?>>Russian</option>
              </select>
            </label>
          </div>

          <div class="row">
            <label>Type
              <select name="type">
                <option value="natal"               <?php selected($atts['type'], 'natal'); ?>>Natal</option>
                <option value="transit"             <?php selected($atts['type'], 'transit'); ?>>Transit</option>
                <option value="solar_return"        <?php selected($atts['type'], 'solar_return'); ?>>Solar Return</option>
                <option value="lunar_return"        <?php selected($atts['type'], 'lunar_return'); ?>>Lunar Return</option>
                <option value="synastry"            <?php selected($atts['type'], 'synastry'); ?>>Synastry</option>
                <option value="composite"           <?php selected($atts['type'], 'composite'); ?>>Composite</option>
                <option value="zodiac_compatibility"<?php selected($atts['type'], 'zodiac_compatibility'); ?>>Zodiac Compatibility</option>
                <option value="chinese"             <?php selected($atts['type'], 'chinese'); ?>>Chinese</option>
              </select>
            </label>
          </div>
        </details>

        <input type="hidden" name="_awnonce" value="<?php echo $nonce; ?>" />

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
