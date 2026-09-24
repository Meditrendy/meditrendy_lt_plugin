<?php
/** Standalone regression tests: php tests/admin-order-gross-prices.php */
define('ABSPATH', __DIR__);

function add_action() {}
function add_filter() {}
function wc_price($amount, $args = []) {
    return number_format((float) $amount, 2, '.', '') . ' ' . ($args['currency'] ?? '');
}

require dirname(__DIR__) . '/includes/admin-order-gross-prices.php';

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class GrossPriceTestItem {
    private $total;
    private $tax;
    private $refunded_item_id;

    public function __construct($total, $tax, $refunded_item_id = 0) {
        $this->total = $total;
        $this->tax = $tax;
        $this->refunded_item_id = $refunded_item_id;
    }

    public function get_total() {
        return $this->total;
    }

    public function get_total_tax() {
        return $this->tax;
    }

    public function get_meta($key) {
        return $key === '_refunded_item_id' ? $this->refunded_item_id : '';
    }
}

class GrossPriceTestRefund {
    private $items;

    public function __construct($items) {
        $this->items = $items;
    }

    public function get_items($type) {
        return $type === 'line_item' ? $this->items : [];
    }
}

class GrossPriceTestOrder {
    private $refunds;

    public function __construct($refunds = []) {
        $this->refunds = $refunds;
    }

    public function get_currency() {
        return 'EUR';
    }

    public function get_refunds() {
        return $this->refunds;
    }
}

$columns = meditrendy_admin_order_gross_prices_preview_columns([
    'product' => 'Product',
    'quantity' => 'Quantity',
    'tax' => 'Tax',
    'total' => 'Total',
]);
check(array_keys($columns) === ['product', 'quantity', 'tax', 'gross_total'], 'Preview total column must be replaced in place');

$item = new GrossPriceTestItem(18.97, 3.98);
$refunded_item = new GrossPriceTestItem(-18.97, -3.98, 7);
$order = new GrossPriceTestOrder([new GrossPriceTestRefund([$refunded_item])]);
$html = meditrendy_admin_order_gross_prices_preview_total('', $item, 7, $order);

check(strpos($html, '22.95 EUR') !== false, 'Preview must display the tax-inclusive line total');
check(strpos($html, '-22.95 EUR') !== false, 'Preview must display the tax-inclusive refunded total');

echo "Admin order gross-price regression tests passed.\n";
