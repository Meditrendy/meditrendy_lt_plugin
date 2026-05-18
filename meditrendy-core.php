<?php
/*
Plugin Name: Meditrendy Core
Description: Custom WooCommerce features for Meditrendy
Version: 1.0
*/

if (!defined('ABSPATH')) exit;

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

document.addEventListener("DOMContentLoaded",function(){

var cartToggles=document.querySelectorAll(
'.x-anchor-toggle[aria-label="Toggle Off Canvas Content"]'
);

if(!cartToggles.length) return;

var count=<?php echo $count;?>;

cartToggles.forEach(function(wrapper){

if(wrapper.querySelector('.x-graphic-toggle')) return;

if(count>0 && !wrapper.querySelector('.meditrendy-cart-count')){

wrapper.insertAdjacentHTML(
'beforeend',
'<span class="meditrendy-cart-count">'+count+'</span>'
);

}

});

});

</script>

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
  BOOST SETTINGS
====================================================== */
/* ======================================================
   mini - cart header only after add-on
====================================================== */

add_action('wp_enqueue_scripts', function() {

    if (is_admin()) return;

    wp_dequeue_script('wc-cart-fragments');

    wp_enqueue_script(
        'wc-cart-fragments',
        WC()->plugin_url() . '/assets/js/frontend/cart-fragments.min.js',
        ['jquery'],
        WC_VERSION,
        true
    );

}, 20);

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