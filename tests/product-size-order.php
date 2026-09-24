<?php
/** Standalone regression tests: php tests/product-size-order.php */
define('ABSPATH', __DIR__);

function add_filter() {}
function sanitize_title($value) {
    return strtolower(trim((string) $value));
}

require dirname(__DIR__) . '/includes/product-size-order.php';

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$args = meditrendy_sort_variation_size_options([
    'attribute' => 'pa_rozmiar',
    'options'   => ['l', 'm', 's', 'xl', 'xs', 'xxs'],
]);

check(
    $args['options'] === ['xxs', 'xs', 's', 'm', 'l', 'xl'],
    'Polish size dropdown options must be sorted from smallest to largest'
);

$terms = array_map(function($slug) {
    return (object) ['slug' => $slug];
}, ['l', 'm', 's', 'xl', 'xs', 'xxs']);

$terms = meditrendy_sort_size_swatch_terms($terms, 1, 'pa_rozmiar', []);

check(
    array_column($terms, 'slug') === ['xxs', 'xs', 's', 'm', 'l', 'xl'],
    'Polish size swatches must be sorted from smallest to largest'
);

echo "Product size-order regression tests passed.\n";
