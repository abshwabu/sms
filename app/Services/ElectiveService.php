<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectiveService
{
    /**
     * Configure (create or update) a subject offering for a grade level and academic year.
     */
    public function configureOffering(array $data, User $actor): SubjectOffering
    {
        $schoolId = $actor->school_id;

        $academicYear = $actor->isSuperAdmin()
            ? AcademicYear::withoutGlobalScopes()->findOrFail($data['academic_year_id'])
            : AcademicYear::where('school_id', $schoolId)->findOrFail($data['academic_year_id']);

        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot configure subject offerings in a closed academic year.');
        }

        $subject = $actor->isSuperAdmin()
            ? Subject::withoutGlobalScopes()->findOrFail($data['subject_id'])
            : Subject::where('school_id', $schoolId)->findOrFail($data['subject_id']);

        $gradeLevel = $actor->isSuperAdmin()
            ? GradeLevel::withoutGlobalScopes()->findOrFail($data['grade_level_id'])
            : GradeLevel::where('school_id', $schoolId)->findOrFail($data['grade_level_id']);

        $type = $data['type'] ?? ($subject->is_elective ? 'elective' : 'core');

        // Sync subject's is_elective flag if elective offering
        if ($type === 'elective' && ! $subject->is_elective) {
            $subject->update(['is_elective' => true]);
        }

        $offering = SubjectOffering::updateOrCreate(
            [
                'school_id' => $subject->school_id,
                'academic_year_id' => $academicYear->id,
                'grade_level_id' => $gradeLevel->id,
                'subject_id' => $subject->id,
            ],
            [
                'type' => $type,
                'max_students' => isset($data['max_students']) ? (int) $data['max_students'] : null,
                'enrollment_start' => $data['enrollment_start'] ?? null,
                'enrollment_end' => $data['enrollment_end'] ?? null,
                'is_open' => array_key_exists('is_open', $data) ? (bool) $data['is_open'] : true,
            ]
        );

        return $offering->load(['subject', 'gradeLevel', 'academicYear']);
    }

    /**
     * Update an existing subject offering (e.g. adjust capacity, open/close window).
     */
    public function updateOffering(SubjectOffering $offering, array $data, User $actor): SubjectOffering
    {
        if (! $actor->isSuperAdmin() && (int) $offering->school_id !== (int) $actor->school_id) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Cannot update offering from another school.');
        }
        if ($offering->academicYear && $offering->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot update subject offerings in a closed academic year.');
        }

        $updateData = [];

        if (array_key_exists('max_students', $data)) {
            $updateData['max_students'] = $data['max_students'] !== null ? (int) $data['max_students'] : null;
        }

        if (array_key_exists('enrollment_start', $data)) {
            $updateData['enrollment_start'] = $data['enrollment_start'];
        }

        if (array_key_exists('enrollment_end', $data)) {
            $updateData['enrollment_end'] = $data['enrollment_end'];
        }

        if (array_key_exists('is_open', $data)) {
            $updateData['is_open'] = (bool) $data['is_open'];
        }

        if (array_key_exists('type', $data)) {
            $updateData['type'] = $data['type'];
            if ($data['type'] === 'elective' && ! $offering->subject->is_elective) {
                $offering->subject->update(['is_elective' => true]);
            }
        }

        $offering->update($updateData);

        return $offering->fresh(['subject', 'gradeLevel', 'academicYear']);
    }

    /**
     * List all offerings for a grade level and academic year.
     */
    public function getGradeOfferings(GradeLevel $gradeLevel, AcademicYear $academicYear): \Illuminate\Support\Collection
    {
        $offerings = SubjectOffering::withoutGlobalScopes()
            ->where('school_id', $gradeLevel->school_id)
            ->where('grade_level_id', $gradeLevel->id)
            ->where('academic_year_id', $academicYear->id)
            ->with('subject')
            ->get();

        return $offerings->map(function (SubjectOffering $offering) {
            $enrolledCount = $offering->enrolledCount();
            return [
                'id' => $offering->id,
                'subject_id' => $offering->subject_id,
                'subject_name' => $offering->subject?->name,
                'subject_code' => $offering->subject?->code,
                'type' => $offering->type,
                'max_students' => $offering->max_students,
                'enrolled_count' => $enrolledCount,
                'remaining_capacity' => $offering->max_students !== null ? max(0, $offering->max_students - $enrolledCount) : null,
                'is_full' => $offering->isFull(),
                'is_open' => $offering->is_open,
                'enrollment_start' => $offering->enrollment_start?->toIso8601String(),
                'enrollment_end' => $offering->enrollment_end?->toIso8601String(),
                'is_window_open' => $offering->isWindowOpen(),
                'can_enroll' => $offering->canEnroll(),
            ];
        });
    }

    /**
     * Get available elective offerings for a student, showing status and availability.
     */
    public function getAvailableElectivesForStudent(Student $student, ?AcademicYear $academicYear = null): array
    {
        $schoolId = $student->school_id;
        $academicYear = $academicYear ?? AcademicYear::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();

        if (! $academicYear) {
            return [];
        }

        $gradeLevelId = $student->currentSection?->grade_level_id;
        if (! $gradeLevelId) {
            $enrollment = $student->enrollments()->where('academic_year_id', $academicYear->id)->first();
            $gradeLevelId = $enrollment?->section?->grade_level_id;
        }

        if (! $gradeLevelId) {
            return [];
        }

        $offerings = SubjectOffering::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('grade_level_id', $gradeLevelId)
            ->where('academic_year_id', $academicYear->id)
            ->where('type', 'elective')
            ->with('subject')
            ->get();

        $enrolledSubjectIds = StudentSubjectSelection::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'enrolled')
            ->pluck('subject_id')
            ->all();

        return $offerings->map(function (SubjectOffering $offering) use ($enrolledSubjectIds) {
            $isEnrolled = in_array($offering->subject_id, $enrolledSubjectIds);
            $enrolledCount = $offering->enrolledCount();
            $isFull = $offering->isFull();
            $windowOpen = $offering->isWindowOpen();

            return [
                'id' => $offering->id,
                'subject_id' => $offering->subject_id,
                'subject_name' => $offering->subject?->name,
                'subject_code' => $offering->subject?->code,
                'max_students' => $offering->max_students,
                'enrolled_count' => $enrolledCount,
                'remaining_capacity' => $offering->max_students !== null ? max(0, $offering->max_students - $enrolledCount) : null,
                'is_enrolled' => $isEnrolled,
                'is_full' => $isFull,
                'is_window_open' => $windowOpen,
                'can_enroll' => ! $isEnrolled && $windowOpen && ! $isFull,
                'enrollment_start' => $offering->enrollment_start?->toIso8601String(),
                'enrollment_end' => $offering->enrollment_end?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * Get active elective selections for a student.
     */
    public function getStudentSelections(Student $student, ?AcademicYear $academicYear = null): \Illuminate\Support\Collection
    {
        $schoolId = $student->school_id;
        $academicYear = $academicYear ?? AcademicYear::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();

        if (! $academicYear) {
            return collect();
        }

        return StudentSubjectSelection::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'enrolled')
            ->with('subject')
            ->get();
    }

    /**
     * Enroll a student in an elective subject.
     * Enforces:
     * 1. Academic year not closed.
     * 2. Subject is offered as elective to student's grade level.
     * 3. Enrollment window is open.
     * 4. Capacity is strictly enforced (reject when max_students reached).
     */
    public function enrollStudent(
        Student $student,
        Subject $subject,
        AcademicYear $academicYear,
        User $actor,
        bool $ignoreWindow = false
    ): StudentSubjectSelection {
        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot select electives for a closed academic year.');
        }

        if ((int) $student->school_id !== (int) $subject->school_id) {
            throw ValidationException::withMessages([
                'subject_id' => 'Subject and student belong to different schools.',
            ]);
        }

        $schoolId = $student->school_id;
        $gradeLevelId = $student->currentSection?->grade_level_id;

        if (! $gradeLevelId) {
            $enrollment = $student->enrollments()->where('academic_year_id', $academicYear->id)->first();
            $gradeLevelId = $enrollment?->section?->grade_level_id;
        }

        if (! $gradeLevelId) {
            throw ValidationException::withMessages([
                'student_id' => 'Student is not enrolled in a section or grade level for this academic year.',
            ]);
        }

        // Find offering
        $offering = SubjectOffering::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('grade_level_id', $gradeLevelId)
            ->where('academic_year_id', $academicYear->id)
            ->where('subject_id', $subject->id)
            ->first();

        if (! $offering || $offering->type !== 'elective') {
            throw ValidationException::withMessages([
                'subject_id' => "The subject '{$subject->name}' is not offered as an elective for this grade level.",
            ]);
        }

        // Check if already enrolled
        $existing = StudentSubjectSelection::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('subject_id', $subject->id)
            ->where('status', 'enrolled')
            ->first();

        if ($existing) {
            return $existing->load(['subject', 'student', 'academicYear']);
        }

        // Validate window (unless bypassed by admin)
        if (! $ignoreWindow && ! $offering->isWindowOpen()) {
            throw ValidationException::withMessages([
                'subject_id' => 'The elective enrollment window is closed.',
            ]);
        }

        // Validate capacity
        if ($offering->isFull()) {
            throw ValidationException::withMessages([
                'subject_id' => 'The capacity for this elective subject has been reached.',
            ]);
        }

        return DB::transaction(function () use ($schoolId, $student, $academicYear, $subject, $actor) {
            $selection = StudentSubjectSelection::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'status' => 'enrolled',
                    'selected_at' => now(),
                    'selected_by' => $actor->id,
                ]
            );

            return $selection->load(['subject', 'student', 'academicYear']);
        });
    }

    /**
     * Drop a student from an elective subject.
     */
    public function dropStudent(
        Student $student,
        Subject $subject,
        AcademicYear $academicYear,
        User $actor,
        bool $ignoreWindow = false
    ): bool {
        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot drop electives in a closed academic year.');
        }

        $schoolId = $student->school_id;
        $gradeLevelId = $student->currentSection?->grade_level_id;

        $offering = SubjectOffering::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYear->id)
            ->where('subject_id', $subject->id)
            ->first();

        if (! $ignoreWindow && $offering && ! $offering->isWindowOpen()) {
            throw ValidationException::withMessages([
                'subject_id' => 'The elective enrollment window is closed.',
            ]);
        }

        $selection = StudentSubjectSelection::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('subject_id', $subject->id)
            ->where('status', 'enrolled')
            ->first();

        if (! $selection) {
            throw ValidationException::withMessages([
                'subject_id' => 'Student is not currently enrolled in this elective subject.',
            ]);
        }

        $selection->update([
            'status' => 'dropped',
        ]);

        return true;
    }
}
