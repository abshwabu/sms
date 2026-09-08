<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AnnouncementController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    /**
     * List announcements targeted to the current user.
     * Acceptance criterion 1: An announcement targeted at "Grade 3" only appears for
     * Grade 3 parents/students/teachers, not the whole school.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();

        $query = Announcement::with(['author:id,name,email,role', 'gradeLevel:id,name,code', 'section:id,name'])
            ->withCount('dispatches');

        // Non-admins only see published announcements matching their audience targeting
        if (! $user->isSuperAdmin() && ! $user->isSchoolAdmin()) {
            $query->published()->forUser($user);
        } else {
            // Admins can filter by published status or view all
            if ($request->boolean('published_only')) {
                $query->published();
            }
        }

        if ($request->filled('audience_type')) {
            $query->where('audience_type', $request->input('audience_type'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $announcements = $query->latest('published_at')->latest('id')->get();

        return $this->respondWithSuccess($announcements, 'Announcements retrieved successfully.');
    }

    /**
     * Show a single announcement.
     */
    public function show(Announcement $announcement): JsonResponse
    {
        Gate::authorize('view', $announcement);

        $announcement->load(['author:id,name,email,role', 'gradeLevel:id,name,code', 'section:id,name'])
            ->loadCount('dispatches');

        return $this->respondWithSuccess($announcement, 'Announcement details retrieved.');
    }

    /**
     * Create a new announcement.
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        Gate::authorize('create', Announcement::class);

        $announcement = $this->announcementService->createAnnouncement(
            $request->validated(),
            $request->user()
        );

        return $this->respondWithSuccess(
            $announcement->load(['author:id,name,email,role', 'gradeLevel', 'section']),
            'Announcement created successfully.',
            Response::HTTP_CREATED
        );
    }

    /**
     * Update an announcement.
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('update', $announcement);

        $updated = $this->announcementService->updateAnnouncement($announcement, $request->validated());

        return $this->respondWithSuccess(
            $updated->load(['author:id,name,email,role', 'gradeLevel', 'section']),
            'Announcement updated successfully.'
        );
    }

    /**
     * Delete an announcement.
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return $this->respondWithSuccess(null, 'Announcement deleted successfully.');
    }

    /**
     * Publish an existing draft announcement immediately and dispatch notifications.
     */
    public function publish(Announcement $announcement): JsonResponse
    {
        Gate::authorize('update', $announcement);

        $published = $this->announcementService->publishAnnouncement($announcement);

        return $this->respondWithSuccess($published, 'Announcement published and notifications dispatched.');
    }

    /**
     * Get delivery and dispatch statistics for an announcement.
     */
    public function stats(Announcement $announcement): JsonResponse
    {
        Gate::authorize('view', $announcement);

        $dispatches = $announcement->dispatches()
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel');

        return $this->respondWithSuccess([
            'announcement_id' => $announcement->id,
            'is_published' => $announcement->isPublished(),
            'published_at' => $announcement->published_at?->toIso8601String(),
            'channels' => $announcement->channels,
            'dispatches' => $dispatches,
            'total_dispatches' => $dispatches->sum(),
        ], 'Announcement dispatch metrics retrieved.');
    }
}
