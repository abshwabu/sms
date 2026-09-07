<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\TenantManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentPromotionService
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Promote, retain, or graduate students from a source section roster.
     *
     * @param int $sourceSectionId
     * @param int $targetAcademicYearId
     * @param int|null $targetSectionId
     * @param string $action 'promote' | 'retain' | 'graduate'
     * @param array<int> $studentIds
     * @return array{processed_count: int, action: string, source_section: string, target_section: ?string, student_ids: array<int>}
     */
    public function promoteRoster(
        int $sourceSectionId,
        int $targetAcademicYearId,
        ?int $targetSectionId,
        string $action,
        array $studentIds = []
    ): array {
        $schoolId = $this->tenantManager->getTenantId();

        $sourceSection = Section::with('academicYear')->findOrFail($sourceSectionId);
        $targetAcademicYear = AcademicYear::findOrFail($targetAcademicYearId);

        if ($targetAcademicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot enroll or promote students into a closed academic year.');
        }

        $targetSection = null;
        if (in_array($action, ['promote', 'retain'], true)) {
            if (! $targetSectionId) {
                throw new InvalidArgumentException("A target section is required for action '{$action}'.");
            }

            $targetSection = Section::findOrFail($targetSectionId);

            if ((int) $targetSection->academic_year_id !== (int) $targetAcademicYear->id) {
                throw new InvalidArgumentException('Target section does not belong to the target academic year.');
            }
        }

        // Fetch students to process
        $query = Student::where('current_section_id', $sourceSection->id);

        if (! empty($studentIds)) {
            $query->whereIn('id', $studentIds);
        } else {
            $query->where('status', 'active');
        }

        /** @var Collection<int, Student> $students */
        $students = $query->get();

        $processedIds = [];

        DB::beginTransaction();
        try {
            $outcomeStatus = match ($action) {
                'graduate' => 'graduated',
                'retain' => 'retained',
                default => 'promoted',
            };

            foreach ($students as $student) {
                // 1. Update prior enrollment status if the source year is not closed
                $sourceEnrollment = Enrollment::where('student_id', $student->id)
                    ->where('academic_year_id', $sourceSection->academic_year_id)
                    ->first();

                if ($sourceEnrollment && ! $sourceSection->academicYear?->isClosed()) {
                    $sourceEnrollment->update([
                        'status' => $outcomeStatus,
                    ]);
                }

                // 2. Handle outcome
                if ($action === 'graduate') {
                    $student->update([
                        'status' => 'graduated',
                        'current_section_id' => null,
                    ]);
                } else {
                    // Create or update target enrollment in target academic year
                    Enrollment::updateOrCreate(
                        [
                            'academic_year_id' => $targetAcademicYear->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'school_id' => $schoolId,
                            'section_id' => $targetSection->id,
                            'enrolled_at' => now()->toDateString(),
                            'status' => 'enrolled',
                        ]
                    );

                    $student->update([
                        'current_section_id' => $targetSection->id,
                        'status' => 'active',
                    ]);
                }

                $processedIds[] = $student->id;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'processed_count' => count($processedIds),
            'action' => $action,
            'source_section' => $sourceSection->name,
            'target_section' => $targetSection?->name,
            'student_ids' => $processedIds,
        ];
    }
}
