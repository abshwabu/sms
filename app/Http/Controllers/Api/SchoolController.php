<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasApiResponse;
use App\Http\Requests\UpdateSchoolTelegramRequest;
use App\Models\School;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of available schools.
     */
    public function index(): JsonResponse
    {
        $schools = School::select([
            'id',
            'name',
            'subdomain',
            'logo',
            'subscription_status',
            'timezone',
            'telegram_bot_username',
            'created_at',
        ])
            ->withCount('courses')
            ->get();

        return $this->respondWithSuccess($schools, 'Schools retrieved successfully.');
    }

    /**
     * Display the specified school.
     */
    public function show(School $school): JsonResponse
    {
        $school->loadCount('courses');

        return $this->respondWithSuccess($school, 'School details retrieved successfully.');
    }

    /**
     * Update Telegram bot configuration for a specific school.
     * Guarded: Super Admin or School Admin of this school.
     */
    public function updateTelegram(UpdateSchoolTelegramRequest $request, School $school, TelegramService $telegramService): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->school_id !== $school->id) {
            return $this->respondWithError('You are not authorized to configure Telegram settings for another school.', 'FORBIDDEN', 403);
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
            $webhookResult = $telegramService->registerWebhook($school);
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
     * Test connection to Telegram API for a specific school.
     * Guarded: Super Admin or School Admin of this school.
     */
    public function testTelegram(Request $request, School $school, TelegramService $telegramService): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->school_id !== $school->id) {
            return $this->respondWithError('You are not authorized to test Telegram settings for another school.', 'FORBIDDEN', 403);
        }

        $token = $request->input('token');
        if (!$token || str_contains($token, '•')) {
            $token = $school->telegram_bot_token;
        }

        if (empty($token)) {
            return $this->respondWithError('Telegram bot token is required for testing.', 'VALIDATION_ERROR', 422, [
                'token' => ['Please enter a Telegram bot token to test.']
            ]);
        }

        $result = $telegramService->testBotToken($token);

        if (!($result['ok'] ?? false)) {
            return $this->respondWithError($result['description'] ?? 'Telegram API test failed.', 'VALIDATION_ERROR', 422, $result);
        }

        return $this->respondWithSuccess($result, "Telegram bot connection for '{$school->name}' verified successfully.");
    }
}
