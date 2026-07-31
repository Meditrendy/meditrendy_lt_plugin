<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function meditrendy_edrone_newsletter_app_id( $shortcode_app_id = '' ) {
    $settings = meditrendy_edrone_newsletter_settings();
    $app_id   = $shortcode_app_id ?: ( $settings['app_id'] ?: ( defined( 'MEDITRENDY_EDRONE_APP_ID' ) ? MEDITRENDY_EDRONE_APP_ID : '' ) );

    return apply_filters( 'meditrendy_edrone_newsletter_app_id', sanitize_text_field( $app_id ) );
}

function meditrendy_edrone_newsletter_customer_tag( $shortcode_tag = '' ) {
    $settings = meditrendy_edrone_newsletter_settings();
    $tag      = $shortcode_tag ?: $settings['customer_tag'];

    return apply_filters( 'meditrendy_edrone_newsletter_customer_tag', sanitize_text_field( $tag ) );
}

function meditrendy_edrone_newsletter_phone_example() {
    $language = function_exists( 'meditrendy_core_current_language' ) ? meditrendy_core_current_language() : 'lt';
    $examples = array(
        'lt' => '+37060000000',
        'lv' => '+37120000000',
        'et' => '+37251234567',
        'pl' => '+48123123123',
        'en' => '+441234567890',
    );

    return $examples[ $language ] ?? $examples['lt'];
}

