<?php
if (!defined('ABSPATH')) exit;

function meditrendy_module_settings_defaults() {
    return [
        'cart_enabled' => 1,
    ];
}

function meditrendy_module_settings() {
    $settings = get_option('meditrendy_module_settings', []);

    return wp_parse_args(is_array($settings) ? $settings : [], meditrendy_module_settings_defaults());
}

function meditrendy_cart_module_enabled() {
    $settings = meditrendy_module_settings();

    return !empty($settings['cart_enabled']);
}

function meditrendy_module_settings_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_module_settings_sanitize($input) {
    $input = is_array($input) ? $input : [];

    return [
        'cart_enabled' => !empty($input['cart_enabled']) ? 1 : 0,
    ];
}

function meditrendy_register_module_settings() {
    register_setting(
        'meditrendy_module_settings',
        'meditrendy_module_settings',
        [
            'sanitize_callback' => 'meditrendy_module_settings_sanitize',
            'default'           => meditrendy_module_settings_defaults(),
        ]
    );
}
add_action('admin_init', 'meditrendy_register_module_settings');

add_filter('option_page_capability_meditrendy_module_settings', 'meditrendy_module_settings_capability');

function meditrendy_module_settings_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        'Modules',
        'Modules',
        meditrendy_module_settings_capability(),
        'meditrendy-modules',
        'meditrendy_render_module_settings_page'
    );
}
add_action('admin_menu', 'meditrendy_module_settings_admin_menu', 15);

function meditrendy_render_module_settings_page() {
    if (!current_user_can(meditrendy_module_settings_capability())) {
        return;
    }

    $settings = meditrendy_module_settings();
    ?>
    <div class="wrap">
        <h1>Meditrendy modules</h1>
        <form method="post" action="options.php">
            <?php settings_fields('meditrendy_module_settings'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Cart module</th>
                    <td>
                        <label>
                            <input type="checkbox" name="meditrendy_module_settings[cart_enabled]" value="1" <?php checked(!empty($settings['cart_enabled'])); ?>>
                            Enabled
                        </label>
                        <p class="description">Controls the custom side cart shell, AJAX add/update handlers, cart badge assets, and side-cart styling.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save modules'); ?>
        </form>
    </div>
    <?php
}
