<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasApiResponse;
use App\Http\Requests\UpdateSchoolTelegramRequest;
use App\Models\School;
use App\Models\User;
use App\Services\TelegramService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolAdminController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TenantManager $tenantManager,
        protected TelegramService $telegramService
    ) {}

    /**
     * Get list of users belonging to the active school.
     * Guarded: School Admin / Super Admin only.
     */
    public function users(): JsonResponse
    {
        $schoolId = $this->tenantManager->getTenantId();

        $users = User::where('school_id', $schoolId)
            ->select(['id', 'school_id', 'name', 'email', 'phone', 'role', 'status', 'last_login_at', 'created_at'])
            ->orderBy('name')
            ->get();

        return $this->respondWithSuccess($users, 'School users retrieved successfully.');
    }

    /**
     * Get school administration dashboard stats.
     * Guarded: School Admin / Super Admin only.
     */
    public function stats(): JsonResponse
    {
        $schoolId = $this->tenantManager->getTenantId();

        $stats = [
            'total_users' => User::where('school_id', $schoolId)->count(),
            'teachers' => User::where('school_id', $schoolId)->where('role', 'teacher')->count(),
            'students' => User::where('school_id', $schoolId)->where('role', 'student')->count(),
            'parents' => User::where('school_id', $schoolId)->where('role', 'parent')->count(),
        ];

        return $this->respondWithSuccess($stats, 'School admin statistics retrieved successfully.');
    }

    /**
     * Get Telegram bot configuration for the active school.
     */
    public function getTelegramSettings(Request $request): JsonResponse
    {
        $school = $this->resolveActiveSchool($request);
        if (! $school) {
            return $this->respondWithError('No active school context found.', 'NOT_FOUND', 404);
        }

        return $this->respondWithSuccess([
            'school_id' => $school->id,
            'school_name' => $school->name,
            'telegram_bot_username' => $school->telegram_bot_username,
            'telegram_bot_token' => $school->masked_telegram_bot_token,
            'has_telegram_bot' => $school->hasTelegramBot(),
            'webhook_url' => $school->telegram_webhook_url,
        ], 'School Telegram bot settings retrieved successfully.');
    }

    /**
     * Update Telegram bot configuration for the active school.
     */
    public function updateTelegramSettings(UpdateSchoolTelegramRequest $request): JsonResponse
    {
        $school = $this->resolveActiveSchool($request);
        if (! $school) {
            return $this->respondWithError('No active school context found.', 'NOT_FOUND', 404);
        }

        $updateData = [];

        if ($request->has('telegram_bot_username')) {
            $updateData['telegram_bot_username'] = $request->input('telegram_bot_username');
        }

        if ($request->has('telegram_bot_token')) {
            $tokenInput = $request->input('telegram_bot_token');
            if ($tokenInput !== null && !str_contains($tokenInput, '•')) {
                $updateData['telegram_bot_token'] = $tokenInput;
            } elseif ($tokenInput === null) {
                $updateData['telegram_bot_token'] = null;
            }
        }

        if (!empty($updateData)) {
            $school->update($updateData);
            $school->refresh();
        }

        $webhookResult = null;
        if ($request->boolean('register_webhook') && $school->telegram_bot_token) {
            $webhookResult = $this->telegramService->registerWebhook($school);
        }

        return $this->respondWithSuccess([
            'school_id' => $school->id,
            'school_name' => $school->name,
            'telegram_bot_username' => $school->telegram_bot_username,
            'telegram_bot_token' => $school->masked_telegram_bot_token,
            'has_telegram_bot' => $school->hasTelegramBot(),
            'webhook_url' => $school->telegram_webhook_url,
            'webhook_result' => $webhookResult,
        ], "Telegram bot settings for '{$school->name}' updated successfully.");
    }

    /**
     * Test connection to Telegram API using current school's token or a candidate token.
     */
    public function testTelegramSettings(Request $request): JsonResponse
    {
        $school = $this->resolveActiveSchool($request);
        $token = $request->input('token');

        if (!$token || str_contains($token, '•')) {
            $token = $school?->telegram_bot_token;
        }

        if (empty($token)) {
            return $this->respondWithError('Telegram bot token is required for testing.', 'VALIDATION_ERROR', 422, [
                'token' => ['Please enter a Telegram bot token to test.']
            ]);
        }

        $result = $this->telegramService->testBotToken($token);

        if (!($result['ok'] ?? false)) {
            return $this->respondWithError($result['description'] ?? 'Telegram API test failed.', 'VALIDATION_ERROR', 422, $result);
        }

        return $this->respondWithSuccess($result, 'Telegram bot connection verified successfully.');
    }

    /**
     * Register or re-register the active school's webhook URL with Telegram.
     */
    public function registerTelegramWebhook(Request $request): JsonResponse
    {
        $school = $this->resolveActiveSchool($request);
        if (! $school || ! $school->telegram_bot_token) {
            return $this->respondWithError('No Telegram bot token configured.', 'VALIDATION_ERROR', 422, [
                'telegram_bot_token' => ['Please configure and save a Telegram bot token before registering the webhook.']
            ]);
        }

        $result = $this->telegramService->registerWebhook($school);

        if (!($result['ok'] ?? false)) {
            return $this->respondWithError($result['description'] ?? 'Failed to register webhook with Telegram.', 'VALIDATION_ERROR', 422, $result);
        }

        return $this->respondWithSuccess($result, 'Telegram webhook successfully registered.');
    }

    /**
     * Resolve the active school from TenantManager or authenticated user.
     */
    protected function resolveActiveSchool(Request $request): ?School
    {
        $tenant = $this->tenantManager->getTenant();
        if ($tenant) {
            return $tenant;
        }

        $schoolId = $this->tenantManager->getTenantId() ?: $request->user()?->school_id;
        if ($schoolId) {
            return School::find($schoolId);
        }

        return null;
    }
}
