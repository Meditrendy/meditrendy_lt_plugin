<?php
if (!defined('ABSPATH')) exit;

/**
 * Paysera POS order synchronization.
 *
 * Creates Paysera POS orders only after WooCommerce has a confirmed online payment.
 */

define('MEDITRENDY_PAYSERA_POS_OPTION', 'meditrendy_paysera_pos_sync');
define('MEDITRENDY_PAYSERA_POS_ACTION', 'meditrendy_paysera_pos_sync_order');
define('MEDITRENDY_PAYSERA_POS_GROUP', 'meditrendy-paysera-pos');
define('MEDITRENDY_PAYSERA_POS_LOG_SOURCE', 'meditrendy-paysera-pos');

function meditrendy_paysera_pos_defaults() {
    return [
        'enabled'                 => 0,
        'environment'             => 'production',
        'bearer_token'            => '',
        'order_number_prefix'     => 'WC-',
        'allowed_payment_methods' => ['paysera'],
        'payment_method'          => 'bankTransfer',
        'language_code'           => 'lt',
        'tax_classifier_21'       => 'PVM1',
        'log_level'               => 'error',
        'max_attempts'            => 5,
    ];
}

function meditrendy_paysera_pos_settings() {
    $settings = get_option(MEDITRENDY_PAYSERA_POS_OPTION, []);

    if (!is_array($settings)) {
        $settings = [];
    }

    $settings = wp_parse_args($settings, meditrendy_paysera_pos_defaults());

    if (!is_array($settings['allowed_payment_methods'])) {
        $settings['allowed_payment_methods'] = preg_split('/[\s,]+/', (string) $settings['allowed_payment_methods']);
    }

    $settings['allowed_payment_methods'] = array_values(array_filter(array_map('sanitize_key', $settings['allowed_payment_methods'])));
    $settings['max_attempts'] = max(1, absint($settings['max_attempts']));

    return $settings;
}

function meditrendy_paysera_pos_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_paysera_pos_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $defaults = meditrendy_paysera_pos_defaults();
    $old = meditrendy_paysera_pos_settings();

    $allowed_methods = isset($input['allowed_payment_methods'])
        ? preg_split('/[\s,]+/', (string) $input['allowed_payment_methods'])
        : $defaults['allowed_payment_methods'];

    $token = isset($input['bearer_token']) ? trim((string) wp_unslash($input['bearer_token'])) : '';
    if ($token === '********' || ($token === '' && !empty($old['bearer_token']))) {
        $token = $old['bearer_token'];
    }

    return [
        'enabled'                 => !empty($input['enabled']) ? 1 : 0,
        'environment'             => in_array(($input['environment'] ?? ''), ['production', 'demo'], true) ? $input['environment'] : 'production',
        'bearer_token'            => sanitize_text_field($token),
        'order_number_prefix'     => sanitize_text_field($input['order_number_prefix'] ?? $defaults['order_number_prefix']),
        'allowed_payment_methods' => array_values(array_filter(array_map('sanitize_key', $allowed_methods))),
        'payment_method'          => in_array(($input['payment_method'] ?? ''), ['bankTransfer', 'wolt', 'bolt'], true) ? $input['payment_method'] : 'bankTransfer',
        'language_code'           => preg_match('/^[a-z]{2}$/', (string) ($input['language_code'] ?? '')) ? (string) $input['language_code'] : 'lt',
        'tax_classifier_21'       => sanitize_text_field($input['tax_classifier_21'] ?? $defaults['tax_classifier_21']),
        'log_level'               => in_array(($input['log_level'] ?? ''), ['none', 'error', 'info'], true) ? $input['log_level'] : 'error',
        'max_attempts'            => max(1, absint($input['max_attempts'] ?? $defaults['max_attempts'])),
    ];
}

function meditrendy_paysera_pos_register_settings() {
    register_setting(
        'meditrendy_paysera_pos_sync',
        MEDITRENDY_PAYSERA_POS_OPTION,
        [
            'sanitize_callback' => 'meditrendy_paysera_pos_sanitize',
            'default'           => meditrendy_paysera_pos_defaults(),
        ]
    );
}
add_action('admin_init', 'meditrendy_paysera_pos_register_settings');

