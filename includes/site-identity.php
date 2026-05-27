<?php
if (!defined('ABSPATH')) exit;

function meditrendy_site_brand_name() {
    return 'Meditrendy';
}

function meditrendy_public_blogname($value) {
    if (is_admin() && !wp_doing_ajax()) {
        return $value;
    }

    return meditrendy_site_brand_name();
}
add_filter('option_blogname', 'meditrendy_public_blogname');

function meditrendy_site_identity_schema() {
    if (is_admin() || (!is_front_page() && !is_home())) {
        return;
    }

    $home_url = home_url('/');
    $brand_name = meditrendy_site_brand_name();
    $logo_id = (int) get_theme_mod('custom_logo');
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

    $organization = array_filter([
        '@type'  => 'Organization',
        '@id'    => $home_url . '#organization',
        'name'   => $brand_name,
        'url'    => $home_url,
        'logo'   => $logo_url ?: null,
    ]);

    $schema = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            $organization,
            [
                '@type'         => 'WebSite',
                '@id'           => $home_url . '#website',
                'url'           => $home_url,
                'name'          => $brand_name,
                'publisher'     => [
                    '@id' => $home_url . '#organization',
                ],
            ],
        ],
    ];

    echo "\n" . '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
add_action('wp_head', 'meditrendy_site_identity_schema', 1);
