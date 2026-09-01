<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Api
{
    const COOKIE_PREFIX     = 'octa_';
    const PIXEL_PATH        = '/p';
    const EVENT_PATH        = '/e';
    const ASSIGN_PATH       = '/call-tracking/assign';
    const PIXEL_TIMEOUT     = 1;
    const COLLECT_TIMEOUT   = 1;
    const ASSIGN_TIMEOUT    = 8;
    const FORM_TIMEOUT      = 10;
    const RETRY_TIMEOUT     = 3;
    const FORWARD_TIMEOUT   = 1;
    const PIXEL_FAILURE_OPTION = 'octanist_pixel_delivery_failures';
    const PIXEL_CIRCUIT_TRANSIENT = 'octanist_pixel_delivery_paused';
    const PIXEL_CIRCUIT_THRESHOLD = 3;
    const PIXEL_CIRCUIT_COOLDOWN = MINUTE_IN_SECONDS;
    const PIXEL_CACHE_OPTION = 'octanist_pixel_js';
    const PIXEL_CACHE_LEGACY_KEY = 'octanist_pixel_js';
    const PIXEL_CACHE_TTL   = 5 * MINUTE_IN_SECONDS;

    public static function collect_client_signals(): array
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return [
            'ip'              => $ip,
            'country'         => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '',
            'ua'              => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer'         => $_SERVER['HTTP_REFERER'] ?? '',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        ];
    }

    public static function filter_forwardable_cookies(array $cookies): array
    {
        $out = [];
        foreach ($cookies as $name => $value) {
            if (strpos($name, self::COOKIE_PREFIX) === 0) {
                $out[$name] = $value;
            }
        }
        return $out;
    }

    /**
     * Forward an event to the upstream /e endpoint.
     *
     * $opts:
     *   - blocking (bool)            default false (fire-and-forget)
     *   - client_signals (array)     from collect_client_signals()
     *   - cookies (array)            name => value (already filtered)
     *   - source (string)            for health tracking ('wordpress', 'pixel_proxy', ...)
     *   - queue_on_failure (bool)    enqueue blocking failures for retry
     *   - timeout (float)            request timeout in seconds
     *
     * Returns the wp_remote_post response array when blocking, otherwise null.
     */
    public static function forward_event(array $payload, array $opts = [])
    {
        $blocking = !empty($opts['blocking']);
        $source   = $opts['source'] ?? ($payload['source'] ?? 'unknown');
        $signals  = $opts['client_signals'] ?? [];
        $cookies  = $opts['cookies'] ?? [];
        $queue_on_failure = !empty($opts['queue_on_failure']);
        $timeout = isset($opts['timeout'])
            ? max(0.1, (float) $opts['timeout'])
            : ($blocking ? self::COLLECT_TIMEOUT : self::FORWARD_TIMEOUT);

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($signals['ip'])) {
            $headers['X-Forwarded-For']           = $signals['ip'];
            $headers['X-Octanist-Client-IP']      = $signals['ip'];
        }
        if (!empty($signals['country'])) {
            $headers['X-Octanist-Client-Country'] = $signals['country'];
        }
        if (!empty($signals['ua'])) {
            $headers['X-Octanist-Client-UA']      = $signals['ua'];
        }
        if (!empty($signals['referer'])) {
            $headers['X-Octanist-Client-Referer'] = $signals['referer'];
        }
        if (!empty($signals['accept_language'])) {
            $headers['X-Octanist-Client-Accept-Language'] = $signals['accept_language'];
        }

        if (!empty($cookies)) {
            $parts = [];
            foreach ($cookies as $name => $value) {
                $parts[] = $name . '=' . $value;
            }
            $headers['Cookie'] = implode('; ', $parts);
        }

        $args = [
            'method'   => 'POST',
            'timeout'  => $timeout,
            'redirection' => 0,
            'blocking' => $blocking,
            'headers'  => $headers,
            'body'     => wp_json_encode($payload),
            'limit_response_size' => 1024,
        ];

        $response = wp_remote_post(OCTANIST_UPSTREAM . self::EVENT_PATH, $args);

        if (!$blocking) {
            // Non-blocking: we can't know if it succeeded. Optimistically mark success.
            Octanist_Health::record_success($source);
            return null;
        }

        if (is_wp_error($response)) {
            Octanist_Health::record_failure($response->get_error_message(), $source);
            if ($queue_on_failure && class_exists('Octanist_Queue')) {
                Octanist_Queue::enqueue($payload, $source, $response->get_error_message(), $signals, $cookies);
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            Octanist_Health::record_success($source);
        } else {
            Octanist_Health::record_failure('HTTP ' . $code, $source);
            if ($queue_on_failure && class_exists('Octanist_Queue')) {
                Octanist_Queue::enqueue($payload, $source, 'HTTP ' . $code, $signals, $cookies);
            }
        }

        return $response;
    }

    /**
     * Forward a DNI assignment request to the upstream /call-tracking/assign endpoint.
     * Blocking: the pixel needs the leased number in the same request.
     */
    public static function forward_call_tracking_assignment(array $payload, array $signals = [])
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($signals['ip'])) {
            $headers['X-Forwarded-For']           = $signals['ip'];
            $headers['X-Octanist-Client-IP']      = $signals['ip'];
        }
        if (!empty($signals['country'])) {
            $headers['X-Octanist-Client-Country'] = $signals['country'];
        }
        if (!empty($signals['ua'])) {
            $headers['X-Octanist-Client-UA']      = $signals['ua'];
        }

        return wp_remote_post(OCTANIST_UPSTREAM . self::ASSIGN_PATH, [
            'method'              => 'POST',
            'timeout'             => self::ASSIGN_TIMEOUT,
            'redirection'         => 0,
            'blocking'            => true,
            'headers'             => $headers,
            'body'                => wp_json_encode($payload),
            'limit_response_size' => 4096,
        ]);
    }

    public static function pixel_delivery_is_paused(): bool
    {
        return (bool) get_transient(self::PIXEL_CIRCUIT_TRANSIENT);
    }

    public static function record_pixel_delivery_result($response): void
    {
        if (self::is_success_response($response)) {
            delete_option(self::PIXEL_FAILURE_OPTION);
            delete_transient(self::PIXEL_CIRCUIT_TRANSIENT);
            return;
        }

        $failures = (int) get_option(self::PIXEL_FAILURE_OPTION, 0) + 1;
        if ($failures >= self::PIXEL_CIRCUIT_THRESHOLD) {
            delete_option(self::PIXEL_FAILURE_OPTION);
            set_transient(
                self::PIXEL_CIRCUIT_TRANSIENT,
                '1',
                self::PIXEL_CIRCUIT_COOLDOWN
            );
            return;
        }

        update_option(self::PIXEL_FAILURE_OPTION, $failures, false);
    }

    /**
     * Fetch pixel.js from the Worker. Only background refresh jobs should call this.
     * Returns ['body' => string, 'etag' => string, 'content_type' => string] or WP_Error.
     */
    public static function fetch_pixel()
    {
        $response = wp_remote_get(OCTANIST_UPSTREAM . self::PIXEL_PATH, [
            'timeout' => self::PIXEL_TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('octanist_pixel_upstream', 'Pixel upstream returned HTTP ' . $code);
        }

        return [
            'body'         => wp_remote_retrieve_body($response),
            'etag'         => wp_remote_retrieve_header($response, 'etag'),
            'content_type' => wp_remote_retrieve_header($response, 'content-type') ?: 'application/javascript',
        ];
    }

    public static function get_pixel_cache(): array
    {
        $cache = get_option(self::PIXEL_CACHE_OPTION, []);
        if (is_array($cache) && isset($cache['body'])) {
            return $cache;
        }

        $legacy_cache = get_transient(self::PIXEL_CACHE_LEGACY_KEY);
        if (is_array($legacy_cache) && isset($legacy_cache['body'])) {
            update_option(self::PIXEL_CACHE_OPTION, $legacy_cache, false);
            return $legacy_cache;
        }

        return [];
    }

    public static function store_pixel_cache(array $fresh): void
    {
        $body = isset($fresh['body']) ? (string) $fresh['body'] : '';
        if ($body === '') {
            return;
        }

        $etag = !empty($fresh['etag']) ? (string) $fresh['etag'] : '"' . md5($body) . '"';
        $cache_entry = [
            'body'         => $body,
            'etag'         => $etag,
            'content_type' => !empty($fresh['content_type']) ? (string) $fresh['content_type'] : 'application/javascript',
            'cached_at'    => time(),
        ];

        update_option(self::PIXEL_CACHE_OPTION, $cache_entry, false);
    }

    public static function pixel_cache_is_fresh(array $cache): bool
    {
        $cached_at = isset($cache['cached_at']) ? (int) $cache['cached_at'] : 0;
        return $cached_at > 0 && (time() - $cached_at) < self::PIXEL_CACHE_TTL;
    }

    private static function is_success_response($response): bool
    {
        if (is_wp_error($response) || !is_array($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }
}
