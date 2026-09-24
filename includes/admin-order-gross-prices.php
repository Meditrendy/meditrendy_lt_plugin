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
            'feesRaw' => 0,
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
    $data['summary']['feesRaw'] = $fees_gross;
    $data['summary']['shipping'] = meditrendy_admin_order_gross_prices_amount($order, (float) $order->get_shipping_total() + (float) $order->get_shipping_tax());

    return $data;
}

function meditrendy_admin_order_gross_prices_fees_total($order) {
    $fees_gross = 0.0;

    foreach ($order->get_items('fee') as $item) {
        $fees_gross += (float) $item->get_total() + meditrendy_admin_order_gross_prices_line_tax($item, 'total');
    }

    return $fees_gross;
}

function meditrendy_admin_order_gross_prices_preview_columns($columns) {
    if (!isset($columns['total'])) {
        return $columns;
    }

    $gross_columns = [];

    foreach ($columns as $key => $label) {
        $gross_columns[$key === 'total' ? 'gross_total' : $key] = $label;
    }

    return $gross_columns;
}
add_filter('woocommerce_admin_order_preview_line_item_columns', 'meditrendy_admin_order_gross_prices_preview_columns', 10, 1);

function meditrendy_admin_order_gross_prices_refunded_for_item($order, $item_id) {
    $refunded_gross = 0.0;

    foreach ($order->get_refunds() as $refund) {
        foreach ($refund->get_items('line_item') as $refunded_item) {
            if ((int) $refunded_item->get_meta('_refunded_item_id') !== (int) $item_id) {
                continue;
            }

            $refunded_gross += abs((float) $refunded_item->get_total() + (float) $refunded_item->get_total_tax());
        }
    }

    return $refunded_gross;
}

function meditrendy_admin_order_gross_prices_preview_total($html, $item, $item_id, $order) {
    $gross_total = (float) $item->get_total() + (float) $item->get_total_tax();
    $html = meditrendy_admin_order_gross_prices_amount($order, $gross_total);
    $refunded_gross = meditrendy_admin_order_gross_prices_refunded_for_item($order, $item_id);

    if ($refunded_gross > 0) {
        $html .= '<div><small class="refunded">-' . meditrendy_admin_order_gross_prices_amount($order, $refunded_gross) . '</small></div><br/>';
    }

    return $html;
}
add_filter('woocommerce_admin_order_preview_line_item_column_gross_total', 'meditrendy_admin_order_gross_prices_preview_total', 10, 4);

