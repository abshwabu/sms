<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\BookLoan;
use App\Models\CommunicationThread;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\Invitation;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolCalendar;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentSubjectSelection;
use App\Models\StudentTransport;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected TenantManager $tenantManager,
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Resolve and build the role-specific dashboard payload for an authenticated user.
     */
    public function getDashboardForUser(
        User $user,
        ?string $requestedRole = null,
        ?int $childId = null,
        ?string $requestedDay = null
    ): array {
        // Determine role to display
        $role = $requestedRole ?: $this->determineUserRole($user);

        // Super Admin Platform Dashboard (only allowed for actual super admins)
        if ($role === RoleEnum::SUPER_ADMIN->value && $user->isSuperAdmin()) {
            return $this->getSuperAdminDashboard();
        }

        // For school-specific roles, resolve the school context
        if (! $user->isSuperAdmin()) {
            $school = $user->school;
            if (! $school && $user->school_id) {
                $school = School::find($user->school_id);
            }
            if ($school) {
                $this->tenantManager->setTenant($school);
            }
        } else {
            $school = $this->tenantManager->getTenant() ?? $user->school;
            if (! $school && $user->school_id) {
                $school = School::find($user->school_id);
                if ($school) {
                    $this->tenantManager->setTenant($school);
                }
            }
        }

        if (! $school) {
            // Fallback for platform users with no school selected
            return $this->getSuperAdminDashboard();
        }

        return match ($role) {
            RoleEnum::SCHOOL_ADMIN->value => $this->getSchoolAdminDashboard($school),
            RoleEnum::TEACHER->value => $this->getTeacherDashboard($user, $school, $requestedDay),
            RoleEnum::STUDENT->value => $this->getStudentDashboard($user, $school, $requestedDay),
            RoleEnum::PARENT->value => $this->getParentDashboard($user, $school, $childId, $requestedDay),
            default => $user->isSchoolAdmin()
                ? $this->getSchoolAdminDashboard($school)
                : $this->getStudentDashboard($user, $school, $requestedDay),
        };
    }

    /**
     * Determine the primary role string of the user.
     */
    public function determineUserRole(User $user): string
    {
        if ($user->isSuperAdmin() && ! $this->tenantManager->hasTenant()) {
            return RoleEnum::SUPER_ADMIN->value;
        }

        if ($user->isSchoolAdmin()) {
            return RoleEnum::SCHOOL_ADMIN->value;
        }

        if ($user->isTeacher()) {
            return RoleEnum::TEACHER->value;
        }

        if ($user->isParent()) {
            return RoleEnum::PARENT->value;
        }

        if ($user->isStudent()) {
            return RoleEnum::STUDENT->value;
        }

        return $user->role ?? RoleEnum::STUDENT->value;
    }

    /**
     * 1. SUPER ADMIN: Cross-school overview (schools, subscription status, usage).
     */
    public function getSuperAdminDashboard(): array
    {
        return $this->tenantManager->bypass(function () {
            $schools = School::orderBy('name')->get();

            $totalStudents = Student::withoutGlobalScopes()->where('status', 'active')->count();
            $totalStaff = Staff::withoutGlobalScopes()->where('status', 'active')->count();
            $totalSections = Section::withoutGlobalScopes()->count();
            $totalCourses = Course::withoutGlobalScopes()->count();
            $totalAnnouncements = Announcement::withoutGlobalScopes()->count();

            $schoolsList = $schools->map(function (School $s) {
                $studentsCount = Student::withoutGlobalScopes()->where('school_id', $s->id)->where('status', 'active')->count();
                $staffCount = Staff::withoutGlobalScopes()->where('school_id', $s->id)->where('status', 'active')->count();
                $sectionsCount = Section::withoutGlobalScopes()->where('school_id', $s->id)->count();

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'subdomain' => $s->subdomain,
                    'is_active' => (bool) $s->is_active,
                    'subscription_status' => $s->is_active ? 'active' : 'suspended',
                    'students_count' => $studentsCount,
                    'staff_count' => $staffCount,
                    'sections_count' => $sectionsCount,
                    'telegram_configured' => ! empty($s->telegram_bot_token),
                    'created_at' => $s->created_at?->format('Y-m-d'),
                ];
            });

            return [
                'role' => RoleEnum::SUPER_ADMIN->value,
                'title' => 'Platform Super-Admin Overview',
                'summary_metrics' => [
                    'total_schools' => $schools->count(),
                    'active_schools' => $schools->where('is_active', true)->count(),
                    'total_students' => $totalStudents,
                    'total_staff' => $totalStaff,
                    'total_sections' => $totalSections,
                    'total_courses' => $totalCourses,
                    'total_announcements' => $totalAnnouncements,
                ],
                'schools' => $schoolsList,
                'system_health' => [
                    'db_connected' => true,
                    'cache_driver' => config('cache.default', 'file'),
                    'queue_connection' => config('queue.default', 'sync'),
                    'multi_tenant_isolation' => 'Enforced via TenantScoped global scope & dual resolution',
                    'timestamp' => now()->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * 2. SCHOOL ADMIN: Enrollment stats, attendance trends, pending approvals/actions, announcements composer.
     */
    public function getSchoolAdminDashboard(School $school): array
    {
        $schoolId = $school->id;

        // Enrollment & Capacity
        $totalStudents = Student::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        $totalStaff = Staff::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        $sections = Section::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->with(['gradeLevel', 'academicYear'])
            ->get();

        $totalSections = $sections->count();
        $totalCapacity = (int) $sections->sum('capacity');
        $capacityUtilizedPercent = $totalCapacity > 0
            ? round(($totalStudents / $totalCapacity) * 100, 1)
            : 0.0;

        // Grade Level Breakdown
        $gradeLevels = GradeLevel::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->orderBy('order')
            ->get();

        $gradeBreakdown = $gradeLevels->map(function ($gl) use ($schoolId) {
            $secList = Section::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('grade_level_id', $gl->id)
                ->get();

            $secIds = $secList->pluck('id');
            $studentCount = Student::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->whereIn('current_section_id', $secIds)
                ->count();

            return [
                'id' => $gl->id,
                'name' => $gl->name,
                'order' => $gl->order,
                'sections_count' => $secList->count(),
                'students_count' => $studentCount,
                'capacity' => (int) $secList->sum('capacity'),
            ];
        });

        // Attendance Trends (Today & Recent days)
        $todayDate = now()->format('Y-m-d');
        $todayRecords = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereDate('date', $todayDate)
            ->get();

        $todayPresent = $todayRecords->where('status', AttendanceStatus::PRESENT->value)->count();
        $todayLate = $todayRecords->where('status', AttendanceStatus::LATE->value)->count();
        $todayAbsent = $todayRecords->where('status', AttendanceStatus::ABSENT->value)->count();
        $todayExcused = $todayRecords->where('status', AttendanceStatus::EXCUSED->value)->count();
        $todayTotal = $todayRecords->count();

        $todayRate = $todayTotal > 0
            ? round((($todayPresent + $todayLate) / $todayTotal) * 100, 1)
            : 0.0;

        $sectionsMarkedCount = $todayRecords->pluck('section_id')->unique()->count();

        // 7-day attendance rate trend
        $recentTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $dayRecords = AttendanceRecord::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->whereDate('date', $day)
                ->get();

            $cnt = $dayRecords->count();
            $attCount = $dayRecords->whereIn('status', [AttendanceStatus::PRESENT->value, AttendanceStatus::LATE->value])->count();
            $recentTrends[] = [
                'date' => $day,
                'rate' => $cnt > 0 ? round(($attCount / $cnt) * 100, 1) : null,
                'marked_count' => $cnt,
            ];
        }

        // Pending Actions / Approvals
        $overdueLoansCount = BookLoan::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereNull('returned_at')
            ->where('due_at', '<', now())
            ->count();

        $unassignedStudentsCount = Student::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNull('current_section_id')
            ->count();

        $pendingInvitationsCount = Invitation::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->count();

        $draftAnnouncementsCount = Announcement::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereNull('published_at')
            ->count();

        // Recent Announcements
        $recentAnnouncements = Announcement::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->with('author:id,name')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'audience_type' => $a->audience_type,
                'priority' => $a->priority,
                'is_published' => ! empty($a->published_at),
                'published_at' => $a->published_at?->format('Y-m-d H:i'),
                'author_name' => $a->author?->name ?? 'Admin',
            ]);

        return [
            'role' => RoleEnum::SCHOOL_ADMIN->value,
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'subdomain' => $school->subdomain,
            ],
            'enrollment_stats' => [
                'total_students' => $totalStudents,
                'total_staff' => $totalStaff,
                'total_sections' => $totalSections,
                'total_capacity' => $totalCapacity,
                'capacity_utilized_percent' => $capacityUtilizedPercent,
                'grade_breakdown' => $gradeBreakdown,
            ],
            'attendance_trends' => [
                'today' => $todayDate,
                'today_rate' => $todayRate,
                'sections_marked_count' => $sectionsMarkedCount,
                'sections_total_count' => $totalSections,
                'sections_pending_count' => max(0, $totalSections - $sectionsMarkedCount),
                'breakdown' => [
                    'present' => $todayPresent,
                    'late' => $todayLate,
                    'absent' => $todayAbsent,
                    'excused' => $todayExcused,
                    'total' => $todayTotal,
                ],
                'daily_history' => $recentTrends,
            ],
            'pending_actions' => [
                'overdue_loans_count' => $overdueLoansCount,
                'unassigned_students_count' => $unassignedStudentsCount,
                'pending_invitations_count' => $pendingInvitationsCount,
                'draft_announcements_count' => $draftAnnouncementsCount,
            ],
            'recent_announcements' => $recentAnnouncements,
        ];
    }

    /**
     * 3. TEACHER: Today's sections to mark attendance for, pending grade entry, personal timetable, recent messages.
     */
    public function getTeacherDashboard(User $user, School $school, ?string $requestedDay = null): array
    {
        $schoolId = $school->id;
        $staff = $user->staff ?? Staff::withoutGlobalScopes()->where('user_id', $user->id)->first();
        $todayDate = now()->format('Y-m-d');
        $dayOfWeek = $this->resolveDayOfWeek($requestedDay);

        // Sections to mark attendance for (homeroom sections)
        $homeroomSections = $staff ? $staff->homeroomSections()->with('gradeLevel')->get() : collect();
        if ($homeroomSections->isEmpty()) {
            // Also include any section where this user is assigned as homeroom_teacher_id
            $homeroomSections = Section::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('homeroom_teacher_id', $user->id)
                ->with('gradeLevel')
                ->get();
        }

        $attendanceSections = $homeroomSections->map(function ($sec) use ($todayDate, $schoolId) {
            $records = AttendanceRecord::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('section_id', $sec->id)
                ->whereDate('date', $todayDate)
                ->get();

            $isMarked = $records->isNotEmpty();
            $totalStudents = Student::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('current_section_id', $sec->id)
                ->where('status', 'active')
                ->count();

            return [
                'section_id' => $sec->id,
                'section_name' => $sec->name,
                'grade_level' => $sec->gradeLevel?->name ?? 'Grade',
                'student_count' => $totalStudents,
                'is_marked_today' => $isMarked,
                'marked_count' => $records->count(),
                'present_count' => $records->where('status', AttendanceStatus::PRESENT->value)->count(),
                'absent_count' => $records->where('status', AttendanceStatus::ABSENT->value)->count(),
                'late_count' => $records->where('status', AttendanceStatus::LATE->value)->count(),
                'last_marked_at' => $records->first()?->created_at?->format('H:i'),
            ];
        });

        // Personal Timetable for Today
        $timetableSlots = TimetableSlot::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('teacher_id', $user->id)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('period_number')
            ->with(['subject', 'section.gradeLevel'])
            ->get()
            ->map(fn ($slot) => [
                'id' => $slot->id,
                'period_number' => $slot->period_number,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'subject_name' => $slot->subject?->name ?? 'Class',
                'section_name' => $slot->section?->name ?? 'Section',
                'grade_level' => $slot->section?->gradeLevel?->name ?? '',
                'room' => $slot->room ?? 'Room TBD',
            ]);

        // Pending Grade Entry: open exams where teacher has assigned sections and incomplete marks
        $pendingGrades = [];
        $activeYear = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();

        if ($staff) {
            $assignedSections = $staff->allAssignedSections();
            $activeExams = Exam::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
                ->latest('date')
                ->take(6)
                ->get();

            foreach ($assignedSections as $sec) {
                $secStudentCount = Student::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->where('current_section_id', $sec->id)
                    ->where('status', 'active')
                    ->count();

                if ($secStudentCount === 0) continue;

                // Subjects available for this section's grade level
                $subjects = Subject::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->where('grade_level_id', $sec->grade_level_id)
                    ->get();

                foreach ($activeExams as $exam) {
                    if ($exam->grade_level_id && $exam->grade_level_id !== $sec->grade_level_id) {
                        continue;
                    }

                    foreach ($subjects as $subj) {
                        $enteredCount = Grade::withoutGlobalScopes()
                            ->where('school_id', $schoolId)
                            ->where('exam_id', $exam->id)
                            ->where('section_id', $sec->id)
                            ->where('subject_id', $subj->id)
                            ->count();

                        if ($enteredCount < $secStudentCount) {
                            $pendingGrades[] = [
                                'exam_id' => $exam->id,
                                'exam_name' => $exam->name,
                                'exam_type' => $exam->type,
                                'subject_id' => $subj->id,
                                'subject_name' => $subj->name,
                                'section_id' => $sec->id,
                                'section_name' => $sec->name,
                                'entered_count' => $enteredCount,
                                'total_students' => $secStudentCount,
                                'pending_count' => $secStudentCount - $enteredCount,
                            ];
                        }
                    }
                }
            }
        }

        // Recent Messages / Communication Threads
        $teacherSecIds = $homeroomSections->pluck('id')->all();
        $recentThreads = CommunicationThread::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($user, $teacherSecIds) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('student', function ($sq) use ($teacherSecIds) {
                      $sq->whereIn('current_section_id', $teacherSecIds);
                  });
            })
            ->with(['student.user', 'student.currentSection.gradeLevel', 'latestMessage.sender'])
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'student_name' => $t->student?->user?->name ?? 'Student',
                'section_name' => $t->student?->currentSection?->name,
                'last_message' => $t->latestMessage?->body,
                'last_sender' => $t->latestMessage?->sender?->name,
                'updated_at' => $t->updated_at->diffForHumans(),
            ]);

        return [
            'role' => RoleEnum::TEACHER->value,
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
            ],
            'teacher_profile' => [
                'name' => $user->name,
                'staff_number' => $staff?->staff_number,
                'role_title' => $staff?->role_title ?? 'Teacher',
            ],
            'attendance_sections' => $attendanceSections,
            'today_timetable' => [
                'day_of_week' => $dayOfWeek,
                'slots' => $timetableSlots,
            ],
            'pending_grade_entry' => array_slice($pendingGrades, 0, 5),
            'recent_messages' => $recentThreads,
        ];
    }

    /**
     * 4. STUDENT: Today's timetable, recent grades, attendance %, announcements, library loans.
     */
    public function getStudentDashboard(User $user, School $school, ?string $requestedDay = null): array
    {
        $schoolId = $school->id;
        $student = $user->student ?? Student::withoutGlobalScopes()->where('user_id', $user->id)->first();
        $dayOfWeek = $this->resolveDayOfWeek($requestedDay);

        if (! $student) {
            return [
                'role' => RoleEnum::STUDENT->value,
                'error' => 'No student profile record found for this user account.',
            ];
        }

        $student->load(['currentSection.gradeLevel', 'currentSection.homeroomTeacher']);

        // Today's Timetable
        $timetableSlots = collect();
        if ($student->current_section_id) {
            $enrolledElectiveIds = StudentSubjectSelection::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('student_id', $student->id)
                ->where('status', 'enrolled')
                ->pluck('subject_id')
                ->all();

            $sectionSlots = TimetableSlot::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('section_id', $student->current_section_id)
                ->where('day_of_week', $dayOfWeek)
                ->with(['subject', 'teacher'])
                ->get()
                ->filter(function ($slot) use ($enrolledElectiveIds) {
                    if (! $slot->subject || ! $slot->subject->is_elective) {
                        return true;
                    }
                    return in_array($slot->subject_id, $enrolledElectiveIds);
                });

            $coveredElectives = $sectionSlots->filter(fn($s) => $s->subject?->is_elective)->pluck('subject_id')->unique()->all();
            $uncoveredElectives = array_values(array_diff($enrolledElectiveIds, $coveredElectives));

            $crossSectionSlots = collect();
            if (! empty($uncoveredElectives) && $student->currentSection) {
                $crossSectionSlots = TimetableSlot::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->where('day_of_week', $dayOfWeek)
                    ->whereIn('subject_id', $uncoveredElectives)
                    ->whereHas('section', fn($q) => $q->where('grade_level_id', $student->currentSection->grade_level_id))
                    ->with(['subject', 'teacher'])
                    ->get();
            }

            $timetableSlots = $sectionSlots->concat($crossSectionSlots)
                ->sortBy('period_number')
                ->values()
                ->map(fn ($slot) => [
                    'id' => $slot->id,
                    'period_number' => $slot->period_number,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'subject_name' => $slot->subject?->name ?? 'Class',
                    'teacher_name' => $slot->teacher?->name ?? 'Teacher',
                    'room' => $slot->room ?? 'Room TBD',
                ]);
        }

        // Recent Grades
        $recentGrades = Grade::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->with(['subject', 'exam'])
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject?->name ?? 'Subject',
                'exam_name' => $g->exam?->name ?? 'Exam',
                'exam_type' => $g->exam?->type ?? 'Assessment',
                'marks_obtained' => (float) $g->marks_obtained,
                'max_marks' => (float) $g->max_marks,
                'percentage' => $g->max_marks > 0 ? round(($g->marks_obtained / $g->max_marks) * 100, 1) : 0,
                'remarks' => $g->remarks,
            ]);

        // Attendance Summary
        $attSummary = $this->attendanceService->getStudentAttendanceSummary($student);
        $todayRecord = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->whereDate('date', now()->format('Y-m-d'))
            ->first();

        // Library Loans
        $bookLoans = BookLoan::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($student, $user) {
                $q->where('student_id', $student->id)
                  ->orWhere('user_id', $user->id);
            })
            ->whereNull('returned_at')
            ->with('book')
            ->orderBy('due_at')
            ->take(5)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'title' => $l->book?->title ?? 'Library Book',
                'author' => $l->book?->author,
                'borrowed_at' => $l->borrowed_at?->format('Y-m-d'),
                'due_at' => $l->due_at?->format('Y-m-d'),
                'is_overdue' => $l->due_at && $l->due_at->isPast(),
                'fine_amount' => (float) $l->fine_amount,
            ]);

        // Targeted Announcements
        $announcements = Announcement::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->published()
            ->forUser($user)
            ->with('author:id,name')
            ->latest('published_at')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'priority' => $a->priority,
                'published_at' => $a->published_at?->format('M d, Y'),
                'author_name' => $a->author?->name ?? 'School Administration',
            ]);

        return [
            'role' => RoleEnum::STUDENT->value,
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
            ],
            'student_profile' => [
                'id' => $student->id,
                'name' => $user->name,
                'admission_number' => $student->admission_number,
                'section_name' => $student->currentSection?->name ?? 'Not Enrolled',
                'grade_level' => $student->currentSection?->gradeLevel?->name ?? 'N/A',
                'homeroom_teacher' => $student->currentSection?->homeroomTeacher?->name ?? 'Unassigned',
            ],
            'today_timetable' => [
                'day_of_week' => $dayOfWeek,
                'slots' => $timetableSlots,
            ],
            'recent_grades' => $recentGrades,
            'attendance_summary' => [
                'rate_percent' => $attSummary['summary']['attendance_percentage'] ?? 0,
                'present_days' => $attSummary['summary']['present_days'] ?? 0,
                'late_days' => $attSummary['summary']['late_days'] ?? 0,
                'absent_days' => $attSummary['summary']['absent_days'] ?? 0,
                'total_school_days' => $attSummary['summary']['total_school_days'] ?? 0,
                'today_status' => $todayRecord?->status ?? 'pending',
            ],
            'library_loans' => $bookLoans,
            'announcements' => $announcements,
        ];
    }

    /**
     * 5. PARENT: Per-child switcher showing attendance, latest grades, timetable, transport, messages, announcements.
     */
    public function getParentDashboard(User $user, School $school, ?int $childId = null, ?string $requestedDay = null): array
    {
        $schoolId = $school->id;
        $parent = $user->parentProfile ?? ParentProfile::withoutGlobalScopes()->where('user_id', $user->id)->first();
        $dayOfWeek = $this->resolveDayOfWeek($requestedDay);

        if (! $parent) {
            return [
                'role' => RoleEnum::PARENT->value,
                'error' => 'No parent profile found for this user account.',
            ];
        }

        // All linked children for switcher
        $children = $parent->students()
            ->with(['user:id,name,email,status', 'currentSection.gradeLevel', 'currentSection.homeroomTeacher'])
            ->get();

        if ($children->isEmpty()) {
            return [
                'role' => RoleEnum::PARENT->value,
                'children' => [],
                'selected_child' => null,
                'message' => 'No children are currently linked to this parent account.',
            ];
        }

        // Active child: requested or first child
        $selectedChild = $childId
            ? $children->firstWhere('id', $childId) ?? $children->first()
            : $children->first();

        // 1. Attendance for child
        $attSummary = $this->attendanceService->getStudentAttendanceSummary($selectedChild);
        $todayRecord = AttendanceRecord::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $selectedChild->id)
            ->whereDate('date', now()->format('Y-m-d'))
            ->first();

        // 2. Latest Grades
        $recentGrades = Grade::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $selectedChild->id)
            ->with(['subject', 'exam'])
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject?->name ?? 'Subject',
                'exam_name' => $g->exam?->name ?? 'Exam',
                'marks_obtained' => (float) $g->marks_obtained,
                'max_marks' => (float) $g->max_marks,
                'percentage' => $g->max_marks > 0 ? round(($g->marks_obtained / $g->max_marks) * 100, 1) : 0,
            ]);

        // 3. Timetable for child (core + selected electives, merging cross-section elective slots)
        $timetableSlots = collect();
        if ($selectedChild->current_section_id) {
            $enrolledElectiveIds = StudentSubjectSelection::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('student_id', $selectedChild->id)
                ->where('status', 'enrolled')
                ->pluck('subject_id')
                ->all();

            $sectionSlots = TimetableSlot::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('section_id', $selectedChild->current_section_id)
                ->where('day_of_week', $dayOfWeek)
                ->with(['subject', 'teacher'])
                ->get()
                ->filter(function ($slot) use ($enrolledElectiveIds) {
                    if (! $slot->subject || ! $slot->subject->is_elective) {
                        return true;
                    }
                    return in_array($slot->subject_id, $enrolledElectiveIds);
                });

            $coveredElectives = $sectionSlots->filter(fn($s) => $s->subject?->is_elective)->pluck('subject_id')->unique()->all();
            $uncoveredElectives = array_values(array_diff($enrolledElectiveIds, $coveredElectives));

            $crossSectionSlots = collect();
            if (! empty($uncoveredElectives) && $selectedChild->currentSection) {
                $crossSectionSlots = TimetableSlot::withoutGlobalScopes()
                    ->where('school_id', $schoolId)
                    ->where('day_of_week', $dayOfWeek)
                    ->whereIn('subject_id', $uncoveredElectives)
                    ->whereHas('section', fn($q) => $q->where('grade_level_id', $selectedChild->currentSection->grade_level_id))
                    ->with(['subject', 'teacher'])
                    ->get();
            }

            $timetableSlots = $sectionSlots->concat($crossSectionSlots)
                ->sortBy('period_number')
                ->values()
                ->map(fn ($slot) => [
                    'id' => $slot->id,
                    'period_number' => $slot->period_number,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'subject_name' => $slot->subject?->name ?? 'Class',
                    'teacher_name' => $slot->teacher?->name ?? 'Teacher',
                    'room' => $slot->room ?? 'Room TBD',
                ]);
        }

        // 4. Transport route & stop
        $transport = StudentTransport::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $selectedChild->id)
            ->with(['route', 'stop'])
            ->first();

        // 5. Direct message threads for this child
        $threads = CommunicationThread::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $selectedChild->id)
            ->with(['latestMessage.sender'])
            ->latest('updated_at')
            ->take(4)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'last_message' => $t->latestMessage?->body,
                'last_sender' => $t->latestMessage?->sender?->name,
                'updated_at' => $t->updated_at->diffForHumans(),
            ]);

        // 6. Announcements for child
        $announcements = Announcement::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->published()
            ->forUser($user)
            ->with('author:id,name')
            ->latest('published_at')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'priority' => $a->priority,
                'published_at' => $a->published_at?->format('M d, Y'),
            ]);

        return [
            'role' => RoleEnum::PARENT->value,
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
            ],
            'children' => $children->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->user?->name ?? $c->admission_number,
                'admission_number' => $c->admission_number,
                'section_name' => $c->currentSection?->name ?? 'No Section',
                'grade_level' => $c->currentSection?->gradeLevel?->name ?? 'Grade',
                'relationship' => $c->pivot?->relationship ?? 'Guardian',
                'is_primary' => (bool) ($c->pivot?->is_primary_contact ?? false),
            ]),
            'active_child_id' => $selectedChild->id,
            'child_dashboard' => [
                'student' => [
                    'id' => $selectedChild->id,
                    'name' => $selectedChild->user?->name,
                    'admission_number' => $selectedChild->admission_number,
                    'section_name' => $selectedChild->currentSection?->name ?? 'Unassigned',
                    'grade_level' => $selectedChild->currentSection?->gradeLevel?->name ?? 'N/A',
                    'homeroom_teacher' => $selectedChild->currentSection?->homeroomTeacher?->name ?? 'Unassigned',
                ],
                'attendance' => [
                    'rate_percent' => $attSummary['summary']['attendance_percentage'] ?? 0,
                    'present_days' => $attSummary['summary']['present_days'] ?? 0,
                    'late_days' => $attSummary['summary']['late_days'] ?? 0,
                    'absent_days' => $attSummary['summary']['absent_days'] ?? 0,
                    'today_status' => $todayRecord?->status ?? 'pending',
                ],
                'recent_grades' => $recentGrades,
                'today_timetable' => [
                    'day_of_week' => $dayOfWeek,
                    'slots' => $timetableSlots,
                ],
                'transport' => $transport ? [
                    'has_transport' => true,
                    'route_name' => $transport->route?->name,
                    'vehicle_info' => $transport->route?->vehicle_info,
                    'driver_name' => $transport->route?->driver_name,
                    'driver_phone' => $transport->route?->driver_phone,
                    'stop_name' => $transport->stop?->name,
                    'pickup_time' => $transport->stop?->pickup_time,
                    'dropoff_time' => $transport->stop?->dropoff_time,
                ] : [
                    'has_transport' => false,
                ],
                'direct_messages' => $threads,
                'announcements' => $announcements,
            ],
        ];
    }

    /**
     * Resolve a valid DayOfWeek enum string.
     */
    protected function resolveDayOfWeek(?string $day): string
    {
        if ($day && in_array(strtolower($day), DayOfWeek::values(), true)) {
            return strtolower($day);
        }

        $nowDay = strtolower(now()->format('l'));
        if (in_array($nowDay, DayOfWeek::values(), true)) {
            return $nowDay;
        }

        return DayOfWeek::MONDAY->value;
    }
}
