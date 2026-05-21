<?php
/*
Plugin Name: Meditrendy Core
Description: Custom WooCommerce features for Meditrendy
Version: 1.0
*/

if (!defined('ABSPATH')) exit;

define('MEDITRENDY_CORE_FILE', __FILE__);
define('MEDITRENDY_CORE_DIR', plugin_dir_path(__FILE__));
define('MEDITRENDY_CORE_URL', plugin_dir_url(__FILE__));

require_once MEDITRENDY_CORE_DIR . 'includes/product-filters.php';
require_once MEDITRENDY_CORE_DIR . 'includes/filter-settings.php';
require_once MEDITRENDY_CORE_DIR . 'includes/product-waitlist.php';

/* ======================================================
   REMOVE GALLERY ZOOM & SLIDER
====================================================== */
add_action( 'after_setup_theme', 'remove_woo_three_support', 11 ); 
function remove_woo_three_support() {
    remove_theme_support( 'wc-product-gallery-zoom' );
}
add_filter( 'woocommerce_single_product_carousel_options', 'mt_gallery_loop' );

function mt_gallery_loop( $options ) {

    $options['animationLoop'] = true;   // loop
    $options['slideshow'] = false;      // bez autoplay
    $options['controlNav'] = 'thumbnails'; // miniaturki
    $options['directionNav'] = true;   // bez strzałek
    $options['smoothHeight'] = true;
    $options['animation'] = 'slide';

    return $options;
}
/* ======================================================
   CART BADGE
====================================================== */

add_action('wp_footer',function(){

if(is_admin()) return;

$count=(function_exists('WC') && WC()->cart)
? WC()->cart->get_cart_contents_count()
: 0;

?>

<script>

(function(){
const cartEndpoint='<?php echo esc_url_raw(rest_url('wc/store/v1/cart'));?>';
let cartCount=<?php echo (int) $count;?>;
let refreshTimer=0;

function getVisibleCartButton(){
const buttons=Array.from(document.querySelectorAll('.x-anchor.xoo-wsc-cart-trigger'));

return buttons.filter(function(button){
const rect=button.getBoundingClientRect();
return rect.width>0&&rect.height>0&&rect.top>=0&&rect.top<window.innerHeight;
}).pop();
}

function renderCartBadge(nextCount){
cartCount=Number.isFinite(nextCount)?nextCount:cartCount;

document.querySelectorAll('.meditrendy-cart-count').forEach(function(badge){
badge.remove();
});

document.querySelectorAll('.meditrendy-cart-toggle').forEach(function(toggle){
toggle.classList.remove('meditrendy-cart-toggle');
});

const cartButton=getVisibleCartButton();

if(!cartButton){
return;
}

const badgeTarget=cartButton.querySelector('.x-graphic')||cartButton;
badgeTarget.classList.add('meditrendy-cart-toggle');

if(cartCount>0){
const badge=document.createElement('span');
badge.className='meditrendy-cart-count';
badge.textContent=cartCount;
badgeTarget.appendChild(badge);
}
}

function readStoreCartCount(data){
if(data&&typeof data.items_count==='number'){
return data.items_count;
}

if(data&&Array.isArray(data.items)){
return data.items.reduce(function(total,item){
return total+(Number(item.quantity)||0);
},0);
}

return cartCount;
}

function refreshCartBadge(){
if(!window.fetch){
renderCartBadge(cartCount);
return;
}

window.fetch(cartEndpoint,{credentials:'include'})
.then(function(response){
return response.ok?response.json():null;
})
.then(function(data){
renderCartBadge(readStoreCartCount(data));
})
.catch(function(){
renderCartBadge(cartCount);
});
}

function scheduleCartBadgeRefresh(){
window.clearTimeout(refreshTimer);
refreshTimer=window.setTimeout(refreshCartBadge,250);
}

document.addEventListener('DOMContentLoaded',function(){
renderCartBadge(cartCount);
window.setTimeout(function(){renderCartBadge(cartCount);},250);
window.setTimeout(refreshCartBadge,900);

if(window.jQuery){
jQuery(document.body).on('added_to_cart removed_from_cart updated_cart_totals xoo_wsc_cart_updated xoo_wsc_quantity_updated',scheduleCartBadgeRefresh);
}
});
}());

</script>

<style>
.meditrendy-cart-toggle {
position: relative;
display: inline-flex;
}

.meditrendy-cart-count {
position: absolute;
top: -8px;
right: -10px;
z-index: 5;
display: inline-flex;
align-items: center;
justify-content: center;
min-width: 18px;
height: 18px;
padding: 0 5px;
border: 2px solid #ffffff;
border-radius: 999px;
background: red;
color: #ffffff;
font-size: 10px;
font-weight: 700;
line-height: 1;
letter-spacing: 0;
pointer-events: none;
}
</style>

<?php

});