function meditrendy_edrone_newsletter_translate( $text ) {
    $translation = __( $text, 'meditrendy-core' );

    if ( $translation !== $text || ! function_exists( 'meditrendy_core_current_language' ) ) {
        return $translation;
    }

    $translations = array(
        'lt' => array(
            'Newsletter' => 'Naujienlaiškis',
            'Receive news and offers by email.' => 'Gaukite naujienas ir pasiūlymus el. paštu.',
            'Subscribe' => 'Prenumeruoti',
            'I agree to receive newsletters and special offers by email.' => 'Sutinku gauti naujienlaiškius ir specialius pasiūlymus el. paštu.',
            'First name' => 'Vardas',
            'Email' => 'El. paštas',
            'Phone number' => 'Telefono numeris',
            'Phone number (optional)' => 'Telefono numeris (neprivaloma)',
            'Format: +37060000000' => 'Formatas: +37060000000',
            'Use the international format, for example %s.' => 'Naudokite tarptautinį formatą, pavyzdžiui, %s.',
            'Use the international format, for example +37060000000.' => 'Naudokite tarptautinį formatą, pavyzdžiui, +37060000000.',
            'Thank you!' => 'Ačiū!',
            'Enter a valid email address.' => 'Įveskite teisingą el. pašto adresą.',
            'Enter your first name.' => 'Įveskite savo vardą.',
            'Enter your phone number with a country code, for example %s.' => 'Įveskite telefono numerį su šalies kodu, pavyzdžiui, %s.',
            'Enter your phone number with a country code, for example +37060000000.' => 'Įveskite telefono numerį su šalies kodu, pavyzdžiui, +37060000000.',
            'Please accept the consent.' => 'Pažymėkite sutikimą.',
            'Edrone App ID is missing.' => 'Trūksta Edrone App ID.',
            'Edrone App ID is not configured.' => 'Edrone App ID dar nesukonfigūruotas.',
            'Unable to subscribe. Please try again.' => 'Nepavyko užsiprenumeruoti. Pabandykite dar kartą.',
            'Thank you! Your subscription has been registered.' => 'Ačiū! Prenumerata užregistruota.',
        ),
        'lv' => array(
            'Newsletter' => 'Jaunumu vēstule',
            'Receive news and offers by email.' => 'Saņemiet jaunumus un piedāvājumus e-pastā.',
            'Subscribe' => 'Abonēt',
            'I agree to receive newsletters and special offers by email.' => 'Piekrītu saņemt jaunumus un īpašos piedāvājumus e-pastā.',
            'First name' => 'Vārds',
            'Email' => 'E-pasts',
            'Phone number' => 'Tālruņa numurs',
            'Phone number (optional)' => 'Tālruņa numurs (nav obligāts)',
            'Format: +37060000000' => 'Formāts: +37060000000',
            'Use the international format, for example %s.' => 'Izmantojiet starptautisko formātu, piemēram, %s.',
            'Use the international format, for example +37060000000.' => 'Izmantojiet starptautisko formātu, piemēram, +37060000000.',
            'Thank you!' => 'Paldies!',
            'Enter a valid email address.' => 'Ievadiet derīgu e-pasta adresi.',
            'Enter your first name.' => 'Ievadiet savu vārdu.',
            'Enter your phone number with a country code, for example %s.' => 'Ievadiet tālruņa numuru ar valsts kodu, piemēram, %s.',
            'Enter your phone number with a country code, for example +37060000000.' => 'Ievadiet tālruņa numuru ar valsts kodu, piemēram, +37060000000.',
            'Please accept the consent.' => 'Lūdzu, atzīmējiet piekrišanu.',
            'Edrone App ID is missing.' => 'Trūkst Edrone App ID.',
            'Edrone App ID is not configured.' => 'Edrone App ID nav konfigurēts.',
            'Unable to subscribe. Please try again.' => 'Neizdevās abonēt. Lūdzu, mēģiniet vēlreiz.',
            'Thank you! Your subscription has been registered.' => 'Paldies! Jūsu abonements ir reģistrēts.',
        ),
        'et' => array(
            'Newsletter' => 'Uudiskiri',
            'Receive news and offers by email.' => 'Saage uudiseid ja pakkumisi e-posti teel.',
            'Subscribe' => 'Telli',
            'I agree to receive newsletters and special offers by email.' => 'Nõustun saama uudiskirju ja eripakkumisi e-posti teel.',
            'First name' => 'Eesnimi',
            'Email' => 'E-post',
            'Phone number' => 'Telefoninumber',
            'Phone number (optional)' => 'Telefoninumber (valikuline)',
            'Format: +37060000000' => 'Vorming: +37060000000',
            'Use the international format, for example %s.' => 'Kasutage rahvusvahelist vormingut, näiteks %s.',
            'Use the international format, for example +37060000000.' => 'Kasutage rahvusvahelist vormingut, näiteks +37060000000.',
            'Thank you!' => 'Aitäh!',
            'Enter a valid email address.' => 'Sisestage kehtiv e-posti aadress.',
            'Enter your first name.' => 'Sisestage oma eesnimi.',
            'Enter your phone number with a country code, for example %s.' => 'Sisestage telefoninumber koos riigikoodiga, näiteks %s.',
            'Enter your phone number with a country code, for example +37060000000.' => 'Sisestage telefoninumber koos riigikoodiga, näiteks +37060000000.',
            'Please accept the consent.' => 'Palun märkige nõusolek.',
            'Edrone App ID is missing.' => 'Edrone App ID puudub.',
            'Edrone App ID is not configured.' => 'Edrone App ID pole seadistatud.',
            'Unable to subscribe. Please try again.' => 'Tellimine ebaõnnestus. Proovige uuesti.',
            'Thank you! Your subscription has been registered.' => 'Aitäh! Teie tellimus on registreeritud.',
        ),
        'pl' => array(
            'Newsletter' => 'Newsletter',
            'Receive news and offers by email.' => 'Otrzymuj aktualności i oferty e-mailem.',
            'Subscribe' => 'Zapisz się',
            'I agree to receive newsletters and special offers by email.' => 'Zgadzam się na otrzymywanie newsletterów i ofert specjalnych e-mailem.',
            'First name' => 'Imię',
            'Email' => 'E-mail',
            'Phone number' => 'Numer telefonu',
            'Phone number (optional)' => 'Numer telefonu (opcjonalnie)',
            'Format: +37060000000' => 'Format: +37060000000',
            'Use the international format, for example %s.' => 'Użyj formatu międzynarodowego, na przykład %s.',
            'Use the international format, for example +37060000000.' => 'Użyj formatu międzynarodowego, na przykład +37060000000.',
            'Thank you!' => 'Dziękujemy!',
            'Enter a valid email address.' => 'Wpisz prawidłowy adres e-mail.',
            'Enter your first name.' => 'Wpisz swoje imię.',
            'Enter your phone number with a country code, for example %s.' => 'Wpisz numer telefonu z kodem kraju, na przykład %s.',
            'Enter your phone number with a country code, for example +37060000000.' => 'Wpisz numer telefonu z kodem kraju, na przykład +37060000000.',
            'Please accept the consent.' => 'Proszę zaznaczyć zgodę.',
            'Edrone App ID is missing.' => 'Brakuje Edrone App ID.',
            'Edrone App ID is not configured.' => 'Edrone App ID nie jest skonfigurowany.',
            'Unable to subscribe. Please try again.' => 'Nie udało się zapisać. Spróbuj ponownie.',
            'Thank you! Your subscription has been registered.' => 'Dziękujemy! Subskrypcja została zarejestrowana.',
        ),
    );

    $language = meditrendy_core_current_language();

    return $translations[ $language ][ $text ] ?? $text;
}

