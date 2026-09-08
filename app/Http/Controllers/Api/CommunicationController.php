<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunicationMessageRequest;
use App\Http\Requests\StoreCommunicationThreadRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\CommunicationThread;
use App\Models\Student;
use App\Services\CommunicationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CommunicationController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected CommunicationService $communicationService
    ) {}

    /**
     * List communication threads accessible to the current user.
     * Acceptance criterion 2: Teacher-parent message thread is scoped to the specific student
     * and visible to both linked parents (if two) plus the relevant teacher(s).
     */
    public function threads(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CommunicationThread::class);

        $threads = CommunicationThread::forUser($request->user())
            ->with([
                'student.user:id,name,email',
                'student.currentSection:id,name',
                'creator:id,name,email,role',
                'latestMessage.sender:id,name,email,role',
            ])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return $this->respondWithSuccess($threads, 'Communication threads retrieved successfully.');
    }

    /**
     * List threads specifically for a student (e.g. from student profile or parent portal).
     */
    public function studentThreads(Student $student, Request $request): JsonResponse
    {
        $user = $request->user();

        $dummyThread = new CommunicationThread([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'created_by' => $user->id,
        ]);
        $dummyThread->setRelation('student', $student);

        if (! $dummyThread->isParticipant($user)) {
            throw new AuthorizationException('You are not authorized to view communication threads for this student.');
        }

        $threads = CommunicationThread::where('student_id', $student->id)
            ->with([
                'student.user:id,name,email',
                'creator:id,name,email,role',
                'latestMessage.sender:id,name,email,role',
            ])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return $this->respondWithSuccess($threads, 'Student communication threads retrieved.');
    }

    /**
     * View thread and full message history.
     */
    public function showThread(CommunicationThread $thread): JsonResponse
    {
        Gate::authorize('view', $thread);

        $thread->load([
            'student.user:id,name,email',
            'student.parents.user:id,name,email',
            'student.currentSection.homeroomTeacher:id,name,email',
            'creator:id,name,email,role',
            'messages.sender:id,name,email,role',
        ]);

        return $this->respondWithSuccess($thread, 'Thread details and messages retrieved.');
    }

    /**
     * Start a new direct messaging thread regarding a student.
     */
    public function storeThread(StoreCommunicationThreadRequest $request): JsonResponse
    {
        Gate::authorize('create', CommunicationThread::class);

        $student = Student::findOrFail($request->input('student_id'));

        $thread = $this->communicationService->createThread(
            $student,
            $request->user(),
            $request->input('subject'),
            $request->input('message')
        );

        return $this->respondWithSuccess(
            $thread,
            'Communication thread started successfully.',
            Response::HTTP_CREATED
        );
    }

    /**
     * Reply with a message in an existing thread.
     */
    public function reply(StoreCommunicationMessageRequest $request, CommunicationThread $thread): JsonResponse
    {
        Gate::authorize('reply', $thread);

        $message = $this->communicationService->sendMessage(
            $thread,
            $request->user(),
            $request->input('body')
        );

        return $this->respondWithSuccess(
            $message,
            'Message sent successfully.',
            Response::HTTP_CREATED
        );
    }
}
