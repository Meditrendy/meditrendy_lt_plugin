<?php
/** Standalone regression tests: php tests/admin-order-custom-items.php */
define('ABSPATH', __DIR__);
$hooks = [];
function add_action($name, $callback, ...$args) { $GLOBALS['hooks'][$name][] = $callback; }
function add_filter($name, $callback, ...$args) { add_action($name, $callback); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return stripslashes($value); }
function wc_tax_enabled() { return true; }
function wc_get_base_location() { return ['country' => 'LT']; }
function wc_get_rounding_precision() { return 6; }
function wc_format_decimal($value, $precision = false) { return $precision === false ? (string) $value : number_format((float) $value, $precision, '.', ''); }
function current_user_can(...$args) { return true; }
function wp_doing_ajax() { return true; }
function wp_send_json_error($error) { throw new RuntimeException($error['error']); }
function wc_get_order($id) { return $GLOBALS['test_order']; }
class WC_Tax {
    public static function get_tax_class_slugs() { return ['reduced']; }
    public static function get_rates_for_tax_class($class) {
        return $class === '' ? [7 => (object) ['tax_rate' => 21, 'tax_rate_country' => 'LT', 'tax_rate_compound' => 0]] : [];
    }
}
class WC_Order_Item_Product {
    public $props = ['name' => '', 'quantity' => 1, 'subtotal' => 0, 'total' => 0];
    public $meta = [];
    public $id = 42;
    public function __call($method, $args) {
        if (strpos($method, 'set_') === 0) $this->props[substr($method, 4)] = $args[0];
        else return $this->props[substr($method, 4)] ?? null;
    }
    public function get_id() { return $this->id; }
    public function get_order_id() { return 1; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
}
class TestOrder {
    public $items = [];
    public $recalculated = 0;
    public function is_editable() { return true; }
    public function get_item($id) { return $this->items[$id] ?? false; }
    public function get_items($type) { return []; }
    public function get_shipping_country() { return 'LT'; }
    public function get_billing_country() { return 'LT'; }
    public function add_item($item) { $this->items[] = $item; }
    public function save() {}
    public function update_taxes() {}
    public function calculate_totals($taxes) { $this->recalculated++; }
}
require dirname(__DIR__) . '/includes/admin-order-custom-items.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function run_hook($name, ...$args) { foreach ($GLOBALS['hooks'][$name] as $callback) $callback(...$args); }
$test_order = new TestOrder();
$raw = ['name' => 'Custom instrument', 'gross' => '12,10', 'rate' => '21', 'days' => '7', 'qty' => '3'];
$data = meditrendy_custom_item_validate($raw, $test_order);
$item = new WC_Order_Item_Product();
meditrendy_custom_item_apply($item, $data);
check(abs($item->get_total() - 30) < 0.000001, 'Gross to net conversion');
check(abs($item->get_taxes()['total'][7] - 6.3) < 0.000001, 'VAT calculation');
check($item->get_quantity() === 3.0, 'Quantity');
check($item->get_meta('_meditrendy_delivery_days') === 7, 'Calendar days stored privately');
check($item->get_meta('_meditrendy_tax_rate_id') === 7, 'Configured tax ID');
foreach (['rate' => '', 'qty' => '0', 'days' => '1.5', 'gross' => '-1'] as $key => $value) {
    try {
        meditrendy_custom_item_validate(array_replace($raw, [$key => $value]), $test_order);
        throw new RuntimeException('Accepted invalid ' . $key);
    } catch (Exception $error) {
        check(strpos($error->getMessage(), 'Accepted invalid') === false, $error->getMessage());
    }
}
try {
    meditrendy_custom_item_validate(array_replace($raw, ['rate' => '17']), $test_order);
    throw new RuntimeException('Accepted missing configured VAT');
} catch (Exception $error) { check(strpos($error->getMessage(), 'Accepted') === false, $error->getMessage()); }
$zero = meditrendy_custom_item_validate(array_replace($raw, ['rate' => '0']), $test_order);
$zero_item = new WC_Order_Item_Product();
meditrendy_custom_item_apply($zero_item, $zero);
check(abs($zero_item->get_total() - 36.3) < 0.000001 && !$zero_item->get_taxes()['total'], 'Explicit zero VAT');
$test_order->items[42] = $item;
run_hook('woocommerce_before_save_order_items', 1, ['med_custom' => [42 => $raw]]);
$item->set_total(25); // Preserve a coupon on unchanged saves.
run_hook('woocommerce_before_save_order_item', $item);
check($item->get_total() === 25, 'Unchanged edit must preserve discount');
run_hook('woocommerce_before_save_order_items', 1, ['med_custom' => [42 => array_replace($raw, ['days' => '9'])]]);
run_hook('woocommerce_before_save_order_item', $item);
check($item->get_total() === 25 && $item->get_meta('_meditrendy_delivery_days') === 9, 'Delivery-only edit preserves discount');
run_hook('woocommerce_order_item_after_calculate_taxes', $item);
check(abs($item->get_taxes()['total'][7] - 5.25) < 0.000001, 'Recalculate preserves manual VAT');
run_hook('woocommerce_before_save_order_items', 1, ['med_custom' => [42 => array_replace($raw, ['qty' => '4'])]]);
run_hook('woocommerce_before_save_order_item', $item);
check($item->get_quantity() === 4.0 && abs($item->get_total() - 40) < 0.000001, 'Saved line can be edited');
run_hook('woocommerce_before_save_order_items', 1, ['med_custom' => ['new_test' => $raw]]);
check(count($test_order->items) === 1, 'Validation must not create drafts');
run_hook('woocommerce_saved_order_items', 1);
check(count($test_order->items) === 2 && $test_order->recalculated === 1, 'Draft creation and order totals');
run_hook('woocommerce_saved_order_items', 1);
check(count($test_order->items) === 2, 'Draft state cleared after save');
try {
    run_hook('woocommerce_before_save_order_items', 1, ['med_custom' => [999 => $raw]]);
    throw new RuntimeException('Accepted foreign item');
} catch (RuntimeException $error) { check(strpos($error->getMessage(), 'Accepted') === false, $error->getMessage()); }
$hidden = $hooks['woocommerce_hidden_order_itemmeta'][0]([]);
check(in_array('_meditrendy_delivery_days', $hidden, true), 'Delivery metadata hidden from generic admin metadata');
foreach ($item->meta as $key => $value) check($key[0] === '_', 'All feature metadata must be customer-private');
echo "Custom order line regression tests passed.\n";
