<?php
if (!defined('ABSPATH')) exit;

/**
 * Global settings for the shipping section in the product preset accordion.
 */
function meditrendy_product_accordion_defaults() {
    return [
        'shipping_title'   => '',
        'shipping_content' => '',
    ];
}

function meditrendy_product_accordion_settings() {
    $settings = get_option('meditrendy_product_accordion_settings', []);

    return wp_parse_args(
        is_array($settings) ? $settings : [],
        meditrendy_product_accordion_defaults()
    );
}

function meditrendy_product_accordion_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_product_accordion_sanitize($input) {
    $input = is_array($input) ? $input : [];

    return [
        'shipping_title'   => sanitize_text_field($input['shipping_title'] ?? ''),
        'shipping_content' => wp_kses_post($input['shipping_content'] ?? ''),
    ];
}

function meditrendy_product_accordion_register_settings() {
    register_setting(
        'meditrendy_product_accordion_settings',
        'meditrendy_product_accordion_settings',
        [
            'sanitize_callback' => 'meditrendy_product_accordion_sanitize',
            'default'           => meditrendy_product_accordion_defaults(),
        ]
    );
}
add_action('admin_init', 'meditrendy_product_accordion_register_settings');

add_filter(
    'option_page_capability_meditrendy_product_accordion_settings',
    'meditrendy_product_accordion_capability'
);

function meditrendy_product_accordion_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        'Product accordion',
        'Product accordion',
        meditrendy_product_accordion_capability(),
        'meditrendy-product-accordion',
        'meditrendy_product_accordion_render_admin_page'
    );
}
add_action('admin_menu', 'meditrendy_product_accordion_admin_menu', 18);

function meditrendy_product_accordion_render_admin_page() {
    if (!current_user_can(meditrendy_product_accordion_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    $settings = meditrendy_product_accordion_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Product accordion', 'meditrendy-core'); ?></h1>
        <p><?php echo esc_html__('Configure the global shipping section displayed by the [preset_accordion] shortcode.', 'meditrendy-core'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('meditrendy_product_accordion_settings'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="meditrendy-product-accordion-shipping-title">
                            <?php echo esc_html__('Shipping title', 'meditrendy-core'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="meditrendy-product-accordion-shipping-title"
                            class="regular-text"
                            name="meditrendy_product_accordion_settings[shipping_title]"
                            value="<?php echo esc_attr($settings['shipping_title']); ?>"
                        >
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Shipping information', 'meditrendy-core'); ?>
                    </th>
                    <td>
                        <?php
                        wp_editor(
                            $settings['shipping_content'],
                            'meditrendy_product_accordion_shipping_content',
                            [
                                'textarea_name' => 'meditrendy_product_accordion_settings[shipping_content]',
                                'textarea_rows' => 12,
                                'media_buttons' => false,
                            ]
                        );
                        ?>
                        <p class="description">
                            <?php echo esc_html__('This content is shared by every product using the shortcode.', 'meditrendy-core'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(esc_html__('Save shipping information', 'meditrendy-core')); ?>
        </form>
    </div>
    <?php
}

/**
 * Normalize an ACF post-object or relationship value to a post ID.
 */
function meditrendy_product_accordion_preset_id($preset) {
    if ($preset instanceof WP_Post) {
        return (int) $preset->ID;
    }

    if (is_array($preset)) {
        $preset = reset($preset);

        if ($preset instanceof WP_Post) {
            return (int) $preset->ID;
        }
    }

    return absint($preset);
}

/**
 * Render the product details accordion used in the product-page builder.
 *
 * Details and fabric remain preset-specific ACF fields. Shipping is global and
 * is managed under Meditrendy > Product accordion.
 */
function meditrendy_product_preset_accordion_shortcode() {
    $details_field = [];
    $fabric_field = [];

    if (function_exists('get_field') && function_exists('get_field_object')) {
        $preset_id = meditrendy_product_accordion_preset_id(get_field('preset', get_the_ID()));

        if ($preset_id) {
            $details_field = (array) get_field_object('details_fit', $preset_id);
            $fabric_field = (array) get_field_object('fabric', $preset_id);
        }
    }

    $settings = meditrendy_product_accordion_settings();
    $shipping_title = trim((string) $settings['shipping_title']);
    $shipping_content = trim((string) $settings['shipping_content']);

    if (
        empty($details_field['value'])
        && empty($fabric_field['value'])
        && ($shipping_title === '' || $shipping_content === '')
    ) {
        return '';
    }

    ob_start();
    ?>
    <div class="mt-accordion">
        <?php if (!empty($details_field['value'])): ?>
            <details>
                <summary><?php echo esc_html($details_field['label']); ?></summary>
                <div class="acc-content">
                    <?php echo wp_kses_post($details_field['value']); ?>
                </div>
            </details>
        <?php endif; ?>

        <?php if (!empty($fabric_field['value'])): ?>
            <details>
                <summary><?php echo esc_html($fabric_field['label']); ?></summary>
                <div class="acc-content">
                    <?php echo wp_kses_post($fabric_field['value']); ?>
                </div>
            </details>
        <?php endif; ?>

        <?php if ($shipping_title !== '' && $shipping_content !== ''): ?>
            <details>
                <summary><?php echo esc_html($shipping_title); ?></summary>
                <div class="acc-content">
                    <?php echo wp_kses_post(wpautop($shipping_content)); ?>
                </div>
            </details>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('preset_accordion', 'meditrendy_product_preset_accordion_shortcode');
