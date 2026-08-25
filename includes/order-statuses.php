<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the ordered Meditrendy order status registry.
 *
 * Core WooCommerce statuses are included so the admin status selector follows
 * one coherent workflow. Only statuses with "core" set to false are registered
 * by this plugin.
 *
 * Custom statuses deliberately have no email or automation side effects yet.
 * Their behaviour can be added later without changing the stored status values
 * or the legacy import mapping.
 *
 * @return array<string,array<string,mixed>>
 */
function meditrendy_order_status_registry() {
    $core = static function ($label, $legacy_ids = []) {
        return [
            'label'          => $label,
            'core'           => true,
            'legacy_ids'     => $legacy_ids,
            'email_policy'   => 'woocommerce-core',
            'automations'    => [],
        ];
    };

    $custom = static function ($label, $legacy_ids) {
        return [
            'label'          => $label,
            'core'           => false,
            'legacy_ids'     => $legacy_ids,
            'email_policy'   => 'none',
            'automations'    => [],
        ];
    };

    $statuses = [
        'wc-pending' => $core(__('Pending payment', 'woocommerce'), [9]),
        'wc-mt-placed' => $custom(__('Złożone', 'meditrendy-core'), [1, 31]),
        'wc-on-hold' => $core(__('On hold', 'woocommerce')),
        'wc-mt-paid' => $custom(__('Płatność zarejestrowana', 'meditrendy-core'), [6]),
        'wc-processing' => $core(__('Processing', 'woocommerce'), [7, 119, 120]),
        'wc-mt-wait-salon' => $custom(__('Oczekiwanie na dostawę do Salonu Meditrendy', 'meditrendy-core'), [10]),
        'wc-mt-embroidery' => $custom(__('W trakcie wykonywania haftu komputerowego', 'meditrendy-core'), [39]),
        'wc-mt-business' => $custom(__('Przekazane do Działu Biznes', 'meditrendy-core'), []),
        'wc-mt-dispatch' => $custom(__('Przekazane do Działu Wysyłek', 'meditrendy-core'), [4]),
        'wc-mt-courier' => $custom(__('Przekazane kurierowi', 'meditrendy-core'), [2]),
        'wc-mt-all-sent' => $custom(__('Wszystkie paczki zostały wysłane', 'meditrendy-core'), [118]),
        'wc-mt-salon-route' => $custom(__('W drodze do Salonu Meditrendy', 'meditrendy-core'), []),
        'wc-mt-pick-lodz' => $custom(__('Oczekuje na odbiór w Salonie Łódź', 'meditrendy-core'), [19]),
        'wc-mt-pick-gdansk' => $custom(__('Oczekuje na odbiór w Salonie Gdańsk', 'meditrendy-core'), [37]),
        'wc-mt-pick-wroclaw' => $custom(__('Oczekuje na odbiór w Salonie Wrocław', 'meditrendy-core'), [29]),
        'wc-mt-pick-krakow' => $custom(__('Oczekuje na odbiór w Salonie Kraków', 'meditrendy-core'), [15]),
        'wc-mt-pick-szczecin' => $custom(__('Oczekuje na odbiór w Salonie Szczecin', 'meditrendy-core'), [115]),
        'wc-mt-pick-bydg' => $custom(__('Oczekuje na odbiór w Salonie Bydgoszcz', 'meditrendy-core'), [36]),
        'wc-mt-partial' => $custom(__('Zamówienie zrealizowane częściowo', 'meditrendy-core'), [11]),
        'wc-completed' => $core(__('Completed', 'woocommerce'), [12, 24, 41]),
        'wc-mt-return' => $custom(__('Zwrot zarejestrowany w systemie', 'meditrendy-core'), [20, 116]),
        'wc-mt-exchange' => $custom(__('Wymiana w trakcie realizacji', 'meditrendy-core'), [21]),
        'wc-mt-exch-pick' => $custom(__('Wymiana – czeka na odbiór przez kuriera', 'meditrendy-core'), [25]),
        'wc-mt-complaint' => $custom(__('Reklamacja – w toku', 'meditrendy-core'), [23]),
        'wc-refunded' => $core(__('Refunded', 'woocommerce'), [38]),
        'wc-mt-unclaimed' => $custom(__('Zamówienie nieodebrane w terminie', 'meditrendy-core'), [28]),
        'wc-mt-parcel-back' => $custom(__('Przesyłka nieodebrana w terminie', 'meditrendy-core'), [13]),
        'wc-cancelled' => $core(__('Cancelled', 'woocommerce'), [26]),
        'wc-failed' => $core(__('Failed', 'woocommerce'), [14]),
    ];

    /**
     * Filter the complete status registry.
     *
     * A third-party extension may add behaviour metadata or insert another
     * status while retaining the same registration and ordering mechanism.
     */
    return apply_filters('meditrendy_order_status_registry', $statuses);
}

/**
 * Register Meditrendy-specific statuses for legacy storage and HPOS.
 */
function meditrendy_register_order_statuses() {
    foreach (meditrendy_order_status_registry() as $status => $definition) {
        if (!empty($definition['core'])) {
            continue;
        }

        $label = (string) $definition['label'];

        register_post_status($status, [
            'label'                     => $label,
            'public'                    => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                $label . ' <span class="count">(%s)</span>',
                $label . ' <span class="count">(%s)</span>',
                'meditrendy-core'
            ),
        ]);
    }
}
add_action('init', 'meditrendy_register_order_statuses', 9);

/**
 * Put core and custom statuses into one operational sequence.
 *
 * Unknown statuses supplied by payment, fulfilment, or other plugins are
 * preserved after the Meditrendy sequence.
 *
 * @param array<string,string> $order_statuses WooCommerce statuses.
 * @return array<string,string>
 */
function meditrendy_order_statuses_in_workflow_order($order_statuses) {
    $ordered = [];

    foreach (meditrendy_order_status_registry() as $status => $definition) {
        $ordered[$status] = isset($order_statuses[$status])
            ? $order_statuses[$status]
            : (string) $definition['label'];
    }

    foreach ($order_statuses as $status => $label) {
        if (!isset($ordered[$status])) {
            $ordered[$status] = $label;
        }
    }

    return $ordered;
}
add_filter('wc_order_statuses', 'meditrendy_order_statuses_in_workflow_order', 20);

/**
 * Return the approved 4real status ID to WooCommerce status mapping.
 *
 * WooCommerce CRUD methods expect status slugs without the "wc-" prefix by
 * default. Pass true when a prefixed WordPress status is needed.
 *
 * @param bool $with_prefix Whether values should retain the wc- prefix.
 * @return array<int,string>
 */
function meditrendy_legacy_order_status_map($with_prefix = false) {
    $mapping = [];

    foreach (meditrendy_order_status_registry() as $status => $definition) {
        foreach ((array) $definition['legacy_ids'] as $legacy_id) {
            $mapping[(int) $legacy_id] = $with_prefix ? $status : substr($status, 3);
        }
    }

    ksort($mapping, SORT_NUMERIC);

    return $mapping;
}