/**
 * Gets the settings used by the Edrone newsletter form.
 *
 * An app ID set in the administration panel takes precedence over the legacy
 * MEDITRENDY_EDRONE_APP_ID constant. A shortcode app_id attribute remains the
 * highest-priority, per-form override.
 */
function meditrendy_edrone_newsletter_settings() {
    $settings = get_option( 'meditrendy_edrone_newsletter_settings', array() );

    return wp_parse_args(
        is_array( $settings ) ? $settings : array(),
        array(
            'app_id'       => '',
            'customer_tag' => 'Newsletter Footer',
        )
    );
}

function meditrendy_edrone_newsletter_settings_sanitize( $input ) {
    $input = is_array( $input ) ? $input : array();

    return array(
        'app_id'       => sanitize_text_field( $input['app_id'] ?? '' ),
        'customer_tag' => sanitize_text_field( $input['customer_tag'] ?? '' ),
    );
}

function meditrendy_edrone_newsletter_settings_capability() {
    return current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_register_edrone_newsletter_settings() {
    register_setting(
        'meditrendy_edrone_newsletter_settings',
        'meditrendy_edrone_newsletter_settings',
        array(
            'sanitize_callback' => 'meditrendy_edrone_newsletter_settings_sanitize',
            'default'           => array(
                'app_id'       => '',
                'customer_tag' => 'Newsletter Footer',
            ),
        )
    );
}
add_action( 'admin_init', 'meditrendy_register_edrone_newsletter_settings' );

add_filter( 'option_page_capability_meditrendy_edrone_newsletter_settings', 'meditrendy_edrone_newsletter_settings_capability' );

function meditrendy_edrone_newsletter_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        'Edrone',
        'Edrone',
        meditrendy_edrone_newsletter_settings_capability(),
        'meditrendy-edrone',
        'meditrendy_render_edrone_newsletter_settings_page'
    );
}
add_action( 'admin_menu', 'meditrendy_edrone_newsletter_admin_menu', 15 );

function meditrendy_render_edrone_newsletter_settings_page() {
    if ( ! current_user_can( meditrendy_edrone_newsletter_settings_capability() ) ) {
        return;
    }

    $settings       = meditrendy_edrone_newsletter_settings();
    $constant_app_id = defined( 'MEDITRENDY_EDRONE_APP_ID' ) ? MEDITRENDY_EDRONE_APP_ID : '';
    ?>
    <div class="wrap">
        <h1>Edrone newsletter</h1>
        <p>Connect the <code>[meditrendy_edrone_newsletter]</code> form to Edrone. Submissions are sent server-to-server to Edrone's subscribe endpoint.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'meditrendy_edrone_newsletter_settings' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="meditrendy-edrone-app-id">Edrone App ID</label></th>
                    <td>
                        <input
                            id="meditrendy-edrone-app-id"
                            class="regular-text"
                            type="text"
                            name="meditrendy_edrone_newsletter_settings[app_id]"
                            value="<?php echo esc_attr( $settings['app_id'] ); ?>"
                            autocomplete="off"
                        >
                        <p class="description">Find this identifier in your Edrone account. It is required for newsletter subscriptions.</p>
                        <?php if ( $constant_app_id ) : ?>
                            <p class="description">A legacy <code>MEDITRENDY_EDRONE_APP_ID</code> constant is also defined. The value saved here overrides it; leave this field empty to use the constant.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="meditrendy-edrone-customer-tag">Newsletter customer tag</label></th>
                    <td>
                        <input
                            id="meditrendy-edrone-customer-tag"
                            class="regular-text"
                            type="text"
                            name="meditrendy_edrone_newsletter_settings[customer_tag]"
                            value="<?php echo esc_attr( $settings['customer_tag'] ); ?>"
                            placeholder="Newsletter Footer"
                        >
                        <p class="description">Identifies where the subscriber came from. This value is sent to Edrone as <code>customer_tags</code>.</p>
                        <p class="description">A shortcode <code>tag</code> attribute can override this value for an individual form.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Save Edrone settings' ); ?>
        </form>
    </div>
    <?php
}

