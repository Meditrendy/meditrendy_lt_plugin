<?php
if (!defined('ABSPATH')) exit;

function meditrendy_blog_archive_page_number() {
    $page = isset($_GET['mt_blog_page']) ? absint(wp_unslash($_GET['mt_blog_page'])) : 1;

    return max(1, $page);
}

function meditrendy_blog_archive_active_category() {
    if (empty($_GET['mt_blog_category'])) {
        return '';
    }

    return sanitize_title(wp_unslash($_GET['mt_blog_category']));
}

function meditrendy_blog_archive_base_url() {
    $url = get_permalink();

    if (!$url) {
        $url = home_url(add_query_arg([], $GLOBALS['wp']->request));
    }

    return remove_query_arg(['mt_blog_page'], $url);
}

function meditrendy_blog_archive_category_url($category_slug = '') {
    $url = remove_query_arg(['mt_blog_category', 'mt_blog_page'], meditrendy_blog_archive_base_url());

    if ($category_slug !== '') {
        $url = add_query_arg('mt_blog_category', sanitize_title($category_slug), $url);
    }

    return $url;
}

function meditrendy_blog_archive_pagination_url($page) {
    $url = meditrendy_blog_archive_base_url();
    $category = meditrendy_blog_archive_active_category();

    if ($category !== '') {
        $url = add_query_arg('mt_blog_category', $category, $url);
    }

    if ((int) $page > 1) {
        $url = add_query_arg('mt_blog_page', (int) $page, $url);
    }

    return $url;
}

