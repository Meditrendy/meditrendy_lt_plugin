<?php
/*
Plugin Name: Meditrendy Core
Description: Custom WooCommerce features for Meditrendy
Version: 1.0
Text Domain: meditrendy-core
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit;

define('MEDITRENDY_CORE_FILE', __FILE__);
define('MEDITRENDY_CORE_DIR', plugin_dir_path(__FILE__));
define('MEDITRENDY_CORE_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function() {
    load_plugin_textdomain('meditrendy-core', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

require_once MEDITRENDY_CORE_DIR . 'includes/product-card-renderer.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-internal-name.php';
require_once MEDITRENDY_CORE_DIR . 'includes/site-identity.php';
require_once MEDITRENDY_CORE_DIR . 'includes/module-settings.php';
require_once MEDITRENDY_CORE_DIR . 'includes/search-indexing.php';
require_once MEDITRENDY_CORE_DIR . 'includes/account-domain-routing.php';
require_once MEDITRENDY_CORE_DIR . 'includes/marketing-banner.php';
require_once MEDITRENDY_CORE_DIR . 'includes/configurable-popups.php';
require_once MEDITRENDY_CORE_DIR . 'includes/cart-badge.php';
require_once MEDITRENDY_CORE_DIR . 'includes/side-cart.php';
require_once MEDITRENDY_CORE_DIR . 'includes/edrone-newsletter.php';
require_once MEDITRENDY_CORE_DIR . 'includes/performance.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-attribute-labels.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-brand-label.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-size-order.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-filters.php';
require_once MEDITRENDY_CORE_DIR . 'includes/filter-settings.php';
require_once MEDITRENDY_CORE_DIR . 'includes/customer-note-email.php';
require_once MEDITRENDY_CORE_DIR . 'includes/side-cart-upsells.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-subcategories.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-category-description.php';
require_once MEDITRENDY_CORE_DIR . 'includes/brand-products-shortcode.php';
require_once MEDITRENDY_CORE_DIR . 'includes/language-switcher-shortcode.php';
require_once MEDITRENDY_CORE_DIR . 'includes/blog-archive.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-waitlist.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-color-swatches.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-listing-color-swatches.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-pdp-upsells.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-size-charts.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-set-variation-status.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-set-labels.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-complete-set.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-price-block.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-promotions.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-gallery.php';
require_once MEDITRENDY_CORE_DIR . 'includes/checkout-invoice-fields.php';
require_once MEDITRENDY_CORE_DIR . 'includes/checkout-pickup-availability.php';
require_once MEDITRENDY_CORE_DIR . 'includes/checkout-terms-consent.php';
require_once MEDITRENDY_CORE_DIR . 'includes/checkout-translations.php';
require_once MEDITRENDY_CORE_DIR . 'includes/checkout-cod-fee.php';
require_once MEDITRENDY_CORE_DIR . 'includes/wpc-bundle-sync.php';
require_once MEDITRENDY_CORE_DIR . 'includes/wpc-bundle-blocks-compat.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-set-cache.php';
require_once MEDITRENDY_CORE_DIR . 'includes/wpc-bundle-variation-json-compat.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-badges.php';
require_once MEDITRENDY_CORE_DIR . 'includes/admin-order-gross-prices.php';
require_once MEDITRENDY_CORE_DIR . 'includes/admin-order-labels.php';
require_once MEDITRENDY_CORE_DIR . 'includes/admin-order-item-color.php';
require_once MEDITRENDY_CORE_DIR . 'includes/admin-order-image-preview.php';
require_once MEDITRENDY_CORE_DIR . 'includes/admin-orders-pdf.php';
require_once MEDITRENDY_CORE_DIR . 'includes/cart-estimated-delivery.php';
require_once MEDITRENDY_CORE_DIR . 'includes/admin-order-last-comment-column.php';
require_once MEDITRENDY_CORE_DIR . 'includes/paysera-pos-sync.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-rest-api-logger.php';
require_once MEDITRENDY_CORE_DIR . 'includes/wc-price-history-recovery.php';

/**
 * Load Meditrendy Core storefront translations from the WordPress Site Language.
 *
 * Each country storefront is a separate WordPress installation. This prevents a
 * temporary Polylang selection or an administrator profile locale from changing
 * customer-facing labels such as the product-set add-to-cart button.
 */
