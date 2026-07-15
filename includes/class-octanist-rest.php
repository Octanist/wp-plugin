<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Rest
{
    const NAMESPACE  = 'oct';
    const PIXEL      = 'p';
    const COLLECT    = 'e';
    const MAX_BODY   = 65536; // 64 KB

    public static function register(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::PIXEL, [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'serve_pixel'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::COLLECT, [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'collect'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function pixel_url(): string
    {
        return rest_url(self::NAMESPACE . '/' . self::PIXEL);
    }

    public static function collect_url(): string
    {
        return rest_url(self::NAMESPACE . '/' . self::COLLECT);
    }

    public static function serve_pixel(WP_REST_Request $request)
    {
        if ($request->get_param('cookie') === '1') {
            self::ensure_cid_cookie();
            return new WP_REST_Response(null, 204);
        }

        $cache = Octanist_Api::get_pixel_cache();

        if (is_array($cache) && isset($cache['body'])) {
            $is_fresh = Octanist_Api::pixel_cache_is_fresh($cache);
            if (!$is_fresh && class_exists('Octanist_Queue')) {
                Octanist_Queue::schedule_pixel_refresh();
            }

            $if_none_match = $request->get_header('if_none_match');
            if ($if_none_match && !empty($cache['etag']) && trim($if_none_match, '"') === trim($cache['etag'], '"')) {
                return self::respond_304($cache, $is_fresh ? Octanist_Api::PIXEL_CACHE_TTL : 60);
            }

            $max_age = $is_fresh ? Octanist_Api::PIXEL_CACHE_TTL : 60;
            return self::respond_pixel($cache, $max_age);
        }

        if (class_exists('Octanist_Queue')) {
            Octanist_Queue::schedule_pixel_refresh();
        }

        return self::respond_stub();
    }

    private static function ensure_cid_cookie(): void
    {
        if (!empty($_COOKIE['octa_cid']) && is_string($_COOKIE['octa_cid'])) {
            return;
        }

        $cid = wp_generate_uuid4();
        $_COOKIE['octa_cid'] = $cid;

        setcookie('octa_cid', $cid, [
            'expires'  => time() + (2 * YEAR_IN_SECONDS),
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private static function respond_pixel(array $cache, int $max_age)
    {
        self::send_headers([
            'Content-Type'  => $cache['content_type'],
            'Cache-Control' => 'public, max-age=' . $max_age . ', s-maxage=' . $max_age,
            'ETag'          => $cache['etag'],
        ]);
        echo $cache['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function respond_304(array $cache, int $max_age)
    {
        status_header(304);
        self::send_headers([
            'ETag'          => $cache['etag'],
            'Cache-Control' => 'public, max-age=' . $max_age,
        ]);
        exit;
    }

    private static function respond_stub()
    {
        self::send_headers([
            'Content-Type'  => 'application/javascript',
            'Cache-Control' => 'public, max-age=30',
        ]);
        echo "/* Octanist: pixel cache warming */\n";
        exit;
    }

    public static function collect(WP_REST_Request $request)
    {
        // Size guard.
        $raw = $request->get_body();
        if (strlen($raw) > self::MAX_BODY) {
            return new WP_REST_Response(null, 413);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return new WP_REST_Response(null, 400);
        }

        $settings = Octanist_Settings::get();
        if (!empty($settings['measurement_id']) && empty($payload['mid'])) {
            $payload['mid'] = $settings['measurement_id'];
        }

        self::ensure_cid_cookie();

        $signals = Octanist_Api::collect_client_signals();
        $cookies = Octanist_Api::filter_forwardable_cookies($_COOKIE ?? []);

        if (Octanist_Api::pixel_delivery_is_paused()) {
            $response = new WP_REST_Response(null, 204);
            $response->header('Server-Timing', 'octanist_upstream;dur=0;desc="circuit-open-drop"');
            return $response;
        }

        $upstream_started = microtime(true);
        $response = Octanist_Api::forward_event($payload, [
            'blocking'         => true,
            'timeout'          => Octanist_Api::COLLECT_TIMEOUT,
            'client_signals'   => $signals,
            'cookies'          => $cookies,
            'source'           => 'pixel_proxy',
            'queue_on_failure' => false,
        ]);
        Octanist_Api::record_pixel_delivery_result($response);

        $upstream_duration = max(0, (microtime(true) - $upstream_started) * 1000);
        $rest_response = new WP_REST_Response(null, 204);
        $rest_response->header(
            'Server-Timing',
            'octanist_upstream;dur=' . number_format($upstream_duration, 1, '.', '')
        );
        return $rest_response;
    }

    private static function send_headers(array $headers): void
    {
        foreach ($headers as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            header($name . ': ' . $value);
        }
    }
}
