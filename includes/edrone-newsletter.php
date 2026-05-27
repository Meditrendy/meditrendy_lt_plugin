<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function meditrendy_edrone_newsletter_app_id( $shortcode_app_id = '' ) {
    $app_id = $shortcode_app_id ?: ( defined( 'MEDITRENDY_EDRONE_APP_ID' ) ? MEDITRENDY_EDRONE_APP_ID : '' );

    return apply_filters( 'meditrendy_edrone_newsletter_app_id', sanitize_text_field( $app_id ) );
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
add_action( 'wp_enqueue_scripts', 'meditrendy_edrone_newsletter_enqueue_assets' );

function meditrendy_edrone_newsletter_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'app_id'      => '',
            'tag'         => 'Newsletter Footer',
            'title'       => 'Naujienlaiškis',
            'description' => 'Gaukite naujienas ir pasiūlymus el. paštu.',
            'button'      => 'Prenumeruoti',
            'consent'     => 'Sutinku gauti naujienlaiškius ir specialius pasiūlymus el. paštu.',
            'show_name'   => 'false',
            'class'       => '',
        ),
        $atts,
        'meditrendy_edrone_newsletter'
    );

    meditrendy_edrone_newsletter_enqueue_assets();

    $app_id    = meditrendy_edrone_newsletter_app_id( $atts['app_id'] );
    $show_name = filter_var( $atts['show_name'], FILTER_VALIDATE_BOOLEAN );
    $classes   = trim( 'mt-edrone-newsletter ' . sanitize_html_class( $atts['class'] ) );

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
        <input type="hidden" name="tag" value="<?php echo esc_attr( $atts['tag'] ); ?>">
        <input type="hidden" name="app_id" value="<?php echo esc_attr( $app_id ); ?>">
        <input class="mt-edrone-newsletter-trap" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">

        <div class="mt-edrone-newsletter-fields">
            <?php if ( $show_name ) : ?>
                <label class="mt-edrone-newsletter-field">
                    <span>Vardas</span>
                    <input type="text" name="first_name" autocomplete="given-name">
                </label>
            <?php endif; ?>

            <label class="mt-edrone-newsletter-field">
                <span>El. paštas</span>
                <input type="email" name="email" autocomplete="email" required>
            </label>

            <button class="mt-edrone-newsletter-button" type="submit"><?php echo esc_html( $atts['button'] ); ?></button>
        </div>

        <label class="mt-edrone-newsletter-consent">
            <input type="checkbox" name="consent" value="1" required>
            <span><?php echo esc_html( $atts['consent'] ); ?></span>
        </label>

        <?php if ( ! $app_id ) : ?>
            <p class="mt-edrone-newsletter-config">Trūksta edrone App ID.</p>
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
        wp_send_json_success( array( 'message' => 'Ačiū!' ) );
    }

    $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $tag        = isset( $_POST['tag'] ) ? sanitize_text_field( wp_unslash( $_POST['tag'] ) ) : 'Newsletter Footer';
    $app_id     = isset( $_POST['app_id'] ) ? meditrendy_edrone_newsletter_app_id( wp_unslash( $_POST['app_id'] ) ) : meditrendy_edrone_newsletter_app_id();
    $consent    = ! empty( $_POST['consent'] );

    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Įveskite teisingą el. pašto adresą.' ), 400 );
    }

    if ( ! $consent ) {
        wp_send_json_error( array( 'message' => 'Pažymėkite sutikimą.' ), 400 );
    }

    if ( ! $app_id ) {
        wp_send_json_error( array( 'message' => 'edrone App ID dar nesukonfigūruotas.' ), 400 );
    }

    $response = wp_remote_post(
        'https://api.edrone.me/trace',
        array(
            'timeout' => 8,
            'body'    => array(
                'app_id'            => $app_id,
                'action_type'       => 'subscribe',
                'email'             => $email,
                'first_name'        => $first_name,
                'subscriber_status' => '1',
                'customer_tags'     => $tag,
                'sender_type'       => 'server',
            ),
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'Nepavyko užsiprenumeruoti. Pabandykite dar kartą.' ), 502 );
    }

    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code < 200 || $status_code >= 300 ) {
        wp_send_json_error( array( 'message' => 'Nepavyko užsiprenumeruoti. Pabandykite dar kartą.' ), 502 );
    }

    wp_send_json_success( array( 'message' => 'Ačiū! Prenumerata užregistruota.' ) );
}
add_action( 'wp_ajax_meditrendy_edrone_newsletter_subscribe', 'meditrendy_edrone_newsletter_subscribe' );
add_action( 'wp_ajax_nopriv_meditrendy_edrone_newsletter_subscribe', 'meditrendy_edrone_newsletter_subscribe' );
