<?php
if (!defined('ABSPATH')) exit;

/**
 * Diagnostic logging for incoming WooCommerce product REST API requests.
 *
 * The integration which updates stock may not identify itself as Apilo, so all
 * classic WooCommerce product API calls are logged with enough metadata to
 * correlate them without storing API secrets.
 */

define('MEDITRENDY_PRODUCT_REST_LOG_SOURCE', 'meditrendy-product-rest-api');

function meditrendy_product_rest_log_enabled() {
    $enabled = defined('MEDITRENDY_PRODUCT_REST_LOG_ENABLED')
        ? (bool) MEDITRENDY_PRODUCT_REST_LOG_ENABLED
        : true;

    return (bool) apply_filters('meditrendy_product_rest_log_enabled', $enabled);
}

function meditrendy_product_rest_is_product_route(WP_REST_Request $request) {
    $route = '/' . ltrim((string) $request->get_route(), '/');

    // Apilo uses the authenticated WooCommerce REST API, not the public Store API.
    return (bool) preg_match('#^/wc/v[1-9][0-9]*/products(?:/|$)#', $route);
}

function meditrendy_product_rest_is_sensitive_key($key) {
    return (bool) preg_match(
        '/(?:authorization|cookie|consumer_(?:key|secret)|password|passwd|secret|token|api[_-]?key)/i',
        (string) $key
    );
}

function meditrendy_product_rest_redact($value, $key = '') {
    if (meditrendy_product_rest_is_sensitive_key($key)) {
        return '[redacted]';
    }

    if (!is_array($value)) {
        return $value;
    }

    $redacted = [];
    foreach ($value as $child_key => $child_value) {
        $redacted[$child_key] = meditrendy_product_rest_redact($child_value, $child_key);
    }

    return $redacted;
}

function meditrendy_product_rest_credential_fingerprint(WP_REST_Request $request) {
    $consumer_key = (string) $request->get_param('consumer_key');
    $authorization = (string) $request->get_header('authorization');

    if ($consumer_key === '' && stripos($authorization, 'Basic ') === 0) {
        $decoded = base64_decode(substr($authorization, 6), true);
        if (is_string($decoded) && strpos($decoded, ':') !== false) {
            $consumer_key = explode(':', $decoded, 2)[0];
        }
    }

    if ($consumer_key === '' && preg_match('/oauth_consumer_key=["\']?([^,"\'\s]+)/i', $authorization, $matches)) {
        $consumer_key = rawurldecode($matches[1]);
    }

    if ($consumer_key === '') {
        return '';
    }

    return substr(hash_hmac('sha256', $consumer_key, wp_salt('auth')), 0, 12);
}

function meditrendy_product_rest_safe_headers(WP_REST_Request $request) {
    $headers = [];

    foreach ($request->get_headers() as $name => $values) {
        if (meditrendy_product_rest_is_sensitive_key($name)) {
            if (strtolower((string) $name) === 'authorization') {
                $value = is_array($values) ? (string) reset($values) : (string) $values;
                $headers[$name] = preg_match('/^\s*([A-Za-z]+)\s+/', $value, $matches)
                    ? $matches[1] . ' [redacted]'
                    : '[redacted]';
            } else {
                $headers[$name] = '[redacted]';
            }
            continue;
        }

        $headers[$name] = $values;
    }

    return $headers;
}

function meditrendy_product_rest_request_body(WP_REST_Request $request) {
    $body = $request->get_json_params();

    if (!is_array($body)) {
        $body = $request->get_body_params();
    }

    if (!is_array($body) || $body === []) {
        return null;
    }

    $encoded = wp_json_encode(meditrendy_product_rest_redact($body));
    if (!is_string($encoded)) {
        return '[unable to encode request body]';
    }

    $max_bytes = (int) apply_filters('meditrendy_product_rest_log_max_body_bytes', 1048576);
    if ($max_bytes > 0 && strlen($encoded) > $max_bytes) {
        return substr($encoded, 0, $max_bytes) . '...[truncated]';
    }

    return $encoded;
}

