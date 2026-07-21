<?php
if (!defined('ABSPATH')) exit;

/**
 * Adds the latest non-system WooCommerce order note to the orders list table.
 */

function meditrendy_admin_order_last_comment_insert_column($columns) {
    $new_columns = [];

    foreach ((array) $columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'order_status') {
            $new_columns['meditrendy_last_order_comment'] = __('Ostatni komentarz', 'meditrendy-core');
        }
    }

    if (!isset($new_columns['meditrendy_last_order_comment'])) {
        $new_columns['meditrendy_last_order_comment'] = __('Ostatni komentarz', 'meditrendy-core');
    }

    return $new_columns;
}
add_filter('manage_edit-shop_order_columns', 'meditrendy_admin_order_last_comment_insert_column', 30);
add_filter('manage_woocommerce_page_wc-orders_columns', 'meditrendy_admin_order_last_comment_insert_column', 30);

function meditrendy_admin_order_last_comment_note($order_id) {
    if (!function_exists('wc_get_order_notes')) {
        return null;
    }

    $notes = wc_get_order_notes([
        'order_id' => absint($order_id),
        'orderby' => 'date_created',
        'order' => 'DESC',
    ]);

    if (empty($notes) || !is_array($notes)) {
        return null;
    }

    foreach ($notes as $note) {
        if ((string) ($note->added_by ?? '') !== 'system') {
            return $note;
        }
    }

    return null;
}

function meditrendy_admin_order_last_comment_render_note($order_id) {
    $note = meditrendy_admin_order_last_comment_note($order_id);

    if (!$note || !isset($note->content)) {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
        $customer_note = $order instanceof WC_Order ? trim(wp_strip_all_tags((string) $order->get_customer_note())) : '';

        if ($customer_note !== '') {
            ?>
            <div class="meditrendy-admin-order-last-comment is-customer-note">
                <div class="meditrendy-admin-order-last-comment__text" title="<?php echo esc_attr($customer_note); ?>">
                    <?php echo esc_html($customer_note); ?>
                </div>
                <div class="meditrendy-admin-order-last-comment__meta">
                    <?php echo esc_html__('komentarz klienta', 'meditrendy-core'); ?>
                </div>
            </div>
            <?php
        } else {
            echo '<span class="meditrendy-admin-order-last-comment-empty">&mdash;</span>';
        }

        return;
    }

    $content = trim(wp_strip_all_tags((string) $note->content));

    if ($content === '') {
        echo '<span class="meditrendy-admin-order-last-comment-empty">&mdash;</span>';
        return;
    }

    $added_by = isset($note->added_by) ? trim((string) $note->added_by) : '';
    $is_customer_note = !empty($note->customer_note);
    $date = isset($note->date_created) ? $note->date_created : null;
    $date_text = $date instanceof WC_DateTime ? $date->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : '';
    $classes = 'meditrendy-admin-order-last-comment';

    if ($is_customer_note) {
        $classes .= ' is-customer-note';
    }
    ?>
    <div class="<?php echo esc_attr($classes); ?>">
        <div class="meditrendy-admin-order-last-comment__text" title="<?php echo esc_attr($content); ?>">
            <?php echo esc_html($content); ?>
        </div>
        <?php if ($added_by || $date_text || $is_customer_note) : ?>
            <div class="meditrendy-admin-order-last-comment__meta">
                <?php
                $meta = [];

                if ($is_customer_note) {
                    $meta[] = __('dla klienta', 'meditrendy-core');
                }

                if ($added_by) {
                    $meta[] = $added_by;
                }

                if ($date_text) {
                    $meta[] = $date_text;
                }

                echo esc_html(implode(' · ', $meta));
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function meditrendy_admin_order_last_comment_classic_column($column, $post_id) {
    if ($column !== 'meditrendy_last_order_comment') {
        return;
    }

    meditrendy_admin_order_last_comment_render_note($post_id);
}
add_action('manage_shop_order_posts_custom_column', 'meditrendy_admin_order_last_comment_classic_column', 20, 2);

function meditrendy_admin_order_last_comment_hpos_column($column, $order) {
    if ($column !== 'meditrendy_last_order_comment') {
        return;
    }

    if (!$order instanceof WC_Order) {
        echo '<span class="meditrendy-admin-order-last-comment-empty">&mdash;</span>';
        return;
    }

    meditrendy_admin_order_last_comment_render_note($order->get_id());
}
add_action('manage_woocommerce_page_wc-orders_custom_column', 'meditrendy_admin_order_last_comment_hpos_column', 20, 2);

function meditrendy_admin_order_last_comment_styles() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen ? (string) $screen->id : '';

    if (!in_array($screen_id, ['edit-shop_order', 'woocommerce_page_wc-orders'], true)) {
        return;
    }
    ?>
    <style>
        .wp-list-table .column-meditrendy_last_order_comment {
            width: 260px;
        }

        .meditrendy-admin-order-last-comment__text {
            display: -webkit-box;
            max-width: 260px;
            overflow: hidden;
            color: #1d2327;
            font-size: 12px;
            line-height: 1.35;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .meditrendy-admin-order-last-comment__meta {
            margin-top: 3px;
            color: #646970;
            font-size: 11px;
            line-height: 1.25;
        }

        .meditrendy-admin-order-last-comment.is-customer-note .meditrendy-admin-order-last-comment__meta {
            color: #996800;
        }

        .meditrendy-admin-order-last-comment-empty {
            color: #8c8f94;
        }
    </style>
    <?php
}
add_action('admin_head', 'meditrendy_admin_order_last_comment_styles');