function meditrendy_admin_order_gross_prices_render_fees_total($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);

    if (!$order instanceof WC_Order) {
        return;
    }

    $fees_gross = meditrendy_admin_order_gross_prices_fees_total($order);

    if ($fees_gross <= 0) {
        return;
    }
    ?>
    <tr>
        <td class="label"><?php echo esc_html__('Mokėjimo mokestis:', 'meditrendy-core'); ?></td>
        <td width="1%"></td>
        <td class="total">
            <?php echo wp_kses_post(meditrendy_admin_order_gross_prices_amount($order, $fees_gross)); ?>
        </td>
    </tr>
    <?php
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

        .woocommerce_order_items input.meditrendy-admin-net-price {
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
            const decimalPoint = window.woocommerce_admin ? woocommerce_admin.mon_decimal_point : '.';
            const roundingPrecision = window.woocommerce_admin_meta_boxes ? woocommerce_admin_meta_boxes.rounding_precision : 6;
            let hasFeesRow = false;
            let updatingNativePrices = false;

            const parsePrice = (value) => {
                if (window.accounting && typeof window.accounting.unformat === 'function') {
                    return accounting.unformat(value || 0, decimalPoint);
                }

                const normalized = String(value || 0).replace(/\s/g, '').replace(decimalPoint, '.');
                const parsed = Number.parseFloat(normalized);
                return Number.isFinite(parsed) ? parsed : 0;
            };

            const formatPrice = (value) => {
                if (window.accounting && typeof window.accounting.formatNumber === 'function') {
                    return parseFloat(accounting.formatNumber(value, roundingPrecision, '')).toString().replace('.', decimalPoint);
                }

                return Number(value || 0).toFixed(roundingPrecision).replace(/\.?0+$/, '').replace('.', decimalPoint);
            };

            const priceInputs = (row, context, kind) => {
                const totalSelector = context === 'refund'
                    ? '.refund input.refund_line_total'
                    : '.edit input.' + (kind === 'subtotal' ? 'line_subtotal' : 'line_total');
                const taxSelector = context === 'refund'
                    ? 'td.line_tax .refund input.refund_line_tax'
                    : 'td.line_tax .edit input.' + (kind === 'subtotal' ? 'line_subtotal_tax' : 'line_tax');

                return {
                    total: row.querySelector(totalSelector),
                    taxes: Array.from(row.querySelectorAll(taxSelector)),
                };
            };

            const inputGross = (inputs) => {
                if (!inputs.total) {
                    return null;
                }

                const values = [inputs.total].concat(inputs.taxes);
                const hasValue = values.some((input) => String(input.value || '').trim() !== '');

                if (!hasValue) {
                    return '';
                }

                return values.reduce((sum, input) => sum + parsePrice(input.value), 0);
            };

            const syncGrossInput = (grossInput) => {
                const row = grossInput.closest('tr[data-order_item_id]');

                if (!row || updatingNativePrices) {
                    return;
                }

                const inputs = priceInputs(row, grossInput.dataset.context, grossInput.dataset.kind);
                const gross = inputGross(inputs);
                grossInput.value = gross === '' ? '' : formatPrice(gross);
            };

            const splitGrossPrice = (grossInput) => {
                const row = grossInput.closest('tr[data-order_item_id]');

                if (!row) {
                    return;
                }

                const context = grossInput.dataset.context;
                const kind = grossInput.dataset.kind;
                const inputs = priceInputs(row, context, kind);

                if (!inputs.total) {
                    return;
                }

                let reference = inputs;
                let currentGross = inputGross(reference);

                // A refund can also be entered directly, without selecting a quantity first.
                // In that case use the original line's net/tax proportions.
                if (!currentGross && context === 'refund') {
                    reference = priceInputs(row, 'edit', 'total');
                    currentGross = inputGross(reference);
                }

                const gross = parsePrice(grossInput.value);
                const referenceValues = [reference.total].concat(reference.taxes).map((input) => input ? parsePrice(input.value) : 0);
                const shares = currentGross
                    ? referenceValues.map((value) => value / currentGross)
                    : [1].concat(inputs.taxes.map(() => 0));

                updatingNativePrices = true;

                [inputs.total].concat(inputs.taxes).forEach((input, index) => {
                    if (!input) {
                        return;
                    }

                    input.value = formatPrice(gross * (shares[index] || 0));
                });

                // WooCommerce calculates the refund amount and enables its save button
                // from changes to the native net and tax fields.
                [inputs.total].concat(inputs.taxes).forEach((input) => {
                    if (input) {
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                updatingNativePrices = false;
                syncGrossInput(grossInput);
            };

            const addGrossInput = (row, context, kind) => {
                const inputs = priceInputs(row, context, kind);

                if (!inputs.total || inputs.total.dataset.meditrendyGrossReady === '1') {
                    return;
                }

                inputs.total.dataset.meditrendyGrossReady = '1';
                inputs.total.classList.add('meditrendy-admin-net-price');

                const grossInput = document.createElement('input');
                grossInput.type = 'text';
                grossInput.className = 'wc_input_price meditrendy-admin-gross-price';
                grossInput.placeholder = inputs.total.placeholder || '0';
                grossInput.dataset.context = context;
                grossInput.dataset.kind = kind;
                grossInput.setAttribute('aria-label', context === 'refund' ? 'Gross refund amount' : 'Gross amount');
                inputs.total.insertAdjacentElement('afterend', grossInput);
                syncGrossInput(grossInput);

                grossInput.addEventListener('change', () => splitGrossPrice(grossInput));
            };

            const initializeGrossInputs = (root) => {
                root.querySelectorAll('.woocommerce_order_items tr[data-order_item_id]').forEach((row) => {
                    addGrossInput(row, 'edit', 'subtotal');
                    addGrossInput(row, 'edit', 'total');
                    addGrossInput(row, 'refund', 'total');
                });
            };

            const syncRowGrossInputs = (row) => {
                row.querySelectorAll('input.meditrendy-admin-gross-price').forEach(syncGrossInput);
            };

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
                    hasFeesRow = true;
                    total.innerHTML = summary.fees || total.innerHTML;
                } else if (label === normalize(labels.shipping)) {
                    total.innerHTML = summary.shipping || total.innerHTML;
                } else if (taxLabels.some((taxLabel) => label === normalize(taxLabel))) {
                    row.style.display = 'none';
                }
            });

            if (!hasFeesRow && Number(summary.feesRaw || 0) > 0) {
                const totals = document.querySelector('.wc-order-totals-items .wc-order-totals');

                if (totals) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td class="label">' + (labels.fees || 'Fees:') + '</td><td width="1%"></td><td class="total">' + (summary.fees || '') + '</td>';

                    const shippingRow = Array.from(totals.querySelectorAll('tr')).find((existingRow) => {
                        const label = normalize(existingRow.querySelector('.label') ? existingRow.querySelector('.label').textContent : '');
                        return label === normalize(labels.shipping);
                    });

                    if (shippingRow) {
                        totals.insertBefore(row, shippingRow);
                    } else {
                        totals.appendChild(row);
                    }
                }
            }

            initializeGrossInputs(document);

            document.addEventListener('change', (event) => {
                const input = event.target;

                if (!(input instanceof HTMLInputElement) || updatingNativePrices) {
                    return;
                }

                const row = input.closest('tr[data-order_item_id]');

                if (!row) {
                    return;
                }

                if (input.matches('.quantity, .refund_order_item_qty, .line_total, .line_subtotal, .line_tax, .line_subtotal_tax, .refund_line_total, .refund_line_tax')) {
                    window.setTimeout(() => syncRowGrossInputs(row), 0);
                }
            });

            const orderItems = document.querySelector('.woocommerce_order_items');

            if (orderItems && window.MutationObserver) {
                new MutationObserver(() => initializeGrossInputs(document)).observe(orderItems, {
                    childList: true,
                    subtree: true,
                });
            }
        })();
    </script>
    <?php
}
add_action('admin_footer', 'meditrendy_admin_order_gross_prices_footer', 20);
