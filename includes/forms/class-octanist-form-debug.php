<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug logging for form-capture pipeline.
 *
 * All output is gated on WP_DEBUG. Nothing is written in production unless the
 * site owner has explicitly turned WP_DEBUG on. Intended to be tailed via
 * `npm run logs:octanist` during plugin-by-plugin development.
 *
 * Every line is prefixed with `[octanist]` so it's easy to grep out of the
 * noisy wp-debug.log.
 */
class Octanist_Form_Debug
{
    public static function enabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * The raw hook arguments WordPress handed us. We log class names and array
     * shape, not full dumps, a Gravity Forms $form array is hundreds of lines.
     */
    public static function log_raw(string $slug, array $args): void
    {
        if (!self::enabled()) {
            return;
        }
        $shapes = array_map([self::class, 'describe'], $args);
        self::log($slug, 'raw args: ' . wp_json_encode($shapes));
    }

    public static function log_extracted(string $slug, array $extracted): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log($slug, 'extracted: ' . wp_json_encode([
            'form_id' => $extracted['form_id'] ?? null,
            'sid'     => $extracted['sid'] ?? null,
            'fields'  => $extracted['fields'] ?? [],
        ]));
    }

    public static function log_payload(string $slug, array $payload): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log($slug, 'payload: ' . wp_json_encode($payload));
    }

    public static function log(string $slug, string $message): void
    {
        if (!self::enabled()) {
            return;
        }
        // error_log routes to WP_DEBUG_LOG when enabled (the wp-env default).
        error_log(sprintf('[octanist][%s] %s', $slug, $message));
    }

    /**
     * Cheap structural summary of a hook arg, safe for logging even when the
     * underlying object is huge (Gravity $form, Elementor record).
     */
    private static function describe($value): string
    {
        if (is_object($value)) {
            return 'object<' . get_class($value) . '>';
        }
        if (is_array($value)) {
            $keys = array_slice(array_keys($value), 0, 10);
            return 'array{' . count($value) . '}<' . implode(',', array_map('strval', $keys)) . '>';
        }
        if (is_string($value)) {
            return 'string(' . strlen($value) . ')';
        }
        return gettype($value);
    }
}