function meditrendy_product_rest_logger() {
    return function_exists('wc_get_logger') ? wc_get_logger() : null;
}

function meditrendy_product_rest_log($level, $message, array $context) {
    $logger = meditrendy_product_rest_logger();
    if (!$logger) {
        return;
    }

    $context['source'] = MEDITRENDY_PRODUCT_REST_LOG_SOURCE;
    $logger->log($level, $message, $context);
}

function meditrendy_product_rest_request_context(WP_REST_Request $request, $request_id) {
    $user = wp_get_current_user();

    return [
        'request_id'             => $request_id,
        'method'                 => $request->get_method(),
        'route'                  => $request->get_route(),
        'url_params'             => meditrendy_product_rest_redact($request->get_url_params()),
        'query_params'           => meditrendy_product_rest_redact($request->get_query_params()),
        'body'                   => meditrendy_product_rest_request_body($request),
        'headers'                => meditrendy_product_rest_safe_headers($request),
        'remote_ip'              => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        'credential_fingerprint' => meditrendy_product_rest_credential_fingerprint($request),
        'user_id'                => (int) $user->ID,
        'user_login'             => $user->exists() ? $user->user_login : '',
    ];
}

function meditrendy_product_rest_log_request($result, WP_REST_Server $server, WP_REST_Request $request) {
    if (!meditrendy_product_rest_log_enabled() || !meditrendy_product_rest_is_product_route($request)) {
        return $result;
    }

    $request_id = wp_generate_uuid4();
    $GLOBALS['meditrendy_product_rest_requests'][spl_object_id($request)] = [
        'id'      => $request_id,
        'started' => microtime(true),
    ];

    meditrendy_product_rest_log(
        'info',
        'Incoming product REST API request',
        meditrendy_product_rest_request_context($request, $request_id)
    );

    return $result;
}
add_filter('rest_pre_dispatch', 'meditrendy_product_rest_log_request', 5, 3);

function meditrendy_product_rest_log_response($response, WP_REST_Server $server, WP_REST_Request $request) {
    if (!meditrendy_product_rest_log_enabled() || !meditrendy_product_rest_is_product_route($request)) {
        return $response;
    }

    $key = spl_object_id($request);
    $tracked = $GLOBALS['meditrendy_product_rest_requests'][$key] ?? [];
    unset($GLOBALS['meditrendy_product_rest_requests'][$key]);

    // Authentication failures are returned before rest_pre_dispatch is reached.
    // Log their request details here so broken/expired Apilo credentials remain visible.
    if (empty($tracked)) {
        $tracked = [
            'id'      => wp_generate_uuid4(),
            'started' => null,
        ];
        $request_context = meditrendy_product_rest_request_context($request, $tracked['id']);
        $request_context['authentication_failed_before_dispatch'] = true;
        meditrendy_product_rest_log('info', 'Incoming product REST API request', $request_context);
    }

    $rest_response = rest_ensure_response($response);
    $data = $rest_response->get_data();
    $context = [
        'request_id'  => $tracked['id'] ?? '',
        'method'      => $request->get_method(),
        'route'       => $request->get_route(),
        'status'      => $rest_response->get_status(),
        'duration_ms' => !empty($tracked['started'])
            ? (int) round((microtime(true) - $tracked['started']) * 1000)
            : null,
    ];

    if (is_array($data) && isset($data['code'])) {
        $context['error_code'] = sanitize_key((string) $data['code']);
        $context['error_message'] = isset($data['message']) ? sanitize_text_field((string) $data['message']) : '';
    }

    $level = $rest_response->get_status() >= 400 ? 'error' : 'info';
    meditrendy_product_rest_log($level, 'Completed product REST API request', $context);

    return $response;
}
add_filter('rest_post_dispatch', 'meditrendy_product_rest_log_response', 999, 3);