function meditrendy_core_load_storefront_translations() {
    if (is_admin() || !function_exists('meditrendy_core_current_language')) {
        return;
    }

    $language = meditrendy_core_current_language();
    $locales = [
        'en' => 'en_GB',
        'et' => 'et',
        'lt' => null,
        'lv' => 'lv',
        'pl' => 'pl_PL',
    ];

    if (!isset($locales[$language])) {
        return;
    }

    unload_textdomain('meditrendy-core', true);

    // Lithuanian is the plugin's source language, so it deliberately has no MO file.
    if ($locales[$language] === null) {
        return;
    }

    $translation_file = MEDITRENDY_CORE_DIR . 'languages/meditrendy-core-' . $locales[$language] . '.mo';

    if (!is_readable($translation_file)) {
        return;
    }

    load_textdomain('meditrendy-core', $translation_file);
}
add_action('init', 'meditrendy_core_load_storefront_translations', 1);

/* ======================================================
   ACF +SHOP MAMAGER
====================================================== */
add_filter('acf/settings/capability', function() {
    return 'edit_products';
});

/* ======================================================
   ACF FORCE json
====================================================== */
add_filter('acf/settings/save_json', function() {
    return get_stylesheet_directory() . '/acf-json';
});

add_filter('acf/settings/load_json', function($paths) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});


/* ======================================================
   avoid global cart fragments
====================================================== */

add_action('wp_enqueue_scripts', function() {
    if (is_admin()) return;

    if (function_exists('is_cart') && is_cart()) return;
    if (function_exists('is_checkout') && is_checkout()) return;

    wp_dequeue_script('wc-cart-fragments');
}, 100);

/* ======================================================
   remove emoji
====================================================== */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

/* ======================================================
   FILTER COLORS SHORTCODE
====================================================== */

add_shortcode('med_filter_colors', function(){

    global $wp;

    if(!is_shop() && !is_product_taxonomy() && !is_product_category()){
        return '';
    }

    $terms = get_terms([
        'taxonomy'   => 'pa_color-group',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ]);

    if(empty($terms) || is_wp_error($terms)){
        return '';
    }

    $active_filters = [];

    // POPRAWNY PARAMETR
    if(isset($_GET['filter_group_color'])){

        $active_filters = explode(
            ',',
            sanitize_text_field($_GET['filter_group_color'])
        );
    }

    ob_start();

    echo '<div class="med-filter-colors">';

    foreach($terms as $term){

        $hex = function_exists('meditrendy_native_color_group_hex')
            ? meditrendy_native_color_group_hex($term)
            : '#cccccc';

        $is_active = in_array(
            $term->slug,
            $active_filters
        );

        $new_filters = $active_filters;

        if($is_active){

            $new_filters = array_diff(
                $new_filters,
                [$term->slug]
            );

        } else {

            $new_filters[] = $term->slug;
        }

        $new_filters = array_filter($new_filters);

        // aktualny URL kategorii/podkategorii
        $current_url = home_url($wp->request);

        // POPRAWNY PARAMETR
        $url = add_query_arg(
            'filter_group_color',
            implode(',', $new_filters),
            $current_url
        );

        echo '<a href="'.esc_url($url).'"
        class="med-color-filter '.($is_active ? 'active' : '').'">';

        echo '<span class="dot"
        style="background:'.esc_attr($hex).'"></span>';

        echo '<span class="label">'
        .esc_html($term->name).
        '</span>';

        echo '</a>';
    }

    echo '</div>';

    return ob_get_clean();

});


/* ======================================================
   CUSTOM ATTRIBUTE FILTERS
====================================================== */

add_action('pre_get_posts', function($query){

    if(is_admin()){
        return;
    }

    if(!$query->is_main_query()){
        return;
    }

    if(
        !is_shop()
        && !is_product_taxonomy()
        && !is_product_category()
    ){
        return;
    }

    // POPRAWNY PARAMETR
    if(empty($_GET['filter_group_color'])){
        return;
    }

    $colors = explode(
        ',',
        sanitize_text_field($_GET['filter_group_color'])
    );

    // istniejące tax_query WooCommerce
    $tax_query = (array) $query->get('tax_query');

    // dodaj relację jeśli brak
    if(empty($tax_query['relation'])){
        $tax_query['relation'] = 'AND';
    }

    // dodaj nasz filtr
    $tax_query[] = [
        'taxonomy' => 'pa_color-group',
        'field'    => 'slug',
        'terms'    => $colors,
        'operator' => 'IN'
    ];

    $query->set('tax_query', $tax_query);

}, 999);
