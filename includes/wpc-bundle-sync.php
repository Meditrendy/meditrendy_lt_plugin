<?php
if (!defined('ABSPATH')) exit;

function meditrendy_wpc_bundle_without_composition_meta($metas) {
    if (!is_array($metas)) {
        return $metas;
    }

    unset($metas['woosb_ids']);

    foreach ($metas as $index => $meta_key) {
        if ($meta_key === 'woosb_ids') {
            unset($metas[$index]);
        }
    }

    return $metas;
}

function meditrendy_wpc_bundle_disable_composition_sync($metas, $sync = false, $from = 0) {
    if ($from && !in_array(get_post_type($from), ['product', 'product_variation'], true)) {
        return $metas;
    }

    return meditrendy_wpc_bundle_without_composition_meta($metas);
}
add_filter('pll_copy_post_metas', 'meditrendy_wpc_bundle_disable_composition_sync', 999, 5);
add_filter('pllwc_copy_post_metas', 'meditrendy_wpc_bundle_disable_composition_sync', 999, 5);
