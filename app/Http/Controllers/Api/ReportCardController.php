<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublishReportCardRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\ReportCard;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Services\ReportCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportCardController extends Controller
{
    use HasApiResponse;

    /**
     * Get or aggregate all report cards for a section and term.
     */
    public function getSectionReportCards(
        Request $request,
        Section $section,
        ReportCardService $reportCardService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin() && ! $section->hasTeacher($user)) {
            return ApiResponse::error(
                'You do not have permission to view report cards for this section.',
                'FORBIDDEN_SECTION_REPORTS',
                Response::HTTP_FORBIDDEN
            );
        }

        $termId = $request->query('term_id');
        $term = null;

        if ($termId) {
            $term = Term::find($termId);
        }

        if (! $term) {
            $term = Term::where('academic_year_id', $section->academic_year_id)
                ->where('is_active', true)
                ->first()
                ?: Term::where('academic_year_id', $section->academic_year_id)
                    ->latest('id')
                    ->first()
                ?: Term::latest('id')->first();
        }

        if (! $term) {
            return ApiResponse::error('No academic terms found for this school. Please configure terms first.', 'TERM_NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        // Auto-aggregate to ensure up-to-date grades and rankings
        $reportCards = $reportCardService->aggregateSectionReportCards($section, $term, generatedBy: $user);

        return $this->respondWithSuccess([
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->gradeLevel?->name,
            ],
            'term' => [
                'id' => $term->id,
                'name' => $term->name,
            ],
            'report_cards' => $reportCards,
        ], 'Section report cards retrieved successfully.');
    }

    /**
     * View an individual report card.
     */
    public function show(Request $request, ReportCard $reportCard): JsonResponse
    {
        Gate::authorize('view', $reportCard);

        $reportCard->load([
            'items.subject',
            'items.teacher',
            'student.user',
            'section.gradeLevel',
            'section.homeroomTeacher',
            'term',
            'academicYear',
            'gradingScale',
        ]);

        return $this->respondWithSuccess($reportCard, 'Report card retrieved successfully.');
    }

    /**
     * Publish a report card (enables parent & student visibility).
     */
    public function publish(
        PublishReportCardRequest $request,
        ReportCard $reportCard,
        ReportCardService $reportCardService
    ): JsonResponse {
        Gate::authorize('publish', $reportCard);

        $remarks = $request->input('principal_remarks') ?? $request->input('homeroom_remarks');
        $updated = $reportCardService->publishReportCard($reportCard, $remarks);

        return $this->respondWithSuccess($updated, 'Report card published successfully.');
    }

    /**
     * Bulk publish all report cards in a section for a term.
     */
    public function bulkPublishSection(
        Request $request,
        Section $section,
        ReportCardService $reportCardService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isSchoolAdmin() && ! $user->isSuperAdmin() && ! $section->isHomeroomTeacher($user)) {
            return ApiResponse::error(
                'Only school administrators or homeroom teachers can publish report cards for this section.',
                'FORBIDDEN_BULK_PUBLISH',
                Response::HTTP_FORBIDDEN
            );
        }

        $termId = $request->input('term_id');
        $term = $termId
            ? Term::find($termId)
            : (Term::where('academic_year_id', $section->academic_year_id)->where('is_active', true)->first()
                ?: Term::where('academic_year_id', $section->academic_year_id)->latest('id')->first()
                ?: Term::latest('id')->first());

        if (! $term) {
            return ApiResponse::error('Term not found.', 'TERM_NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $count = $reportCardService->publishSectionReportCards($section, $term);

        return $this->respondWithSuccess([
            'published_count' => $count,
            'section_id' => $section->id,
            'term_id' => $term->id,
        ], "Published {$count} report cards successfully.");
    }

    /**
     * Download or stream report card PDF.
     * Enforces policy: Parents and students can ONLY download if published.
     */
    public function downloadPdf(
        Request $request,
        ReportCard $reportCard,
        ReportCardService $reportCardService
    ): HttpResponse {
        Gate::authorize('downloadPdf', $reportCard);

        $pdf = $reportCardService->generatePdf($reportCard);
        $filename = "ReportCard_{$reportCard->student->admission_number}_{$reportCard->term->name}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Parent endpoint: List report cards for a linked child.
     * Only returns published report cards.
     */
    public function parentChildReportCards(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if (! $user->isParent() || ! $user->parentProfile?->isLinkedTo($student)) {
            return ApiResponse::error(
                'You are not authorized to view this student\'s academic records.',
                'FORBIDDEN_PARENT_ACCESS',
                Response::HTTP_FORBIDDEN
            );
        }

        $reportCards = ReportCard::withoutGlobalScopes()
            ->where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('status', 'published')
            ->with(['items.subject', 'term', 'academicYear', 'gradingScale', 'section'])
            ->orderByDesc('created_at')
            ->get();

        return $this->respondWithSuccess($reportCards, 'Child report cards retrieved successfully.');
    }

    /**
     * Parent endpoint: Download report card PDF for a linked child.
     * Enforces acceptance criterion: Draft returns 403 Forbidden.
     */
    public function parentDownloadChildPdf(
        Request $request,
        Student $student,
        ReportCard $reportCard,
        ReportCardService $reportCardService
    ): HttpResponse|JsonResponse {
        $user = $request->user();

        if (! $user->isParent() || ! $user->parentProfile?->isLinkedTo($student)) {
            return ApiResponse::error(
                'You are not authorized to access this student\'s report card.',
                'FORBIDDEN_PARENT_ACCESS',
                Response::HTTP_FORBIDDEN
            );
        }

        if ((int) $reportCard->student_id !== (int) $student->id) {
            return ApiResponse::error(
                'Report card does not match the requested student.',
                'INVALID_STUDENT_REPORT_CARD',
                Response::HTTP_FORBIDDEN
            );
        }

        // Acceptance criterion: Parent can view/download their child's report card once published (not before)
        if (! $reportCard->isPublished()) {
            return ApiResponse::error(
                'Report card has not been published yet.',
                'REPORT_CARD_NOT_PUBLISHED',
                Response::HTTP_FORBIDDEN
            );
        }

        $pdf = $reportCardService->generatePdf($reportCard);
        $filename = "ReportCard_{$student->admission_number}_{$reportCard->term->name}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Student endpoint: List own published report cards.
     */
    public function studentReportCards(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isStudent() || ! $user->student) {
            return ApiResponse::error('Only students can access this endpoint.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $student = $user->student;

        $reportCards = ReportCard::withoutGlobalScopes()
            ->where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('status', 'published')
            ->with(['items.subject', 'term', 'academicYear', 'gradingScale', 'section'])
            ->orderByDesc('created_at')
            ->get();

        return $this->respondWithSuccess($reportCards, 'Report cards retrieved successfully.');
    }

    /**
     * Student endpoint: Download own published report card PDF.
     */
    public function studentDownloadPdf(
        Request $request,
        ReportCard $reportCard,
        ReportCardService $reportCardService
    ): HttpResponse|JsonResponse {
        $user = $request->user();

        if (! $user->isStudent() || ! $user->student || (int) $user->student->id !== (int) $reportCard->student_id) {
            return ApiResponse::error('Unauthorized access.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        if (! $reportCard->isPublished()) {
            return ApiResponse::error(
                'Report card has not been published yet.',
                'REPORT_CARD_NOT_PUBLISHED',
                Response::HTTP_FORBIDDEN
            );
        }

        $pdf = $reportCardService->generatePdf($reportCard);
        $filename = "ReportCard_{$user->student->admission_number}_{$reportCard->term->name}.pdf";

        return $pdf->download($filename);
    }
}
