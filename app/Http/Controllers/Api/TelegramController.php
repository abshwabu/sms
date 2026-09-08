<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkTelegramRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\School;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TelegramService $telegramService
    ) {}

    /**
     * Get current user's Telegram integration status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->telegramAccount;

        return $this->respondWithSuccess([
            'is_linked' => (bool) ($account?->is_linked),
            'telegram_username' => $account?->telegram_username,
            'first_name' => $account?->first_name,
            'linked_at' => $account?->linked_at?->toIso8601String(),
            'notifications_enabled' => (bool) ($account?->notifications_enabled ?? true),
        ], 'Telegram link status retrieved.');
    }

    /**
     * Generate or refresh a link code to connect Telegram via /start <link_code>.
     */
    public function generateLinkCode(Request $request): JsonResponse
    {
        $payload = $this->telegramService->generateLinkCode($request->user());

        return $this->respondWithSuccess($payload, 'Telegram link code generated.');
    }

    /**
     * Programmatically link account with a code and Telegram chat ID.
     */
    public function linkAccount(LinkTelegramRequest $request): JsonResponse
    {
        $account = $this->telegramService->linkAccountByCode(
            $request->input('link_code'),
            $request->input('telegram_chat_id'),
            $request->input('telegram_username'),
            $request->input('first_name')
        );

        return $this->respondWithSuccess([
            'is_linked' => $account->is_linked,
            'telegram_username' => $account->telegram_username,
            'linked_at' => $account->linked_at?->toIso8601String(),
        ], 'Telegram account successfully linked.');
    }

    /**
     * Disconnect/unlink Telegram account.
     */
    public function unlinkAccount(Request $request): JsonResponse
    {
        $this->telegramService->unlinkAccount($request->user());

        return $this->respondWithSuccess(null, 'Telegram account unlinked successfully.');
    }

    /**
     * Webhook receiver for Telegram Bot API updates.
     */
    public function webhook(Request $request, School $school): JsonResponse
    {
        $update = $request->all();
        $result = $this->telegramService->handleWebhook($school, $update);

        return response()->json($result);
    }
}
