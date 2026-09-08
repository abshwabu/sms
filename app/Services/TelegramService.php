<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\NotificationDispatch;
use App\Models\School;
use App\Models\TelegramAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramService
{
    /**
     * In-memory record of sent messages during testing.
     * @var array<int, array>
     */
    public static array $sentMessages = [];

    /**
     * Clear recorded sent messages (used in testing).
     */
    public static function clearSentMessages(): void
    {
        self::$sentMessages = [];
    }

    /**
     * Generate or renew a 7-day link code for a user to bind their Telegram account.
     */
    public function generateLinkCode(User $user): array
    {
        $school = $user->school;
        $botUsername = $school?->telegram_bot_username 
            ?: config('services.telegram.bot_username', 'BinaSchoolsBot');

        $linkCode = 'TG-' . strtoupper(Str::random(8));

        $account = TelegramAccount::updateOrCreate(
            [
                'school_id' => $user->school_id,
                'user_id' => $user->id,
            ],
            [
                'link_code' => $linkCode,
                'link_code_expires_at' => Carbon::now()->addDays(7),
                'notifications_enabled' => true,
            ]
        );

        $deepLink = "https://t.me/{$botUsername}?start={$linkCode}";

        return [
            'link_code' => $linkCode,
            'bot_username' => $botUsername,
            'deep_link' => $deepLink,
            'expires_at' => $account->link_code_expires_at->toIso8601String(),
            'is_linked' => $account->is_linked,
            'telegram_username' => $account->telegram_username,
        ];
    }

    /**
     * Link a user's Telegram account by matching their /start <link-code>.
     */
    public function linkAccountByCode(
        string $linkCode,
        string|int $chatId,
        ?string $username = null,
        ?string $firstName = null
    ): TelegramAccount {
        $account = TelegramAccount::withoutGlobalScopes()
            ->where('link_code', trim($linkCode))
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'link_code' => ['Invalid Telegram link code. Please generate a new code from your portal.'],
            ]);
        }

        if (! $account->isCodeValid()) {
            throw ValidationException::withMessages([
                'link_code' => ['This link code has expired. Please generate a new link code.'],
            ]);
        }

        $account->update([
            'telegram_chat_id' => (string) $chatId,
            'telegram_username' => $username,
            'first_name' => $firstName,
            'is_linked' => true,
            'linked_at' => Carbon::now(),
            'notifications_enabled' => true,
        ]);

        $userName = $account->user?->name ?: 'User';
        $schoolName = $account->school?->name ?: 'Bina Schools';

        $welcomeMessage = "👋 <b>Welcome {$userName}!</b>\n\n"
            . "Your Telegram account has been successfully linked to <b>{$schoolName}</b>.\n"
            . "You will now receive announcements, student attendance notices, and academic updates directly here.";

        $this->sendMessage($account->school, $chatId, $welcomeMessage);

        return $account;
    }

    /**
     * Unlink a user's Telegram account.
     */
    public function unlinkAccount(User $user): bool
    {
        $account = $user->telegramAccount;
        if (! $account) {
            return false;
        }

        return $account->update([
            'is_linked' => false,
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'linked_at' => null,
        ]);
    }

    /**
     * Handle incoming Telegram Bot API webhook updates.
     */
    public function handleWebhook(School $school, array $update): array
    {
        $message = $update['message'] ?? null;
        if (! $message) {
            return ['status' => 'ignored', 'reason' => 'No message payload'];
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');
        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;

        if (! $chatId) {
            return ['status' => 'ignored', 'reason' => 'Missing chat ID'];
        }

        // 1. Check for /start <link_code>
        if (Str::startsWith($text, '/start')) {
            $parts = explode(' ', $text);
            $code = $parts[1] ?? null;

            if ($code) {
                try {
                    $account = $this->linkAccountByCode($code, $chatId, $username, $firstName);
                    return [
                        'status' => 'success',
                        'action' => 'linked',
                        'user_id' => $account->user_id,
                    ];
                } catch (\Exception $e) {
                    $this->sendMessage($school, $chatId, "❌ " . $e->getMessage());
                    return [
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                $greeting = "👋 Hello! To link your Bina Schools account, please click 'Connect Telegram' in your school portal and use the link provided.";
                $this->sendMessage($school, $chatId, $greeting);
                return ['status' => 'prompted_for_code'];
            }
        }

        // 2. Check for /stop or /unlink
        if (in_array($text, ['/stop', '/unlink', '/disconnect'])) {
            $account = TelegramAccount::withoutGlobalScopes()
                ->where('telegram_chat_id', (string) $chatId)
                ->first();

            if ($account) {
                $account->update([
                    'is_linked' => false,
                    'telegram_chat_id' => null,
                ]);
                $this->sendMessage($school, $chatId, "ℹ️ Your Telegram account has been disconnected from school notifications.");
                return ['status' => 'unlinked'];
            }
        }

        return ['status' => 'no_action'];
    }

    /**
     * Send a Telegram message via Bot API (or records it during testing).
     */
    public function sendMessage(
        School|int|null $school,
        string|int $chatId,
        string $text,
        string $parseMode = 'HTML'
    ): bool {
        // Record message in mock storage for verification and test assertions
        self::$sentMessages[] = [
            'chat_id' => (string) $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'sent_at' => Carbon::now()->toIso8601String(),
        ];

        // Resolve bot token
        $schoolModel = $school instanceof School ? $school : ($school ? School::find($school) : null);
        $token = $schoolModel?->telegram_bot_token ?: config('services.telegram.bot_token');

        // If in test environment or mock token, skip outbound HTTP network call
        if (app()->environment('testing') || empty($token) || str_starts_with($token, 'fake_') || str_starts_with($token, 'mock_')) {
            return true;
        }

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => (string) $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("Telegram message dispatch failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deliver an announcement to a user's Telegram account with deduplication protection.
     * Acceptance criterion 3: A parent who links their Telegram account receives the same announcement
     * via Telegram as they would via email, without duplicate spam if both channels are enabled.
     */
    public function dispatchAnnouncement(Announcement $announcement, User $recipient): bool
    {
        $account = $recipient->telegramAccount;
        if (! $account || ! $account->is_linked || ! $account->telegram_chat_id || ! $account->notifications_enabled) {
            return false;
        }

        // Check deduplication in database: avoid sending duplicate telegram messages
        $alreadySent = NotificationDispatch::withoutGlobalScopes()
            ->where('school_id', $announcement->school_id)
            ->where('notifiable_type', Announcement::class)
            ->where('notifiable_id', $announcement->id)
            ->where('user_id', $recipient->id)
            ->where('channel', 'telegram')
            ->exists();

        if ($alreadySent) {
            return false; // Skip duplicate send
        }

        $schoolName = $announcement->school?->name ?: 'Bina Schools';
        $authorName = $announcement->author?->name ?: 'School Administration';
        $dateStr = $announcement->published_at ? $announcement->published_at->format('M d, Y H:i') : now()->format('M d, Y');

        $message = "📢 <b>{$schoolName} Announcement</b>\n"
            . "<b>{$announcement->title}</b>\n\n"
            . "{$announcement->body}\n\n"
            . "<i>Published: {$dateStr} by {$authorName}</i>";

        $sent = $this->sendMessage(
            $announcement->school_id,
            $account->telegram_chat_id,
            $message
        );

        if ($sent) {
            NotificationDispatch::create([
                'school_id' => $announcement->school_id,
                'notifiable_type' => Announcement::class,
                'notifiable_id' => $announcement->id,
                'user_id' => $recipient->id,
                'channel' => 'telegram',
                'recipient_address' => $account->telegram_chat_id,
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);
        }

        return $sent;
    }
}
