<?php

if (!defined('ABSPATH')) exit;

/**
 * WooCommerce Blocks checkout invoice fields.
 *
 * The React checkout uses the Additional Checkout Fields API, not the classic
 * woocommerce_checkout_fields filter.
 */

add_filter('pre_option_woocommerce_checkout_company_field', function() {
    return 'hidden';
});

function meditrendy_register_checkout_invoice_fields() {
    static $registered = false;

    if ($registered) {
        return;
    }

    $is_rest_request = function_exists('wp_is_serving_rest_request') && wp_is_serving_rest_request();

    if (!is_admin() && !wp_doing_ajax() && !$is_rest_request && !did_action('woocommerce_blocks_checkout_enqueue_data')) {
        return;
    }

    if (!function_exists('woocommerce_register_additional_checkout_field')) {
        return;
    }

    $registered = true;

    woocommerce_register_additional_checkout_field([
        'id'                         => 'meditrendy/invoice_required',
        'label'                      => 'Reikia sąskaitos faktūros įmonei',
        'optionalLabel'              => 'Reikia sąskaitos faktūros įmonei',
        'location'                   => 'contact',
        'type'                       => 'checkbox',
        'required'                   => false,
        'index'                      => 10,
        'show_in_order_confirmation' => false,
        'attributes'                 => [
            'data-meditrendy-invoice-toggle' => '1',
        ],
    ]);

    $hidden_until_invoice_is_checked = [
        'customer' => [
            'properties' => [
                'additional_fields' => [
                    'properties' => [
                        'meditrendy/invoice_required' => [
                            'not' => [
                                'anyOf' => [
                                    ['const' => '1'],
                                    ['const' => true],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    woocommerce_register_additional_checkout_field([
        'id'            => 'meditrendy/company_name',
        'label'         => 'Įmonės pavadinimas',
        'optionalLabel' => 'Įmonės pavadinimas',
        'location'      => 'contact',
        'type'          => 'text',
        'required'      => false,
        'hidden'        => $hidden_until_invoice_is_checked,
        'index'         => 20,
        'attributes'    => [
            'autocomplete'                       => 'organization',
            'data-meditrendy-invoice-dependent' => '1',
        ],
    ]);

    woocommerce_register_additional_checkout_field([
        'id'            => 'meditrendy/company_code',
        'label'         => 'PVM mokėtojo kodas',
        'optionalLabel' => 'PVM mokėtojo kodas',
        'location'      => 'contact',
        'type'          => 'text',
        'required'      => false,
        'hidden'        => $hidden_until_invoice_is_checked,
        'index'         => 30,
        'attributes'    => [
            'autocomplete'                       => 'off',
            'data-meditrendy-invoice-dependent' => '1',
        ],
    ]);
}

add_action('woocommerce_blocks_loaded', 'meditrendy_register_checkout_invoice_fields');
add_action('woocommerce_blocks_checkout_enqueue_data', 'meditrendy_register_checkout_invoice_fields', 1);

add_action('wp_enqueue_scripts', function() {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }

    if (function_exists('is_order_received_page') && is_order_received_page()) {
        return;
    }

    $asset_path = MEDITRENDY_CORE_DIR . 'assets/js/checkout-invoice-fields.js';

    wp_enqueue_script(
        'meditrendy-checkout-invoice-fields',
        MEDITRENDY_CORE_URL . 'assets/js/checkout-invoice-fields.js',
        [],
        file_exists($asset_path) ? filemtime($asset_path) : '1.0.0',
        true
    );
});
