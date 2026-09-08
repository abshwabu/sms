<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use HasApiResponse;

    /**
     * Get the authenticated user's notification preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $pref = NotificationPreference::forUser($user);

        return $this->respondWithSuccess($pref, 'Notification preferences retrieved.');
    }

    /**
     * Update the authenticated user's notification preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_enabled' => ['sometimes', 'boolean'],
            'telegram_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'attendance_alerts' => ['sometimes', 'boolean'],
            'grade_alerts' => ['sometimes', 'boolean'],
            'announcement_alerts' => ['sometimes', 'boolean'],
            'library_alerts' => ['sometimes', 'boolean'],
            'message_alerts' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $pref = NotificationPreference::forUser($user);
        $pref->update($validated);

        return $this->respondWithSuccess($pref->fresh(), 'Notification preferences updated successfully.');
    }
}
