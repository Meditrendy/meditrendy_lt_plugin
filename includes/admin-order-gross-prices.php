<?php
if (!defined('ABSPATH')) exit;

function meditrendy_admin_order_gross_prices_order_id() {
    if (!is_admin()) {
        return 0;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen ? (string) $screen->id : '';

    if (!in_array($screen_id, ['shop_order', 'woocommerce_page_wc-orders'], true)) {
        return 0;
    }

    if (!empty($_GET['post'])) {
        return absint($_GET['post']);
    }

    if (!empty($_GET['id'])) {
        return absint($_GET['id']);
    }

    return 0;
}

function meditrendy_admin_order_gross_prices_amount($order, $amount) {
    return wc_price((float) $amount, ['currency' => $order->get_currency()]);
}

function meditrendy_admin_order_gross_prices_line_tax($item, $type = 'total') {
    if (!is_callable([$item, 'get_taxes'])) {
        return 0.0;
    }

    $taxes = $item->get_taxes();
    $tax_values = isset($taxes[$type]) && is_array($taxes[$type]) ? $taxes[$type] : [];

    return (float) array_sum(array_map('floatval', $tax_values));
}

function meditrendy_admin_order_gross_prices_data($order) {
    $data = [
        'items' => [],
        'summary' => [
            'subtotal' => meditrendy_admin_order_gross_prices_amount($order, 0),
            'discount' => meditrendy_admin_order_gross_prices_amount($order, 0),
            'fees' => meditrendy_admin_order_gross_prices_amount($order, 0),
            'shipping' => meditrendy_admin_order_gross_prices_amount($order, 0),
        ],
        'labels' => [
            'subtotal' => html_entity_decode(__('Items Subtotal:', 'woocommerce'), ENT_QUOTES, 'UTF-8'),
            'discount' => html_entity_decode(__('Discount:', 'woocommerce'), ENT_QUOTES, 'UTF-8'),
            'fees' => html_entity_decode(__('Fees:', 'woocommerce'), ENT_QUOTES, 'UTF-8'),
            'shipping' => html_entity_decode(__('Shipping:', 'woocommerce'), ENT_QUOTES, 'UTF-8'),
        ],
        'taxLabels' => [],
    ];

    $items_subtotal_gross = 0.0;
    $fees_gross = 0.0;

    foreach ($order->get_items('line_item') as $item_id => $item) {
        $quantity = max(1, (int) $item->get_quantity());
        $line_subtotal_gross = (float) $item->get_subtotal() + meditrendy_admin_order_gross_prices_line_tax($item, 'subtotal');
        $line_total_gross = (float) $item->get_total() + meditrendy_admin_order_gross_prices_line_tax($item, 'total');
        $unit_gross = $line_subtotal_gross / $quantity;
        $items_subtotal_gross += $line_subtotal_gross;

        $data['items'][(string) $item_id] = [
            'itemCost' => meditrendy_admin_order_gross_prices_amount($order, $unit_gross),
            'lineCost' => meditrendy_admin_order_gross_prices_amount($order, $line_total_gross),
        ];
    }

    foreach ($order->get_items('fee') as $item_id => $item) {
        $fee_gross = (float) $item->get_total() + meditrendy_admin_order_gross_prices_line_tax($item, 'total');
        $fees_gross += $fee_gross;

        $data['items'][(string) $item_id] = [
            'lineCost' => meditrendy_admin_order_gross_prices_amount($order, $fee_gross),
        ];
    }

    foreach ($order->get_items('shipping') as $item_id => $item) {
        $shipping_gross = (float) $item->get_total() + meditrendy_admin_order_gross_prices_line_tax($item, 'total');

        $data['items'][(string) $item_id] = [
            'lineCost' => meditrendy_admin_order_gross_prices_amount($order, $shipping_gross),
        ];
    }

    foreach ($order->get_tax_totals() as $tax_total) {
        $data['taxLabels'][] = html_entity_decode($tax_total->label . ':', ENT_QUOTES, 'UTF-8');
    }

    $data['summary']['subtotal'] = meditrendy_admin_order_gross_prices_amount($order, $items_subtotal_gross);
    $data['summary']['discount'] = meditrendy_admin_order_gross_prices_amount($order, (float) $order->get_discount_total() + (float) $order->get_discount_tax());
    $data['summary']['fees'] = meditrendy_admin_order_gross_prices_amount($order, $fees_gross);
    $data['summary']['shipping'] = meditrendy_admin_order_gross_prices_amount($order, (float) $order->get_shipping_total() + (float) $order->get_shipping_tax());

    return $data;
}

function meditrendy_admin_order_gross_prices_footer() {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order_id = meditrendy_admin_order_gross_prices_order_id();
    $order = $order_id ? wc_get_order($order_id) : false;

    if (!$order instanceof WC_Order || !wc_tax_enabled()) {
        return;
    }

    $data = meditrendy_admin_order_gross_prices_data($order);
    ?>
    <style>
        .woocommerce_order_items .line_tax {
            display: none !important;
        }
    </style>
    <script>
        window.meditrendyAdminOrderGrossPrices = <?php echo wp_json_encode($data); ?>;
        (function () {
            const data = window.meditrendyAdminOrderGrossPrices || {};
            const itemData = data.items || {};
            const summary = data.summary || {};
            const labels = data.labels || {};
            const taxLabels = data.taxLabels || [];

            const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim();

            document.querySelectorAll('.woocommerce_order_items tr[data-order_item_id]').forEach((row) => {
                const id = row.getAttribute('data-order_item_id');
                const item = itemData[id];

                if (!item) {
                    return;
                }

                const itemCost = row.querySelector('td.item_cost > .view');
                const lineCost = row.querySelector('td.line_cost > .view');

                if (item.itemCost && itemCost) {
                    itemCost.innerHTML = item.itemCost;
                }

                if (item.lineCost && lineCost) {
                    lineCost.innerHTML = item.lineCost;
                }
            });

            document.querySelectorAll('.wc-order-totals-items .wc-order-totals tr').forEach((row) => {
                const label = normalize(row.querySelector('.label') ? row.querySelector('.label').textContent : '');
                const total = row.querySelector('.total');

                if (!label || !total) {
                    return;
                }

                if (label === normalize(labels.subtotal)) {
                    total.innerHTML = summary.subtotal || total.innerHTML;
                } else if (label === normalize(labels.discount)) {
                    total.innerHTML = summary.discount ? '-' + summary.discount : total.innerHTML;
                } else if (label === normalize(labels.fees)) {
                    total.innerHTML = summary.fees || total.innerHTML;
                } else if (label === normalize(labels.shipping)) {
                    total.innerHTML = summary.shipping || total.innerHTML;
                } else if (taxLabels.some((taxLabel) => label === normalize(taxLabel))) {
                    row.style.display = 'none';
                }
            });
        })();
    </script>
    <?php
}
add_action('admin_footer', 'meditrendy_admin_order_gross_prices_footer', 20);