function meditrendy_edrone_newsletter_enqueue_assets() {
    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/edrone-newsletter.css';
    $js_path  = MEDITRENDY_CORE_DIR . 'assets/js/edrone-newsletter.js';

    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'meditrendy-edrone-newsletter',
            MEDITRENDY_CORE_URL . 'assets/css/edrone-newsletter.css',
            array(),
            filemtime( $css_path )
        );
    }

    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'meditrendy-edrone-newsletter',
            MEDITRENDY_CORE_URL . 'assets/js/edrone-newsletter.js',
            array(),
            filemtime( $js_path ),
            true
        );

        wp_localize_script(
            'meditrendy-edrone-newsletter',
            'MeditrendyEdroneNewsletter',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'meditrendy_edrone_newsletter' ),
            )
        );
    }
}

function meditrendy_edrone_newsletter_should_enqueue_assets() {
    if ( is_admin() ) {
        return false;
    }

    if ( is_singular() ) {
        $post = get_post();

        if ( $post && has_shortcode( $post->post_content, 'meditrendy_edrone_newsletter' ) ) {
            return true;
        }
    }

    return (bool) apply_filters( 'meditrendy_edrone_newsletter_should_enqueue_assets', false );
}

function meditrendy_edrone_newsletter_maybe_enqueue_assets() {
    if ( meditrendy_edrone_newsletter_should_enqueue_assets() ) {
        meditrendy_edrone_newsletter_enqueue_assets();
    }
}
add_action( 'wp_enqueue_scripts', 'meditrendy_edrone_newsletter_maybe_enqueue_assets' );

function meditrendy_edrone_newsletter_shortcode( $atts ) {
    $phone_example = meditrendy_edrone_newsletter_phone_example();
    $atts = shortcode_atts(
        array(
            'app_id'      => '',
            'tag'         => '',
            'title'       => meditrendy_edrone_newsletter_translate( 'Newsletter' ),
            'description' => meditrendy_edrone_newsletter_translate( 'Receive news and offers by email.' ),
            'button'      => meditrendy_edrone_newsletter_translate( 'Subscribe' ),
            'consent'     => meditrendy_edrone_newsletter_translate( 'I agree to receive newsletters and special offers by email.' ),
            'class'       => '',
        ),
        $atts,
        'meditrendy_edrone_newsletter'
    );

    meditrendy_edrone_newsletter_enqueue_assets();

    $app_id  = meditrendy_edrone_newsletter_app_id( $atts['app_id'] );
    $tag     = meditrendy_edrone_newsletter_customer_tag( $atts['tag'] );
    $classes = trim( 'mt-edrone-newsletter ' . sanitize_html_class( $atts['class'] ) );

    ob_start();
    ?>
    <form class="<?php echo esc_attr( $classes ); ?>" data-mt-edrone-newsletter>
        <?php if ( $atts['title'] ) : ?>
            <h2 class="mt-edrone-newsletter-title"><?php echo esc_html( $atts['title'] ); ?></h2>
        <?php endif; ?>

        <?php if ( $atts['description'] ) : ?>
            <p class="mt-edrone-newsletter-description"><?php echo esc_html( $atts['description'] ); ?></p>
        <?php endif; ?>

        <input type="hidden" name="action" value="meditrendy_edrone_newsletter_subscribe">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'meditrendy_edrone_newsletter' ) ); ?>">
        <input type="hidden" name="tag" value="<?php echo esc_attr( $tag ); ?>">
        <input type="hidden" name="app_id" value="<?php echo esc_attr( $app_id ); ?>">
        <input class="mt-edrone-newsletter-trap" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">

        <div class="mt-edrone-newsletter-fields">
            <label class="mt-edrone-newsletter-field">
                <span><?php echo esc_html( meditrendy_edrone_newsletter_translate( 'First name' ) ); ?></span>
                <input type="text" name="first_name" autocomplete="given-name" required>
            </label>

            <label class="mt-edrone-newsletter-field">
                <span><?php echo esc_html( meditrendy_edrone_newsletter_translate( 'Email' ) ); ?></span>
                <input type="email" name="email" autocomplete="email" required>
            </label>

            <label class="mt-edrone-newsletter-field">
                <span><?php echo esc_html( meditrendy_edrone_newsletter_translate( 'Phone number (optional)' ) ); ?></span>
                <input type="tel" name="phone" autocomplete="tel" inputmode="tel" placeholder="<?php echo esc_attr( $phone_example ); ?>" pattern="\+[0-9\s().-]{7,20}" title="<?php echo esc_attr( sprintf( meditrendy_edrone_newsletter_translate( 'Use the international format, for example %s.' ), $phone_example ) ); ?>" aria-describedby="mt-edrone-phone-format">
            </label>

            <button class="mt-edrone-newsletter-button" type="submit"><?php echo esc_html( $atts['button'] ); ?></button>
        </div>

        <label class="mt-edrone-newsletter-consent">
            <input type="checkbox" name="consent" value="1" required>
            <span><?php echo esc_html( $atts['consent'] ); ?></span>
        </label>

        <?php if ( ! $app_id ) : ?>
            <p class="mt-edrone-newsletter-config"><?php echo esc_html( meditrendy_edrone_newsletter_translate( 'Edrone App ID is missing.' ) ); ?></p>
        <?php endif; ?>

        <p class="mt-edrone-newsletter-message" data-mt-edrone-newsletter-message aria-live="polite"></p>
    </form>
    <?php

    return ob_get_clean();
}
add_shortcode( 'meditrendy_edrone_newsletter', 'meditrendy_edrone_newsletter_shortcode' );