/* ======================================================
   COLOR SWATCHES SHORTCODE (OPTIMIZED)
====================================================== */

add_shortcode('meditrendy_colors', function(){

    if(!function_exists('is_product') || !is_product()) {
        return '';
    }

    global $product;

    if(!$product) {
        return '';
    }

    $product_id = $product->get_id();

    /* CACHE */

    $cache_key = 'mt_swatches_' . $product_id;

    $cached = get_transient($cache_key);

    if($cached !== false) {
        return $cached;
    }

    /* MODEL */

    $model_terms = wp_get_post_terms(
        $product_id,
        'pa_model'
    );

    if(empty($model_terms)) {
        return '';
    }

    $model_slug = $model_terms[0]->slug;

    /* QUERY */

    $args = [

        'post_type' => 'product',
        'posts_per_page' => 28,
        'post_status' => 'publish',

        // PERFORMANCE
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,

        'tax_query' => [
            [
                'taxonomy' => 'pa_model',
                'field'    => 'slug',
                'terms'    => $model_slug
            ]
        ]

    ];

    $query = new WP_Query($args);

    if(empty($query->posts)) {
        return '';
    }

    /* CURRENT COLOR */

    $current_color_terms = wp_get_post_terms(
        $product_id,
        'pa_color'
    );

    $current_color_name = !empty($current_color_terms)
        ? $current_color_terms[0]->name
        : '';

    ob_start();

    echo '<div class="mt-color-wrapper">';

    echo '<div class="mt-color-label">';
    echo 'Color: <span class="mt-current-color">'
    . esc_html($current_color_name) .
    '</span>';
    echo '</div>';

    echo '<div class="mt-color-swatches">';

    foreach($query->posts as $p_id){

        // LIGHTWEIGHT STOCK CHECK
        $stock = get_post_meta(
            $p_id,
            '_stock_status',
            true
        );

        if($stock !== 'instock') {
            continue;
        }

        $color_terms = wp_get_post_terms(
            $p_id,
            'pa_color'
        );

        if(empty($color_terms)) {
            continue;
        }

        $color_term = $color_terms[0];

        $hex = get_term_meta(
            $color_term->term_id,
            'color_hex',
            true
        );

        if(empty($hex)) {
            continue;
        }

        $is_active = ($p_id == $product_id)
            ? 'active'
            : '';

        echo '<a href="' . esc_url(get_permalink($p_id)) . '"
        class="mt-swatch ' . esc_attr($is_active) . '"
        style="background:' . esc_attr($hex) . '"
        title="' . esc_attr($color_term->name) . '"
        aria-label="' . esc_attr($color_term->name) . '"></a>';
    }

    echo '</div>';
    echo '</div>';

    $output = ob_get_clean();

    /* CACHE 12h */

    set_transient(
        $cache_key,
        $output,
        12 * HOUR_IN_SECONDS
    );

    return $output;

});
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



add_action('woocommerce_after_shop_loop_item_title', 'meditrendy_add_colors_to_loop', 15);

function meditrendy_add_colors_to_loop() {
    echo do_shortcode('[meditrendy_colors]');
}
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

        $hex = get_term_meta(
            $term->term_id,
            'color_hex',
            true
        );

        if(empty($hex)){
            $hex = '#cccccc';
        }

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
