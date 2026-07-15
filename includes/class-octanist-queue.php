<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Queue
{
    const OPTION       = 'octanist_event_queue';
    const LOCK_OPTION  = 'octanist_event_queue_lock';
    const CRON_HOOK    = 'octanist_retry_event_queue';
    const RETRY_WAKE_HOOK = 'octanist_wake_event_queue';
    const PIXEL_REFRESH_HOOK = 'octanist_refresh_pixel_cache';
    const PIXEL_REFRESH_LOCK = 'octanist_pixel_refresh_pending';
    const MAX_FORM_ITEMS = 100;
    const MAX_AGE      = DAY_IN_SECONDS;
    const MAX_ATTEMPTS = 12;
    const MAX_RETRY_BATCH = 3;
    const MAX_RETRY_RUNTIME = 3;
    const RETRY_BASE_DELAY = MINUTE_IN_SECONDS;
    const LOCK_TTL = 5;
    const LOCK_ATTEMPTS = 20;

    public static function register(): void
    {
        add_action(self::CRON_HOOK, [__CLASS__, 'retry']);
        add_action(self::RETRY_WAKE_HOOK, [__CLASS__, 'retry']);
        add_action(self::PIXEL_REFRESH_HOOK, [__CLASS__, 'refresh_pixel_cache']);
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
        }

        $timestamp = wp_next_scheduled(self::RETRY_WAKE_HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::RETRY_WAKE_HOOK);
            $timestamp = wp_next_scheduled(self::RETRY_WAKE_HOOK);
        }

        $timestamp = wp_next_scheduled(self::PIXEL_REFRESH_HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::PIXEL_REFRESH_HOOK);
            $timestamp = wp_next_scheduled(self::PIXEL_REFRESH_HOOK);
        }
    }

    public static function count(): int
    {
        return count(self::get_items());
    }

    public static function enqueue(array $payload, string $source, string $error = '', array $signals = [], array $cookies = []): void
    {
        if (($payload['type'] ?? '') !== 'form_submit') {
            return;
        }

        $item = [
            'id'         => wp_generate_uuid4(),
            'payload'    => $payload,
            'source'     => $source,
            'signals'    => $signals,
            'cookies'    => $cookies,
            'created_at' => time(),
            'attempts'   => 0,
            'next_attempt_at' => time(),
            'last_error' => substr($error, 0, 500),
        ];

        if (!self::store_item($item)) {
            Octanist_Health::record_failure(
                'Unable to persist failed form submission for retry; the queue is full or locked',
                'queue'
            );
            return;
        }

        self::schedule();
        self::schedule_retry_wake(time() + MINUTE_IN_SECONDS);
    }

    public static function retry(): void
    {
        $items = self::claim_items();
        if (empty($items)) {
            return;
        }

        $started = microtime(true);
        $now = time();
        foreach ($items as $item) {
            $attempts = isset($item['attempts']) ? (int) $item['attempts'] : 0;
            if ((microtime(true) - $started) >= self::MAX_RETRY_RUNTIME) {
                $item['claimed_until'] = 0;
                self::replace_item($item);
                continue;
            }

            $source = isset($item['source']) && is_string($item['source']) ? $item['source'] : 'queued_event';
            $signals = isset($item['signals']) && is_array($item['signals']) ? $item['signals'] : [];
            $cookies = isset($item['cookies']) && is_array($item['cookies']) ? $item['cookies'] : [];
            $response = Octanist_Api::forward_event($item['payload'], [
                'blocking'         => true,
                'timeout'          => Octanist_Api::RETRY_TIMEOUT,
                'client_signals'   => $signals,
                'cookies'          => $cookies,
                'source'           => $source,
                'queue_on_failure' => false,
            ]);

            if (self::is_success($response)) {
                self::remove_item((string) $item['id']);
                continue;
            }

            $item['attempts'] = $attempts + 1;
            $item['last_error'] = self::response_error($response);
            $item['next_attempt_at'] = $now + self::retry_delay($item['attempts']);
            $item['claimed_until'] = 0;
            self::replace_item($item);
        }

        $remaining = self::get_items();
        if (!empty($remaining)) {
            $next_attempts = array_map(static function ($item): int {
                return isset($item['next_attempt_at']) ? (int) $item['next_attempt_at'] : 0;
            }, $remaining);
            $next_attempt = min($next_attempts);
            self::schedule_retry_wake(max(time() + MINUTE_IN_SECONDS, $next_attempt));
        }
    }

    public static function schedule_pixel_refresh(): void
    {
        if (get_transient(self::PIXEL_REFRESH_LOCK)) {
            return;
        }

        set_transient(self::PIXEL_REFRESH_LOCK, '1', MINUTE_IN_SECONDS);
        if (!wp_next_scheduled(self::PIXEL_REFRESH_HOOK)) {
            wp_schedule_single_event(time() + 1, self::PIXEL_REFRESH_HOOK);
        }
    }

    public static function refresh_pixel_cache(): void
    {
        delete_transient(self::PIXEL_REFRESH_LOCK);

        $fresh = Octanist_Api::fetch_pixel();
        if (is_wp_error($fresh)) {
            Octanist_Health::record_failure($fresh->get_error_message(), 'pixel_refresh');
            return;
        }

        Octanist_Api::store_pixel_cache($fresh);
        Octanist_Health::record_success('pixel_refresh');
    }

    private static function get_items(): array
    {
        $items = get_option(self::OPTION, []);
        return is_array($items) ? $items : [];
    }

    private static function claim_items(): array
    {
        $token = self::acquire_lock();
        if ($token === '') {
            self::schedule_retry_wake(time() + MINUTE_IN_SECONDS);
            return [];
        }

        $now = time();
        $items = self::get_items();
        $claimed = [];
        $kept = [];

        foreach ($items as $item) {
            if (
                empty($item['id']) ||
                empty($item['payload']) ||
                !is_array($item['payload']) ||
                ($item['payload']['type'] ?? '') !== 'form_submit'
            ) {
                continue;
            }

            $created = isset($item['created_at']) ? (int) $item['created_at'] : 0;
            $attempts = isset($item['attempts']) ? (int) $item['attempts'] : 0;
            if (($created > 0 && $now - $created > self::MAX_AGE) || $attempts >= self::MAX_ATTEMPTS) {
                continue;
            }

            $next_attempt = isset($item['next_attempt_at']) ? (int) $item['next_attempt_at'] : 0;
            $claimed_until = isset($item['claimed_until']) ? (int) $item['claimed_until'] : 0;
            if (
                count($claimed) < self::MAX_RETRY_BATCH &&
                $next_attempt <= $now &&
                $claimed_until <= $now
            ) {
                $item['claimed_until'] = $now + max(MINUTE_IN_SECONDS, Octanist_Api::RETRY_TIMEOUT * 2);
                $claimed[] = $item;
            }

            $kept[] = $item;
        }

        update_option(self::OPTION, array_values($kept), false);
        self::release_lock($token);
        return $claimed;
    }

    private static function store_item(array $item): bool
    {
        $token = self::acquire_lock();
        if ($token === '') {
            return false;
        }

        $items = array_values(array_filter(self::get_items(), static function ($existing): bool {
            return is_array($existing) &&
                isset($existing['payload']) &&
                is_array($existing['payload']) &&
                ($existing['payload']['type'] ?? '') === 'form_submit';
        }));

        if (count($items) >= self::MAX_FORM_ITEMS) {
            self::release_lock($token);
            return false;
        }

        $items[] = $item;

        update_option(self::OPTION, array_values($items), false);
        self::release_lock($token);
        return true;
    }

    private static function replace_item(array $replacement): void
    {
        $token = self::acquire_lock();
        if ($token === '') {
            self::schedule_retry_wake(time() + MINUTE_IN_SECONDS);
            return;
        }

        $items = self::get_items();
        foreach ($items as $index => $item) {
            if (($item['id'] ?? '') === ($replacement['id'] ?? '')) {
                $items[$index] = $replacement;
                break;
            }
        }

        update_option(self::OPTION, array_values($items), false);
        self::release_lock($token);
    }

    private static function remove_item(string $id): void
    {
        $token = self::acquire_lock();
        if ($token === '') {
            self::schedule_retry_wake(time() + MINUTE_IN_SECONDS);
            return;
        }

        $items = array_filter(self::get_items(), static function ($item) use ($id): bool {
            return !is_array($item) || ($item['id'] ?? '') !== $id;
        });
        update_option(self::OPTION, array_values($items), false);
        self::release_lock($token);
    }

    private static function acquire_lock(): string
    {
        $token = wp_generate_uuid4();
        for ($attempt = 0; $attempt < self::LOCK_ATTEMPTS; $attempt++) {
            $lock = [
                'token'      => $token,
                'expires_at' => microtime(true) + self::LOCK_TTL,
            ];
            if (add_option(self::LOCK_OPTION, $lock, '', false)) {
                return $token;
            }

            $existing = get_option(self::LOCK_OPTION, []);
            if (!is_array($existing) || (float) ($existing['expires_at'] ?? 0) < microtime(true)) {
                delete_option(self::LOCK_OPTION);
                continue;
            }

            usleep(5000);
        }

        return '';
    }

    private static function release_lock(string $token): void
    {
        $lock = get_option(self::LOCK_OPTION, []);
        if (is_array($lock) && hash_equals((string) ($lock['token'] ?? ''), $token)) {
            delete_option(self::LOCK_OPTION);
        }
    }

    private static function is_success($response): bool
    {
        if (is_wp_error($response) || !is_array($response)) {
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    private static function response_error($response): string
    {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }
        if (is_array($response)) {
            return 'HTTP ' . wp_remote_retrieve_response_code($response);
        }
        return 'Unknown forwarding error';
    }

    private static function retry_delay(int $attempts): int
    {
        $delay = self::RETRY_BASE_DELAY * (2 ** max(0, $attempts - 1));
        return min($delay, HOUR_IN_SECONDS);
    }

    private static function schedule_retry_wake(int $timestamp): void
    {
        $scheduled = wp_next_scheduled(self::RETRY_WAKE_HOOK);
        if ($scheduled && $scheduled <= $timestamp) {
            return;
        }

        if ($scheduled) {
            wp_unschedule_event($scheduled, self::RETRY_WAKE_HOOK);
        }

        wp_schedule_single_event($timestamp, self::RETRY_WAKE_HOOK);
    }
}