function meditrendy_edrone_newsletter_subscribe() {
    check_ajax_referer( 'meditrendy_edrone_newsletter', 'nonce' );

    if ( ! empty( $_POST['website'] ) ) {
        wp_send_json_success( array( 'message' => meditrendy_edrone_newsletter_translate( 'Thank you!' ) ) );
    }

    $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $phone      = isset( $_POST['phone'] ) ? preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
    $tag        = isset( $_POST['tag'] ) ? meditrendy_edrone_newsletter_customer_tag( wp_unslash( $_POST['tag'] ) ) : meditrendy_edrone_newsletter_customer_tag();
    $app_id     = isset( $_POST['app_id'] ) ? meditrendy_edrone_newsletter_app_id( wp_unslash( $_POST['app_id'] ) ) : meditrendy_edrone_newsletter_app_id();
    $consent    = ! empty( $_POST['consent'] );

    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => meditrendy_edrone_newsletter_translate( 'Enter a valid email address.' ) ), 400 );
    }

    if ( ! $first_name ) {
        wp_send_json_error( array( 'message' => meditrendy_edrone_newsletter_translate( 'Enter your first name.' ) ), 400 );
    }

    if ( $phone && ! preg_match( '/^\+[1-9][0-9]{6,14}$/', $phone ) ) {
        wp_send_json_error(
            array(
                'message' => sprintf(
                    meditrendy_edrone_newsletter_translate( 'Enter your phone number with a country code, for example %s.' ),
                    meditrendy_edrone_newsletter_phone_example()
                ),
            ),
            400
        );
    }

    if ( ! $consent ) {
        wp_send_json_error( array( 'message' => meditrendy_edrone_newsletter_translate( 'Please accept the consent.' ) ), 400 );
    }

    if ( ! $app_id ) {
        wp_send_json_error( array( 'message' => meditrendy_edrone_newsletter_translate( 'Edrone App ID is not configured.' ) ), 400 );
    }

    $request_body = array(
        'app_id'            => $app_id,
        'action_type'       => 'subscribe',
        'email'             => $email,
        'first_name'        => $first_name,
        'subscriber_status' => '1',
        'customer_tags'     => $tag,
        'sender_type'       => 'server',
    );

    if ( $phone ) {
        $request_body['phone'] = $phone;
    }

    $response = wp_remote_post(
        'https://api.edrone.me/trace',
        array(
            'timeout' => 8,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ),
            'body'    => http_build_query( $request_body, '', '&' ),
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => meditrendy_edrone_newsletter_translate( 'Unable to subscribe. Please try again.' ) ), 502 );
    }

    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code < 200 || $status_code >= 300 ) {
        wp_send_json_error( array( 'message' => meditrendy_edrone_newsletter_translate( 'Unable to subscribe. Please try again.' ) ), 502 );
    }

    wp_send_json_success( array( 'message' => meditrendy_edrone_newsletter_translate( 'Thank you! Your subscription has been registered.' ) ) );
}
add_action( 'wp_ajax_meditrendy_edrone_newsletter_subscribe', 'meditrendy_edrone_newsletter_subscribe' );
add_action( 'wp_ajax_nopriv_meditrendy_edrone_newsletter_subscribe', 'meditrendy_edrone_newsletter_subscribe' );
