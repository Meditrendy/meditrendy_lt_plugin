<?php
/**
 * Restore Omniva's block-checkout AJAX handlers when WooCommerce does not
 * initialize the Omniva block integration during an admin-ajax.php request.
 *
 * Omniva Shipping 1.20.11 registers these handlers from
 * Omnivalt_Blocks_Integration::initialize(). With newer WooCommerce versions,
 * that integration is initialized for the checkout page but not necessarily
 * for the separate AJAX request used to load terminal data. WordPress then
 * returns "0" and the parcel-machine selector remains empty.
 *
 * The fallback is registered only for the two affected requests and only when
 * the Omniva plugin has not already registered its own handler.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the missing Omniva AJAX callback just before WordPress dispatches it.
 */
function meditrendy_core_register_omniva_blocks_ajax_fallback() {
    if (!wp_doing_ajax()) {
        return;
    }

    $action = isset($_REQUEST['action'])
        ? sanitize_key(wp_unslash($_REQUEST['action']))
        : '';

    $callbacks = [
        'omnivalt_get_dynamic_data' => 'get_dynamic_data_callback',
        'omnivalt_get_terminals' => 'get_terminals_callback',
    ];

    if (!isset($callbacks[$action])) {
        return;
    }

    $hook = is_user_logged_in()
        ? 'wp_ajax_' . $action
        : 'wp_ajax_nopriv_' . $action;

    if (has_action($hook)) {
        return;
    }

    if (!class_exists('Omnivalt_Blocks_Integration')) {
        $integration_file = defined('OMNIVALT_DIR')
            ? OMNIVALT_DIR . 'core/wc-blocks/class-blocks-integration.php'
            : '';

        if (
            !$integration_file
            || !interface_exists('Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface')
            || !is_readable($integration_file)
        ) {
            return;
        }

        require_once $integration_file;
    }

    $integration = new Omnivalt_Blocks_Integration();
    add_action($hook, [$integration, $callbacks[$action]]);
}
add_action('admin_init', 'meditrendy_core_register_omniva_blocks_ajax_fallback', 99);