add_filter('option_page_capability_meditrendy_paysera_pos_sync', 'meditrendy_paysera_pos_capability');

function meditrendy_paysera_pos_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        'Paysera POS sync',
        'Paysera POS sync',
        meditrendy_paysera_pos_capability(),
        'meditrendy-paysera-pos-sync',
        'meditrendy_paysera_pos_render_settings_page'
    );
}
add_action('admin_menu', 'meditrendy_paysera_pos_admin_menu', 40);

function meditrendy_paysera_pos_render_settings_page() {
    if (!current_user_can(meditrendy_paysera_pos_capability())) {
        return;
    }

    $settings = meditrendy_paysera_pos_settings();
    $allowed = implode(', ', $settings['allowed_payment_methods']);
    ?>
    <div class="wrap">
        <h1>Paysera POS sync</h1>
        <form method="post" action="options.php">
            <?php settings_fields('meditrendy_paysera_pos_sync'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Enabled</th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> Create POS orders after confirmed online payment</label></td>
                </tr>
                <tr>
                    <th scope="row">Environment</th>
                    <td>
                        <select name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[environment]">
                            <option value="production" <?php selected($settings['environment'], 'production'); ?>>Production</option>
                            <option value="demo" <?php selected($settings['environment'], 'demo'); ?>>Demo</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Bearer token</th>
                    <td>
                        <input class="regular-text" type="password" name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[bearer_token]" value="<?php echo esc_attr($settings['bearer_token'] ? '********' : ''); ?>" autocomplete="new-password">
                        <p class="description">Paste a new token to replace the stored value. Tokens are never written to logs.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Order number prefix</th>
                    <td><input class="regular-text" type="text" name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[order_number_prefix]" value="<?php echo esc_attr($settings['order_number_prefix']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Allowed payment method IDs</th>
                    <td>
                        <input class="regular-text" type="text" name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[allowed_payment_methods]" value="<?php echo esc_attr($allowed); ?>">
                        <p class="description">Comma-separated WooCommerce gateway IDs. Default: paysera.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">POS payment method</th>
                    <td>
                        <select name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[payment_method]">
                            <option value="bankTransfer" <?php selected($settings['payment_method'], 'bankTransfer'); ?>>bankTransfer</option>
                            <option value="wolt" <?php selected($settings['payment_method'], 'wolt'); ?>>wolt</option>
                            <option value="bolt" <?php selected($settings['payment_method'], 'bolt'); ?>>bolt</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Language code</th>
                    <td><input class="small-text" type="text" name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[language_code]" value="<?php echo esc_attr($settings['language_code']); ?>" maxlength="2"></td>
                </tr>
                <tr>
                    <th scope="row">21% VAT classifier</th>
                    <td><input class="regular-text" type="text" name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[tax_classifier_21]" value="<?php echo esc_attr($settings['tax_classifier_21']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Log level</th>
                    <td>
                        <select name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[log_level]">
                            <option value="none" <?php selected($settings['log_level'], 'none'); ?>>None</option>
                            <option value="error" <?php selected($settings['log_level'], 'error'); ?>>Errors</option>
                            <option value="info" <?php selected($settings['log_level'], 'info'); ?>>Info</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Max attempts</th>
                    <td><input class="small-text" type="number" min="1" name="<?php echo esc_attr(MEDITRENDY_PAYSERA_POS_OPTION); ?>[max_attempts]" value="<?php echo esc_attr($settings['max_attempts']); ?>"></td>
                </tr>
            </table>
            <?php submit_button('Save Paysera POS sync'); ?>
        </form>
    </div>
    <?php
}

function meditrendy_paysera_pos_logger() {
    return function_exists('wc_get_logger') ? wc_get_logger() : null;
}

function meditrendy_paysera_pos_log($level, $message, array $context = []) {
    $settings = meditrendy_paysera_pos_settings();

    if ($settings['log_level'] === 'none') {
        return;
    }

    if ($level === 'info' && $settings['log_level'] !== 'info') {
        return;
    }

    unset($context['bearer_token'], $context['token'], $context['payload']['customer']);
    $context['source'] = MEDITRENDY_PAYSERA_POS_LOG_SOURCE;

    $logger = meditrendy_paysera_pos_logger();
    if ($logger) {
        $logger->log($level, $message, $context);
    }
}

function meditrendy_paysera_pos_base_url() {
    $settings = meditrendy_paysera_pos_settings();

    return $settings['environment'] === 'demo'
        ? 'https://pos-demo.paysera/eapi/v1/'
        : 'https://pos.paysera.com/eapi/v1/';
}

function meditrendy_paysera_pos_order_number(WC_Order $order) {
    $settings = meditrendy_paysera_pos_settings();

    return $settings['order_number_prefix'] . $order->get_id();
}

function meditrendy_paysera_pos_meta_keys() {
    return [
        'status'       => '_meditrendy_paysera_pos_status',
        'id'           => '_meditrendy_paysera_pos_order_id',
        'number'       => '_meditrendy_paysera_pos_order_number',
        'payload_hash' => '_meditrendy_paysera_pos_payload_hash',
        'attempts'     => '_meditrendy_paysera_pos_attempts',
        'last_error'   => '_meditrendy_paysera_pos_last_error',
        'last_http'    => '_meditrendy_paysera_pos_last_http_status',
        'last_synced'  => '_meditrendy_paysera_pos_last_synced_at',
    ];
}

function meditrendy_paysera_pos_set_sync_meta(WC_Order $order, array $values) {
    $keys = meditrendy_paysera_pos_meta_keys();

    foreach ($values as $key => $value) {
        if (isset($keys[$key])) {
            $order->update_meta_data($keys[$key], $value);
        }
    }

    $order->save();
}

function meditrendy_paysera_pos_is_paid_online(WC_Order $order) {
    $settings = meditrendy_paysera_pos_settings();
    $method = sanitize_key($order->get_payment_method());

    if (!in_array($method, $settings['allowed_payment_methods'], true)) {
        return false;
    }

    if ($method === 'paysera') {
        return $order->get_meta('_paysera_payment_confirmed') === '1';
    }

    return $order->is_paid();
}

function meditrendy_paysera_pos_is_order_eligible(WC_Order $order, &$reason = '') {
    $settings = meditrendy_paysera_pos_settings();
    $keys = meditrendy_paysera_pos_meta_keys();

    if (empty($settings['enabled'])) {
        $reason = 'disabled';
        return false;
    }

    if (empty($settings['bearer_token'])) {
        $reason = 'missing_token';
        return false;
    }

    if ($order->get_currency() !== 'EUR') {
        $reason = 'unsupported_currency';
        return false;
    }

    if (in_array($order->get_status(), ['cancelled', 'failed', 'on-hold', 'pending'], true)) {
        $reason = 'ineligible_status_' . $order->get_status();
        return false;
    }

    if ((float) $order->get_total_refunded() >= (float) $order->get_total()) {
        $reason = 'fully_refunded';
        return false;
    }

    if ($order->get_meta($keys['id'])) {
        $reason = 'already_synced';
        return false;
    }

    if (!meditrendy_paysera_pos_is_paid_online($order)) {
        $reason = 'not_confirmed_online_payment';
        return false;
    }

    if (count($order->get_items('line_item')) < 1) {
        $reason = 'no_product_lines';
        return false;
    }

    $reason = '';
    return true;
}

function meditrendy_paysera_pos_schedule_order($order_id) {
    $order = wc_get_order($order_id);

    if (!$order instanceof WC_Order) {
        return;
    }

    $reason = '';
    if (!meditrendy_paysera_pos_is_order_eligible($order, $reason)) {
        meditrendy_paysera_pos_log('info', 'Paysera POS sync not scheduled: ' . $reason, ['order_id' => $order_id]);
        return;
    }

    if (function_exists('as_has_scheduled_action') && as_has_scheduled_action(MEDITRENDY_PAYSERA_POS_ACTION, ['order_id' => $order_id], MEDITRENDY_PAYSERA_POS_GROUP)) {
        return;
    }

    meditrendy_paysera_pos_set_sync_meta($order, [
        'status' => 'scheduled',
        'number' => meditrendy_paysera_pos_order_number($order),
    ]);

    // Use a dated Action Scheduler job. Async actions depend on a loopback HTTP
    // request, which is not reliable on every hosting environment.
    if (function_exists('as_schedule_single_action')) {
        as_schedule_single_action(time() + 5, MEDITRENDY_PAYSERA_POS_ACTION, ['order_id' => $order_id], MEDITRENDY_PAYSERA_POS_GROUP, true);
    } else {
        wp_schedule_single_event(time() + 60, MEDITRENDY_PAYSERA_POS_ACTION, ['order_id' => $order_id]);
    }
}

function meditrendy_paysera_pos_order_status_changed($order_id, $old_status, $new_status, $order) {
    if ($order instanceof WC_Order) {
        meditrendy_paysera_pos_schedule_order($order_id);
    }
}
add_action('woocommerce_order_status_changed', 'meditrendy_paysera_pos_order_status_changed', 50, 4);

function meditrendy_paysera_pos_payment_complete($order_id) {
    meditrendy_paysera_pos_schedule_order($order_id);
}
add_action('woocommerce_payment_complete', 'meditrendy_paysera_pos_payment_complete', 20);

function meditrendy_paysera_pos_round_minor($amount) {
    return (int) round(((float) $amount) * 100);
}

function meditrendy_paysera_pos_minor_to_decimal($minor) {
    return number_format(((int) $minor) / 100, 2, '.', '');
}

function meditrendy_paysera_pos_datetime_utc($date) {
    if ($date instanceof DateTime) {
        $utc = clone $date;
        $utc->setTimezone(new DateTimeZone('UTC'));
        return $utc->format('Y-m-d\TH:i:s\Z');
    }

    return gmdate('Y-m-d\TH:i:s\Z');
}

function meditrendy_paysera_pos_product_sku(WC_Order_Item_Product $item) {
    $product = $item->get_product();

    if (!$product) {
        return null;
    }

    $sku = $product->get_sku();

    if (!$sku && $product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        $sku = $parent ? $parent->get_sku() : '';
    }

    return $sku ? $sku : null;
}

function meditrendy_paysera_pos_tax_for_item(WC_Order_Item_Product $item, WC_Order $order) {
    $taxes = $item->get_taxes();
    $tax_total = 0.0;
    $tax_rate_id = 0;

    if (!empty($taxes['total']) && is_array($taxes['total'])) {
        foreach ($taxes['total'] as $rate_id => $amount) {
            $amount = (float) $amount;
            if ($amount > 0) {
                $tax_total += $amount;
                $tax_rate_id = (int) $rate_id;
            }
        }
    }

    if ($tax_total <= 0) {
        return null;
    }

    $rate = $tax_rate_id ? (float) WC_Tax::get_rate_percent_value($tax_rate_id) : 0.0;
    if ($rate <= 0) {
        $net = (float) $item->get_total();
        $rate = $net > 0 ? round(($tax_total / $net) * 100, 2) : 0.0;
    }

    $settings = meditrendy_paysera_pos_settings();
    $classifier = abs($rate - 21.0) < 0.01 ? $settings['tax_classifier_21'] : $settings['tax_classifier_21'];

    return [
        'taxRate' => $rate,
        'taxClassifier' => $classifier,
    ];
}

function meditrendy_paysera_pos_position($title, $sku, $qty, $gross_minor, $tax = null) {
    $qty = (float) $qty;
    $unit_minor = $qty > 0 ? (int) round($gross_minor / $qty) : $gross_minor;

    $position = [
        'title' => (string) $title,
        'productSKU' => $sku ?: null,
        'quantity' => $qty,
        'unitPrice' => [
            'regular' => (float) meditrendy_paysera_pos_minor_to_decimal($unit_minor),
        ],
        'measureUnit' => 'unit',
    ];

    if ($tax) {
        $position['tax'] = $tax;
    }

    return $position;
}

function meditrendy_paysera_pos_item_gross_minor(WC_Order_Item_Product $item) {
    return meditrendy_paysera_pos_round_minor((float) $item->get_total() + (float) $item->get_total_tax());
}

function meditrendy_paysera_pos_item_regular_gross_minor(WC_Order_Item_Product $item) {
    $product = $item->get_product();
    $qty = max(1, (float) $item->get_quantity());

    if (!$product) {
        return max(0, meditrendy_paysera_pos_item_gross_minor($item));
    }

    $regular = (float) ($product->get_regular_price() ?: $product->get_price());
    $tax = wc_get_price_including_tax($product, ['price' => $regular, 'qty' => $qty]);

    return max(0, meditrendy_paysera_pos_round_minor($tax));
}

function meditrendy_paysera_pos_allocate_bundle_children($parent, array $children) {
    $parent_gross = meditrendy_paysera_pos_item_gross_minor($parent);
    $child_gross = 0;

    foreach ($children as $child) {
        $child_gross += meditrendy_paysera_pos_item_gross_minor($child);
    }

    if ($child_gross === $parent_gross && $child_gross > 0) {
        return null;
    }

    if ($parent_gross <= 0) {
        return null;
    }

    $weights = [];
    $weight_total = 0;
    foreach ($children as $child) {
        $weight = meditrendy_paysera_pos_item_regular_gross_minor($child);
        $weights[$child->get_id()] = $weight;
        $weight_total += $weight;
    }

    if ($weight_total <= 0) {
        $count = max(1, count($children));
        foreach ($children as $child) {
            $weights[$child->get_id()] = 1;
        }
        $weight_total = $count;
    }

    $allocated = [];
    $fractions = [];
    $allocated_total = 0;
    foreach ($children as $child) {
        $raw = ($parent_gross * $weights[$child->get_id()]) / $weight_total;
        $minor = (int) floor($raw);
        $allocated[$child->get_id()] = $minor;
        $fractions[$child->get_id()] = $raw - $minor;
        $allocated_total += $minor;
    }

    $remainder = $parent_gross - $allocated_total;
    if ($remainder > 0) {
        uasort($fractions, function($a, $b) {
            return $a === $b ? 0 : ($a > $b ? -1 : 1);
        });

        foreach (array_keys($fractions) as $item_id) {
            if ($remainder <= 0) {
                break;
            }
            $allocated[$item_id]++;
            $remainder--;
        }
    }

    return $allocated;
}

function meditrendy_paysera_pos_map_product_positions(WC_Order $order) {
    $items = $order->get_items('line_item');
    $parents = [];
    $children_by_parent = [];
    $normal = [];

    foreach ($items as $item_id => $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }

        $parent_bundle_id = $item->get_meta('_woosb_parent_id', true);
        if ($item->get_meta('_woosb_ids', true)) {
            $parents[(int) $item->get_product_id()] = $item;
            continue;
        }

        if ($parent_bundle_id) {
            $children_by_parent[(int) $parent_bundle_id][] = $item;
            continue;
        }

        $normal[] = $item;
    }

    $positions = [];

    foreach ($children_by_parent as $parent_product_id => $children) {
        $parent = $parents[$parent_product_id] ?? null;
        $allocated = $parent ? meditrendy_paysera_pos_allocate_bundle_children($parent, $children) : null;

        foreach ($children as $child) {
            $gross = is_array($allocated)
                ? (int) ($allocated[$child->get_id()] ?? 0)
                : meditrendy_paysera_pos_item_gross_minor($child);

            if ($gross <= 0) {
                continue;
            }

            $positions[] = meditrendy_paysera_pos_position(
                $child->get_name(),
                meditrendy_paysera_pos_product_sku($child),
                $child->get_quantity(),
                $gross,
                meditrendy_paysera_pos_tax_for_item($child, $order)
            );
        }
    }

    foreach ($normal as $item) {
        $gross = meditrendy_paysera_pos_item_gross_minor($item);

        if ($gross <= 0) {
            continue;
        }

        $positions[] = meditrendy_paysera_pos_position(
            $item->get_name(),
            meditrendy_paysera_pos_product_sku($item),
            $item->get_quantity(),
            $gross,
            meditrendy_paysera_pos_tax_for_item($item, $order)
        );
    }

    return $positions;
}

function meditrendy_paysera_pos_map_shipping_positions(WC_Order $order) {
    $positions = [];

    foreach ($order->get_items('shipping') as $item) {
        $gross = meditrendy_paysera_pos_round_minor((float) $item->get_total() + (float) $item->get_total_tax());

        if ($gross <= 0) {
            continue;
        }

        $positions[] = meditrendy_paysera_pos_position(
            $item->get_name() ?: 'Shipping',
            'SHIPPING',
            1,
            $gross,
            null
        );
    }

    return $positions;
}

function meditrendy_paysera_pos_map_fee_positions(WC_Order $order) {
    $positions = [];

    foreach ($order->get_items('fee') as $item) {
        $gross = meditrendy_paysera_pos_round_minor((float) $item->get_total() + (float) $item->get_total_tax());

        if ($gross <= 0) {
            continue;
        }

        $positions[] = meditrendy_paysera_pos_position(
            $item->get_name() ?: 'Fee',
            'FEE',
            1,
            $gross,
            null
        );
    }

    return $positions;
}

function meditrendy_paysera_pos_customer_payload(WC_Order $order) {
    $email = trim((string) $order->get_billing_email());
    $phone = trim((string) $order->get_billing_phone());
    $customer = [];

    if ($email !== '') {
        $customer['email'] = $email;
    }

    if ($phone !== '') {
        $customer['billingAddress'] = [
            'phone' => $phone,
        ];
    }

    return $customer;
}

function meditrendy_paysera_pos_payload(WC_Order $order) {
    $settings = meditrendy_paysera_pos_settings();
    $positions = array_merge(
        meditrendy_paysera_pos_map_product_positions($order),
        meditrendy_paysera_pos_map_shipping_positions($order),
        meditrendy_paysera_pos_map_fee_positions($order)
    );

    $date = $order->get_date_paid() ?: $order->get_date_created();
    $created = meditrendy_paysera_pos_datetime_utc($date);

    $payload = [
        'number' => meditrendy_paysera_pos_order_number($order),
        'currency' => $order->get_currency(),
        'languageCode' => $settings['language_code'],
        'createdAt' => $created,
        'orderPositions' => $positions,
        'payments' => [
            [
                'paymentMethod' => $settings['payment_method'],
                'amount' => number_format((float) $order->get_total(), 2, '.', ''),
                'advancePayment' => 0,
                'createdAt' => $created,
            ],
        ],
    ];

    $customer = meditrendy_paysera_pos_customer_payload($order);
    if (!empty($customer)) {
        $payload['customer'] = $customer;
    }

    return $payload;
}

function meditrendy_paysera_pos_request($method, $path, $body = null) {
    $settings = meditrendy_paysera_pos_settings();
    $url = meditrendy_paysera_pos_base_url() . ltrim($path, '/');
    $args = [
        'method' => $method,
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $settings['bearer_token'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ];

    if ($body !== null) {
        $args['body'] = wp_json_encode($body);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $data = $raw_body !== '' ? json_decode($raw_body, true) : null;

    return [
        'status' => $status,
        'body' => is_array($data) ? $data : [],
        'raw' => $raw_body,
    ];
}

function meditrendy_paysera_pos_find_remote_order($number) {
    $path = 'orders?numbers[]=' . rawurlencode($number);
    $response = meditrendy_paysera_pos_request('GET', $path);

    if (is_wp_error($response)) {
        return $response;
    }

    if (($response['status'] ?? 0) !== 200) {
        return new WP_Error('paysera_pos_lookup_failed', 'Paysera POS lookup failed', ['status' => $response['status'] ?? 0]);
    }

    $items = $response['body']['data'] ?? [];

    return !empty($items[0]) && is_array($items[0]) ? $items[0] : null;
}

function meditrendy_paysera_pos_mark_synced(WC_Order $order, array $remote, $payload_hash) {
    meditrendy_paysera_pos_set_sync_meta($order, [
        'status' => 'synced',
        'id' => $remote['id'] ?? '',
        'number' => $remote['number'] ?? meditrendy_paysera_pos_order_number($order),
        'payload_hash' => $payload_hash,
        'last_error' => '',
        'last_http' => 200,
        'last_synced' => gmdate('c'),
    ]);

    $order->add_order_note(sprintf('Paysera POS order synced: %s', $remote['number'] ?? meditrendy_paysera_pos_order_number($order)));
}

function meditrendy_paysera_pos_sync_order($order_id) {
    $order = wc_get_order($order_id);

    if (!$order instanceof WC_Order) {
        return;
    }

    $keys = meditrendy_paysera_pos_meta_keys();
    $attempts = absint($order->get_meta($keys['attempts'])) + 1;
    $settings = meditrendy_paysera_pos_settings();
    $reason = '';

    if (!meditrendy_paysera_pos_is_order_eligible($order, $reason)) {
        meditrendy_paysera_pos_set_sync_meta($order, [
            'status' => $reason === 'already_synced' ? 'synced' : 'not_eligible',
            'last_error' => $reason,
            'attempts' => $attempts,
        ]);
        return;
    }

    $payload = meditrendy_paysera_pos_payload($order);
    if (empty($payload['orderPositions'])) {
        meditrendy_paysera_pos_set_sync_meta($order, [
            'status' => 'failed',
            'last_error' => 'no_valid_positions',
            'attempts' => $attempts,
        ]);
        return;
    }

    $payload_hash = hash('sha256', wp_json_encode($payload));
    $number = $payload['number'];

    meditrendy_paysera_pos_set_sync_meta($order, [
        'status' => 'syncing',
        'attempts' => $attempts,
        'number' => $number,
        'payload_hash' => $payload_hash,
    ]);

    $remote = meditrendy_paysera_pos_find_remote_order($number);
    if (is_wp_error($remote)) {
        meditrendy_paysera_pos_fail_or_retry($order, $attempts, $remote->get_error_message(), $remote->get_error_data()['status'] ?? 0);
        return;
    }

    if (is_array($remote)) {
        meditrendy_paysera_pos_mark_synced($order, $remote, $payload_hash);
        return;
    }

    $response = meditrendy_paysera_pos_request('POST', 'orders', $payload);
    if (is_wp_error($response)) {
        $lookup = meditrendy_paysera_pos_find_remote_order($number);
        if (is_array($lookup)) {
            meditrendy_paysera_pos_mark_synced($order, $lookup, $payload_hash);
            return;
        }

        meditrendy_paysera_pos_fail_or_retry($order, $attempts, $response->get_error_message(), 0);
        return;
    }

    $status = (int) ($response['status'] ?? 0);

    if ($status === 201 || $status === 200) {
        meditrendy_paysera_pos_mark_synced($order, $response['body'], $payload_hash);
        return;
    }

    if ($status === 409) {
        $lookup = meditrendy_paysera_pos_find_remote_order($number);
        if (is_array($lookup)) {
            meditrendy_paysera_pos_mark_synced($order, $lookup, $payload_hash);
            return;
        }
    }

    $message = !empty($response['body']['message']) ? $response['body']['message'] : 'Paysera POS request failed';
    meditrendy_paysera_pos_fail_or_retry($order, $attempts, $message, $status);
}
add_action(MEDITRENDY_PAYSERA_POS_ACTION, 'meditrendy_paysera_pos_sync_order');

function meditrendy_paysera_pos_fail_or_retry(WC_Order $order, $attempts, $message, $http_status = 0) {
    $settings = meditrendy_paysera_pos_settings();
    $final = $attempts >= $settings['max_attempts'];

    meditrendy_paysera_pos_set_sync_meta($order, [
        'status' => $final ? 'failed' : 'scheduled',
        'last_error' => sanitize_text_field($message),
        'last_http' => absint($http_status),
        'attempts' => $attempts,
    ]);

    meditrendy_paysera_pos_log('error', 'Paysera POS sync failed', [
        'order_id' => $order->get_id(),
        'attempts' => $attempts,
        'http_status' => $http_status,
        'error' => $message,
    ]);

    if ($final) {
        $order->add_order_note('Paysera POS sync failed: ' . sanitize_text_field($message));
        return;
    }

    if (function_exists('as_schedule_single_action')) {
        as_schedule_single_action(time() + min(3600, 300 * $attempts), MEDITRENDY_PAYSERA_POS_ACTION, ['order_id' => $order->get_id()], MEDITRENDY_PAYSERA_POS_GROUP, true);
    } else {
        wp_schedule_single_event(time() + min(3600, 300 * $attempts), MEDITRENDY_PAYSERA_POS_ACTION, ['order_id' => $order->get_id()]);
    }
}

function meditrendy_paysera_pos_admin_box($order) {
    if (!$order instanceof WC_Order) {
        return;
    }

    $keys = meditrendy_paysera_pos_meta_keys();
    $status = $order->get_meta($keys['status']) ?: 'not_scheduled';
    $remote_id = $order->get_meta($keys['id']);
    $number = $order->get_meta($keys['number']) ?: meditrendy_paysera_pos_order_number($order);
    $error = $order->get_meta($keys['last_error']);

    echo '<div class="address meditrendy-paysera-pos-status">';
    echo '<p><strong>Paysera POS sync</strong></p>';
    echo '<p>Status: ' . esc_html($status) . '</p>';
    echo '<p>Number: ' . esc_html($number) . '</p>';
    if ($remote_id) {
        echo '<p>POS ID: ' . esc_html($remote_id) . '</p>';
    }
    if ($error) {
        echo '<p>Last error: ' . esc_html($error) . '</p>';
    }

    $reason = '';
    if ($status !== 'synced' && meditrendy_paysera_pos_is_order_eligible($order, $reason)) {
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=meditrendy_paysera_pos_retry&order_id=' . absint($order->get_id())),
            'meditrendy_paysera_pos_retry_' . absint($order->get_id())
        );
        echo '<p><a class="button" href="' . esc_url($url) . '">Retry Paysera POS sync</a></p>';
    }
    echo '</div>';
}
add_action('woocommerce_admin_order_data_after_order_details', 'meditrendy_paysera_pos_admin_box');

function meditrendy_paysera_pos_retry_action() {
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

    if (!$order_id || !current_user_can(meditrendy_paysera_pos_capability())) {
        wp_die('Unauthorized');
    }

    check_admin_referer('meditrendy_paysera_pos_retry_' . $order_id);

    $order = wc_get_order($order_id);
    if ($order instanceof WC_Order) {
        // Remove a stale async/pending action before scheduling a dated retry.
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(MEDITRENDY_PAYSERA_POS_ACTION, ['order_id' => $order_id], MEDITRENDY_PAYSERA_POS_GROUP);
        } elseif (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(MEDITRENDY_PAYSERA_POS_ACTION, ['order_id' => $order_id]);
        }

        meditrendy_paysera_pos_set_sync_meta($order, [
            'status' => 'scheduled',
            'last_error' => '',
            'attempts' => 0,
        ]);
        meditrendy_paysera_pos_schedule_order($order_id);
    }

    wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post=' . $order_id . '&action=edit'));
    exit;
}
add_action('admin_post_meditrendy_paysera_pos_retry', 'meditrendy_paysera_pos_retry_action');

function meditrendy_paysera_pos_log_gateway_ids_once() {
    if (get_option('meditrendy_paysera_pos_logged_gateways')) {
        return;
    }

    $settings = meditrendy_paysera_pos_settings();
    if ($settings['log_level'] !== 'info') {
        return;
    }

    if (!function_exists('WC') || !WC()->payment_gateways()) {
        return;
    }

    $ids = array_keys(WC()->payment_gateways()->payment_gateways());
    meditrendy_paysera_pos_log('info', 'Active WooCommerce payment gateway IDs: ' . implode(', ', $ids));
    update_option('meditrendy_paysera_pos_logged_gateways', 1, false);
}
add_action('admin_init', 'meditrendy_paysera_pos_log_gateway_ids_once');
