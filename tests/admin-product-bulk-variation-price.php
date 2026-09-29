<?php
/** Standalone regression checks: php tests/admin-product-bulk-variation-price.php */
define('ABSPATH', __DIR__);

$GLOBALS['test_actions'] = [];
$GLOBALS['test_action_stack'] = [];
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['test_actions'][$hook][$priority][] = $callback;
}
function add_filter() {}
function current_filter() { return end($GLOBALS['test_action_stack']); }
function do_action($hook, ...$args) {
    $GLOBALS['test_action_stack'][] = $hook;
    $callbacks = $GLOBALS['test_actions'][$hook] ?? [];
    ksort($callbacks);
    foreach ($callbacks as $group) {
        foreach ($group as $callback) {
            $callback(...$args);
        }
    }
    array_pop($GLOBALS['test_action_stack']);
}
function current_user_can() { return true; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function wc_get_price_decimals() { return 2; }
function wc_format_decimal($value, $decimals = false) {
    $value = str_replace(',', '.', (string) $value);
    return $decimals === false ? $value : number_format((float) $value, $decimals, '.', '');
}
function wc_get_product($id) { return $GLOBALS['products'][$id] ?? null; }
class WP_Query {
    public $posts = [];
    public function __construct($args) {
        ++$GLOBALS['set_queries'];
    }
}

class WC_Product {
    public $id;
    public $type;
    public $children = [];
    public $regular;
    public $sale;
    public $saved = 0;
    public $dates_cleared = false;
    public $parent_id = 0;

    public function __construct($id, $type, $regular = '', $sale = '') {
        $this->id = $id;
        $this->type = $type;
        $this->regular = $regular;
        $this->sale = $sale;
    }
    public function get_id() { return $this->id; }
    public function get_parent_id() { return $this->parent_id; }
    public function is_type($type) { return $this->type === $type; }
    public function get_children() { return $this->children; }
    public function get_regular_price($context = 'view') { return $this->regular; }
    public function get_sale_price($context = 'view') { return $this->sale; }
    public function set_regular_price($price) { $this->regular = $price; }
    public function set_sale_price($price) { $this->sale = $price; }
    public function set_date_on_sale_from($value) { $this->dates_cleared = true; }
    public function set_date_on_sale_to($value) { $this->dates_cleared = true; }
    public function save() {
        if ($this->type === 'variation' && $GLOBALS['meditrendy_bulk_price_deferred_parent'] !== 100) {
            throw new RuntimeException('Variation was saved without deferring set refresh.');
        }
        ++$this->saved;
        if ($this->type === 'variation') {
            meditrendy_product_set_refresh_containing_product($this->id);
        }
    }
}
class WC_Product_Variation extends WC_Product {}
class FixedSetProduct extends WC_Product {
    public function is_fixed_price() { return true; }
}
class WC_Product_Variable {
    public static $synced = [];
    public static function sync($id) {
        self::$synced[] = $id;
        meditrendy_product_set_refresh_containing_product($id);
    }
}

require dirname(__DIR__) . '/includes/product-set-cache.php';
require dirname(__DIR__) . '/includes/admin-product-bulk-variation-price.php';
require dirname(__DIR__) . '/includes/admin-product-inline-price.php';

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$GLOBALS['price_edit_fields_seen'] = [];
add_action('bulk_edit_custom_box', function($column) {
    if ($column === 'price') {
        $GLOBALS['price_edit_fields_seen'][] = 'bulk';
    }
}, 10, 2);
add_action('quick_edit_custom_box', function($column) {
    if ($column === 'price') {
        $GLOBALS['price_edit_fields_seen'][] = 'quick';
    }
}, 10, 2);
do_action('bulk_edit_custom_box', 'meditrendy_inline_price', 'product');
do_action('quick_edit_custom_box', 'meditrendy_inline_price', 'product');
check($GLOBALS['price_edit_fields_seen'] === ['bulk', 'quick'], 'WooCommerce edit fields must remain available under the replacement price column.');

$parent = new WC_Product(100, 'variable');
$parent->children = [101, 102];
$first = new WC_Product_Variation(101, 'variation', '50', '');
$second = new WC_Product_Variation(102, 'variation', '80', '');
$first->parent_id = $second->parent_id = 100;
$GLOBALS['products'] = [100 => $parent, 101 => $first, 102 => $second];
$GLOBALS['set_queries'] = 0;
$_REQUEST = [
    'change_regular_price' => '2',
    '_regular_price' => '10%',
    'change_sale_price' => '4',
    '_sale_price' => '20%',
];
meditrendy_bulk_edit_variation_prices($parent);
check($first->regular === '55.00' && $second->regular === '88.00', 'Relative regular price changes must preserve variation differences.');
check($first->sale === '44.00' && $second->sale === '70.40', 'Sale prices must use the new regular price of each variation.');
check($first->saved === 1 && $second->saved === 1, 'Each changed variation must be saved once.');
check($first->dates_cleared && $second->dates_cleared, 'Sale dates must be cleared after price changes.');
check(WC_Product_Variable::$synced === [100] && $GLOBALS['set_queries'] === 1, 'Parent must sync once and containing sets must be queried once.');
check(!isset($GLOBALS['meditrendy_bulk_price_deferred_parent']), 'The defer flag must be cleared after saving.');

$_REQUEST = ['change_sale_price' => '1', '_sale_price' => ''];
meditrendy_bulk_edit_variation_prices($parent);
check($first->sale === '' && $second->sale === '', 'A blank set-sale value must clear sales on every variation.');

$_REQUEST = ['change_regular_price' => '2', '_regular_price' => 'invalid'];
$saved_before = [$first->saved, $second->saved];
meditrendy_bulk_edit_variation_prices($parent);
check($saved_before === [$first->saved, $second->saved], 'Malformed input must not save variations.');

$fixed_set = new FixedSetProduct(200, 'woosb', '100', '');
$_REQUEST = ['change_regular_price' => '1', '_regular_price' => '120,50'];
meditrendy_bulk_edit_variation_prices($fixed_set);
check($fixed_set->regular === '120.50' && $fixed_set->saved === 1, 'Fixed-price product sets must receive bulk price changes.');

echo "Bulk variation price checks passed.\n";
