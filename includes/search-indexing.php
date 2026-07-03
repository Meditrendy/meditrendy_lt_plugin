<?php
if (!defined('ABSPATH')) exit;

function meditrendy_search_indexing_public_hosts() {
    return [
        'meditrendy.lt',
        'www.meditrendy.lt',
        'meditrendy.lv',
        'www.meditrendy.lv',
        'meditrendy.pl',
        'www.meditrendy.pl',
        'meditrendy.eu',
        'www.meditrendy.eu',
    ];
}

function meditrendy_search_indexing_current_host() {
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);

    return strtolower((string) $host);
}

function meditrendy_search_indexing_should_force_public() {
    $host = meditrendy_search_indexing_current_host();

    if ($host === '' || !in_array($host, meditrendy_search_indexing_public_hosts(), true)) {
        return false;
    }

    return wp_get_environment_type() === 'production';
}

function meditrendy_search_indexing_force_blog_public($value = null) {
    if (!meditrendy_search_indexing_should_force_public()) {
        return $value;
    }

    return '1';
}

function meditrendy_search_indexing_force_pre_update_blog_public($value) {
    if (!meditrendy_search_indexing_should_force_public()) {
        return $value;
    }

    return '1';
}

function meditrendy_search_indexing_allow_robots_indexing($robots) {
    if (!meditrendy_search_indexing_should_force_public() || !is_array($robots)) {
        return $robots;
    }

    unset($robots['noindex'], $robots['nofollow']);

    if (empty($robots['index'])) {
        $robots['index'] = true;
    }

    if (empty($robots['follow'])) {
        $robots['follow'] = true;
    }

    return $robots;
}

add_filter('pre_option_blog_public', 'meditrendy_search_indexing_force_blog_public', PHP_INT_MAX);
add_filter('option_blog_public', 'meditrendy_search_indexing_force_blog_public', PHP_INT_MAX);
add_filter('pre_update_option_blog_public', 'meditrendy_search_indexing_force_pre_update_blog_public', PHP_INT_MAX);
add_filter('wp_robots', 'meditrendy_search_indexing_allow_robots_indexing', PHP_INT_MAX);
