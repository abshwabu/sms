<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushNotificationHook
{
    /**
     * Optional custom callback handlers for mobile push notifications.
     */
    protected static array $handlers = [];

    /**
     * Register a callback to be triggered when a push notification is dispatched.
     */
    public static function registerHandler(callable $handler): void
    {
        static::$handlers[] = $handler;
    }

    /**
     * Dispatch mobile push notification to user.
     */
    public static function dispatch(User $recipient, string $title, string $body, array $payload = []): bool
    {
        Log::info(sprintf('[PushNotificationHook] Dispatched Push Notification to user %d: %s - %s', $recipient->id, $title, $body), $payload);

        foreach (static::$handlers as $handler) {
            try {
                $handler($recipient, $title, $body, $payload);
            } catch (\Throwable $e) {
                Log::error('[PushNotificationHook] Handler execution failed: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Clear registered handlers (useful for tests).
     */
    public static function clearHandlers(): void
    {
        static::$handlers = [];
    }
}
