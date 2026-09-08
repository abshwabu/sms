<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimCodeController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\GradeLevelController;
use App\Http\Controllers\Api\GradingController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ParentManagementController;
use App\Http\Controllers\Api\ParentPortalController;
use App\Http\Controllers\Api\ReportCardController;
use App\Http\Controllers\Api\SchoolAdminController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TermController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Dual-channel tenant resolution (subdomain or X-School-Id) is prepended
| globally to the api middleware stack.
|
*/

// Platform & Public Endpoints
Route::get('/health', HealthController::class)->name('api.health');
Route::middleware('tenant.require')->get('/tenant/health', HealthController::class)->name('api.tenant.health');
Route::get('/schools', [SchoolController::class, 'index'])->name('api.schools.index');
Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('api.schools.show');

// Public Authentication Endpoints
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');
});

// Public Onboarding Endpoints
Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('api.invitations.accept');
Route::post('/claim-codes/claim', [ClaimCodeController::class, 'claim'])->name('api.claim-codes.claim');

// Authenticated Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Current user profile & session
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // Tenant-Scoped Routes: requires active tenant AND verifies user belongs to this tenant
    Route::middleware(['tenant.require', 'tenant.user'])->group(function () {
        Route::apiResource('courses', CourseController::class);

        // Academic Structure Queries (Read)
        Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('api.academic-years.index');
        Route::get('/academic-years/{academicYear}', [AcademicYearController::class, 'show'])->name('api.academic-years.show');
        Route::get('/academic-years/{academicYear}/terms', [TermController::class, 'index'])->name('api.academic-years.terms.index');
        Route::get('/grade-levels', [GradeLevelController::class, 'index'])->name('api.grade-levels.index');
        Route::get('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'show'])->name('api.grade-levels.show');
        Route::get('/sections', [SectionController::class, 'index'])->name('api.sections.index');
        Route::get('/sections/{section}', [SectionController::class, 'show'])->name('api.sections.show');
        Route::get('/sections/{section}/roster', [SectionController::class, 'roster'])->name('api.sections.roster');
        Route::get('/sections/{section}/permissions', [SectionController::class, 'permissions'])->name('api.sections.permissions');
        
        // Grade/Section-Based Daily Attendance
        Route::get('/sections/{section}/attendance', [AttendanceController::class, 'getSectionDailyAttendance'])->name('api.sections.attendance.get');
        Route::post('/sections/{section}/attendance', [AttendanceController::class, 'recordSectionAttendance'])->name('api.sections.attendance.post');
        Route::get('/sections/{section}/attendance-summary', [AttendanceController::class, 'getSectionAttendanceSummary'])->name('api.sections.attendance.summary');
        Route::post('/sections/{section}/grades', [SectionController::class, 'recordGrades'])->name('api.sections.grades');

        // Grading, Exams, Grading Scales & Report Cards
        Route::get('/subjects', [GradingController::class, 'indexSubjects'])->name('api.subjects.index');
        Route::post('/subjects', [GradingController::class, 'storeSubject'])->name('api.subjects.store');
        Route::get('/exams', [GradingController::class, 'indexExams'])->name('api.exams.index');
        Route::post('/exams', [GradingController::class, 'storeExam'])->name('api.exams.store');
        Route::get('/grading-scales', [GradingController::class, 'indexGradingScales'])->name('api.grading-scales.index');
        Route::post('/grading-scales', [GradingController::class, 'storeGradingScale'])->name('api.grading-scales.store');

        Route::get('/sections/{section}/subjects/{subject}/grades', [GradingController::class, 'getSectionSubjectGrades'])->name('api.sections.subjects.grades.get');
        Route::post('/grades', [GradingController::class, 'recordGrades'])->name('api.grades.store');

        Route::get('/sections/{section}/report-cards', [ReportCardController::class, 'getSectionReportCards'])->name('api.sections.report-cards.index');
        Route::post('/sections/{section}/report-cards/publish', [ReportCardController::class, 'bulkPublishSection'])->name('api.sections.report-cards.bulk-publish');
        Route::get('/report-cards/{reportCard}', [ReportCardController::class, 'show'])->name('api.report-cards.show');
        Route::post('/report-cards/{reportCard}/publish', [ReportCardController::class, 'publish'])->name('api.report-cards.publish');
        Route::get('/report-cards/{reportCard}/pdf', [ReportCardController::class, 'downloadPdf'])->name('api.report-cards.pdf');

        // Staff Directory (Read)
        Route::get('/staff', [StaffController::class, 'index'])->name('api.staff.index');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('api.staff.show');

        // Students (Read) & Attendance Summaries
        Route::get('/students', [StudentController::class, 'index'])->name('api.students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('api.students.show');
        Route::get('/students/{student}/attendance', [AttendanceController::class, 'getStudentAttendanceSummary'])->name('api.students.attendance');
        Route::get('/students/{student}/attendance-summary', [AttendanceController::class, 'getStudentAttendanceSummary'])->name('api.students.attendance-summary');

        // Student Self View
        Route::get('/student/attendance', [AttendanceController::class, 'getMyStudentAttendance'])->name('api.student.my-attendance');
        Route::get('/student/report-cards', [ReportCardController::class, 'studentReportCards'])->name('api.student.report-cards');
        Route::get('/student/report-cards/{reportCard}/pdf', [ReportCardController::class, 'studentDownloadPdf'])->name('api.student.report-cards.pdf');

        // Parent Portal (Child switching & dashboard access)
        Route::prefix('parent')->group(function () {
            Route::get('/children', [ParentPortalController::class, 'children'])->name('api.parent.children');
            Route::get('/children/{student}/dashboard', [ParentPortalController::class, 'childDashboard'])->name('api.parent.child-dashboard');
            Route::get('/children/{student}/attendance', [AttendanceController::class, 'getParentChildAttendance'])->name('api.parent.child-attendance');
            Route::get('/children/{student}/report-cards', [ReportCardController::class, 'parentChildReportCards'])->name('api.parent.child-report-cards');
            Route::get('/children/{student}/report-cards/{reportCard}/pdf', [ReportCardController::class, 'parentDownloadChildPdf'])->name('api.parent.child-report-cards.pdf');
        });

        // School Calendar (Read)
        Route::get('/calendar', [AttendanceController::class, 'getCalendar'])->name('api.calendar.index');

        // School Admin Only Endpoints (403 for teacher/student/parent)
        Route::middleware('role:school_admin,super_admin')->group(function () {
            // School admin management
            Route::prefix('admin')->group(function () {
                Route::get('/users', [SchoolAdminController::class, 'users'])->name('api.admin.users');
                Route::get('/stats', [SchoolAdminController::class, 'stats'])->name('api.admin.stats');
                Route::post('/invitations', [InvitationController::class, 'invite'])->name('api.admin.invitations');
                Route::post('/claim-codes', [ClaimCodeController::class, 'generate'])->name('api.admin.claim-codes');
            });

            // Academic Structure Management (Write)
            Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('api.academic-years.store');
            Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('api.academic-years.update');
            Route::post('/academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('api.academic-years.close');
            Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('api.academic-years.activate');

            Route::post('/academic-years/{academicYear}/terms', [TermController::class, 'store'])->name('api.academic-years.terms.store');
            Route::put('/terms/{term}', [TermController::class, 'update'])->name('api.terms.update');
            Route::delete('/terms/{term}', [TermController::class, 'destroy'])->name('api.terms.destroy');

            Route::post('/grade-levels', [GradeLevelController::class, 'store'])->name('api.grade-levels.store');
            Route::put('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'update'])->name('api.grade-levels.update');
            Route::delete('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'destroy'])->name('api.grade-levels.destroy');

            Route::post('/sections', [SectionController::class, 'store'])->name('api.sections.store');
            Route::put('/sections/{section}', [SectionController::class, 'update'])->name('api.sections.update');
            Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('api.sections.destroy');
            Route::post('/sections/{section}/assign-student', [SectionController::class, 'assignStudent'])->name('api.sections.assign-student');
            Route::post('/sections/promote', [SectionController::class, 'promote'])->name('api.sections.promote');
            Route::post('/sections/{section}/assign-homeroom', [SectionController::class, 'assignHomeroom'])->name('api.sections.assign-homeroom');
            Route::post('/sections/{section}/assign-subject-teacher', [SectionController::class, 'assignSubjectTeacher'])->name('api.sections.assign-subject-teacher');
            Route::delete('/sections/{section}/subject-teachers/{assignment}', [SectionController::class, 'removeSubjectTeacher'])->name('api.sections.remove-subject-teacher');

            // Staff Management (Write)
            Route::post('/staff', [StaffController::class, 'store'])->name('api.staff.store');
            Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('api.staff.update');
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('api.staff.destroy');

            // Student Management & Enrollment (Write)
            Route::post('/students', [StudentController::class, 'store'])->name('api.students.store');
            Route::put('/students/{student}', [StudentController::class, 'update'])->name('api.students.update');
            Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('api.students.destroy');
            Route::post('/students/import', [StudentController::class, 'import'])->name('api.students.import');
            Route::post('/students/promote-roster', [StudentController::class, 'promoteRoster'])->name('api.students.promote-roster');

            // Parent Accounts & Student Linking (Admin)
            Route::get('/parents', [ParentManagementController::class, 'index'])->name('api.parents.index');
            Route::post('/parents', [ParentManagementController::class, 'store'])->name('api.parents.store');
            Route::get('/parents/{parent}', [ParentManagementController::class, 'show'])->name('api.parents.show');
            Route::post('/parents/{parent}/link-student', [ParentManagementController::class, 'linkStudent'])->name('api.parents.link-student');
            Route::delete('/parents/{parent}/students/{student}', [ParentManagementController::class, 'unlinkStudent'])->name('api.parents.unlink-student');
            Route::post('/parents/invite', [ParentManagementController::class, 'invite'])->name('api.parents.invite');

            // Calendar & School Days Management (Admin)
            Route::post('/calendar', [AttendanceController::class, 'storeCalendarDay'])->name('api.calendar.store');
        });
    });
});