function meditrendy_blog_archive_categories_html($active_category) {
    $categories = get_categories([
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    if (!$categories || is_wp_error($categories)) {
        return '';
    }

    ob_start();
    ?>
    <nav class="mt-blog-categories" aria-label="<?php echo esc_attr__('Blog categories', 'meditrendy-core'); ?>">
        <a class="mt-blog-category<?php echo $active_category === '' ? ' is-active' : ''; ?>" href="<?php echo esc_url(meditrendy_blog_archive_category_url()); ?>">
            <?php esc_html_e('Visi', 'meditrendy-core'); ?>
        </a>
        <?php foreach ($categories as $category) : ?>
            <a class="mt-blog-category<?php echo $active_category === $category->slug ? ' is-active' : ''; ?>" href="<?php echo esc_url(meditrendy_blog_archive_category_url($category->slug)); ?>">
                <?php echo esc_html($category->name); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php

    return ob_get_clean();
}

function meditrendy_blog_archive_card($post_id) {
    $post_id = (int) $post_id;
    $url = get_permalink($post_id);
    $title = get_the_title($post_id);
    $date = get_the_date('', $post_id);
    $categories = get_the_category($post_id);
    $category = $categories ? $categories[0] : null;
    $image = get_the_post_thumbnail(
        $post_id,
        'large',
        [
            'class'   => 'mt-blog-card-image',
            'loading' => 'lazy',
        ]
    );
    $excerpt = get_the_excerpt($post_id);

    ob_start();
    ?>
    <article class="mt-blog-card">
        <a class="mt-blog-card-media<?php echo $image ? '' : ' has-placeholder'; ?>" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
            <?php if ($image) : ?>
                <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </a>
        <div class="mt-blog-card-body">
            <h2 class="mt-blog-card-title">
                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
            </h2>
            <div class="mt-blog-card-meta">
                <?php if ($category) : ?>
                    <span><?php echo esc_html($category->name); ?></span>
                <?php endif; ?>
                <time datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>"><?php echo esc_html($date); ?></time>
            </div>
            <?php if ($excerpt) : ?>
                <p class="mt-blog-card-excerpt"><?php echo esc_html(wp_trim_words($excerpt, 24)); ?></p>
            <?php endif; ?>
            <a class="mt-blog-card-link" href="<?php echo esc_url($url); ?>">
                <?php
                printf(
                    esc_html__('Skaityti daugiau apie %s', 'meditrendy-core'),
                    esc_html($title)
                );
                ?>
            </a>
        </div>
    </article>
    <?php

    return ob_get_clean();
}

function meditrendy_blog_archive_pagination_html($query) {
    if (!$query instanceof WP_Query || (int) $query->max_num_pages <= 1) {
        return '';
    }

    $current = meditrendy_blog_archive_page_number();
    $links = paginate_links([
        'base'      => esc_url_raw(add_query_arg('mt_blog_page', '%#%', meditrendy_blog_archive_pagination_url(1))),
        'format'    => '',
        'current'   => $current,
        'total'     => (int) $query->max_num_pages,
        'type'      => 'list',
        'prev_text' => '&larr;',
        'next_text' => '&rarr;',
    ]);

    return $links ? '<nav class="mt-blog-pagination" aria-label="' . esc_attr__('Blog pagination', 'meditrendy-core') . '">' . $links . '</nav>' : '';
}

function meditrendy_blog_page_url($custom_url = '') {
    if ($custom_url !== '') {
        return esc_url_raw($custom_url);
    }

    $posts_page_id = (int) get_option('page_for_posts');

    if ($posts_page_id > 0) {
        $url = get_permalink($posts_page_id);

        if ($url) {
            return $url;
        }
    }

    $blog_page = get_page_by_path('blog');

    if ($blog_page instanceof WP_Post) {
        $url = get_permalink($blog_page);

        if ($url) {
            return $url;
        }
    }

    return home_url('/blog/');
}

function meditrendy_blog_home_item($post_id) {
    $post_id = (int) $post_id;
    $url = get_permalink($post_id);
    $title = get_the_title($post_id);
    $image = get_the_post_thumbnail(
        $post_id,
        'large',
        [
            'class'   => 'mt-blog-home-item-image',
            'loading' => 'lazy',
        ]
    );

    ob_start();
    ?>
    <article class="mt-blog-home-item">
        <a class="mt-blog-home-item-link" href="<?php echo esc_url($url); ?>">
            <span class="mt-blog-home-item-media<?php echo $image ? '' : ' has-placeholder'; ?>" aria-hidden="true">
                <?php if ($image) : ?>
                    <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </span>
            <span class="mt-blog-home-item-title"><?php echo esc_html($title); ?></span>
        </a>
    </article>
    <?php

    return ob_get_clean();
}

function meditrendy_blog_home_shortcode($atts, $content = null) {
    $atts = shortcode_atts(
        [
            'posts_per_page' => '3',
            'title'          => 'BLOG',
            'description'    => 'Kvieciame i medicinines mados pasauli Meditrendy. Cia rasite ikvepimo, praktisku patarimu ir naujausias tendencijas patogiai kasdienai darbe.',
            'button_text'    => 'SKAITYTI DAUGIAU',
            'archive_url'    => '',
            'category'       => '',
        ],
        $atts,
        'meditrendy_blog_home'
    );

    $args = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => max(1, min(6, absint($atts['posts_per_page']))),
        'ignore_sticky_posts' => true,
    ];

    if ($atts['category'] !== '') {
        $args['category_name'] = sanitize_title($atts['category']);
    }

    $query = new WP_Query($args);
    $archive_url = meditrendy_blog_page_url($atts['archive_url']);
    $description = trim((string) $content) !== '' ? do_shortcode($content) : $atts['description'];

    ob_start();
    ?>
    <section class="mt-blog-home">
        <div class="mt-blog-home-copy">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <?php if ($description !== '') : ?>
                <div class="mt-blog-home-description">
                    <?php echo wp_kses_post(wpautop($description)); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($query->have_posts()) : ?>
            <div class="mt-blog-home-posts" aria-label="<?php echo esc_attr__('Latest blog posts', 'meditrendy-core'); ?>">
                <?php while ($query->have_posts()) : ?>
                    <?php
                    $query->the_post();
                    echo meditrendy_blog_home_item(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <?php if ($atts['button_text'] !== '') : ?>
            <div class="mt-blog-home-action">
                <a class="mt-blog-home-button" href="<?php echo esc_url($archive_url); ?>">
                    <?php echo esc_html($atts['button_text']); ?>
                </a>
            </div>
        <?php endif; ?>
    </section>
    <?php

    wp_reset_postdata();

    return ob_get_clean();
}

function meditrendy_blog_archive_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'posts_per_page'   => '9',
            'show_categories'  => '0',
            'show_heading'     => '1',
            'title'            => 'Straipsniai',
            'category'         => '',
        ],
        $atts,
        'meditrendy_blog_archive'
    );

    $active_category = meditrendy_blog_archive_active_category();

    if ($active_category === '' && $atts['category'] !== '') {
        $active_category = sanitize_title($atts['category']);
    }

    $args = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => max(1, min(24, absint($atts['posts_per_page']))),
        'paged'               => meditrendy_blog_archive_page_number(),
        'ignore_sticky_posts' => true,
    ];

    if ($active_category !== '') {
        $args['category_name'] = $active_category;
    }

    $query = new WP_Query($args);

    ob_start();
    ?>
    <section class="mt-blog-archive">
        <?php if ($atts['show_heading'] !== '0') : ?>
            <header class="mt-blog-archive-header">
                <h1><?php echo esc_html($atts['title']); ?></h1>
            </header>
        <?php endif; ?>

        <?php if ($atts['show_categories'] !== '0') : ?>
            <?php echo meditrendy_blog_archive_categories_html($active_category); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>

        <?php if ($query->have_posts()) : ?>
            <div class="mt-blog-grid">
                <?php while ($query->have_posts()) : ?>
                    <?php
                    $query->the_post();
                    echo meditrendy_blog_archive_card(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php endwhile; ?>
            </div>
            <?php echo meditrendy_blog_archive_pagination_html($query); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
            <p class="mt-blog-empty"><?php esc_html_e('Straipsniu nerasta.', 'meditrendy-core'); ?></p>
        <?php endif; ?>
    </section>
    <?php

    wp_reset_postdata();

    return ob_get_clean();
}

add_shortcode('meditrendy_blog_archive', 'meditrendy_blog_archive_shortcode');
add_shortcode('meditrendy_blog_home', 'meditrendy_blog_home_shortcode');
