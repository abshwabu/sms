<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class SmsNotificationHook
{
    /**
     * Optional custom callback handlers for SMS delivery.
     */
    protected static array $handlers = [];

    /**
     * Register a callback to be triggered when an SMS notification is dispatched.
     */
    public static function registerHandler(callable $handler): void
    {
        static::$handlers[] = $handler;
    }

    /**
     * Dispatch SMS notification to user.
     */
    public static function dispatch(User $recipient, string $message, array $context = []): bool
    {
        $phone = $recipient->phone;
        if (! $phone) {
            return false;
        }

        Log::info(sprintf('[SmsNotificationHook] Dispatched SMS to user %d (%s): %s', $recipient->id, $phone, $message), $context);

        foreach (static::$handlers as $handler) {
            try {
                $handler($recipient, $phone, $message, $context);
            } catch (\Throwable $e) {
                Log::error('[SmsNotificationHook] Handler execution failed: ' . $e->getMessage());
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
