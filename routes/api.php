<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimCodeController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GradeLevelController;
use App\Http\Controllers\Api\GradingController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\ParentManagementController;
use App\Http\Controllers\Api\ParentPortalController;
use App\Http\Controllers\Api\ReportCardController;
use App\Http\Controllers\Api\SchoolAdminController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\NotificationCenterController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\TermController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\TransportController;
use App\Http\Controllers\Api\FeeStructureController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ParentBillingController;
use App\Http\Controllers\Api\PaymentReceiptController;
use App\Http\Controllers\Api\ChapaWebhookController;
use App\Http\Controllers\Api\ElectiveController;
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
    Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');
});

// Public Onboarding Endpoints
Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('api.invitations.accept');
Route::post('/claim-codes/claim', [ClaimCodeController::class, 'claim'])->name('api.claim-codes.claim');

// Public Telegram Webhook Endpoint
Route::post('/telegram/webhook/{school}', [TelegramController::class, 'webhook'])->name('api.telegram.webhook');

// Public Chapa Payment Webhook & Callback
Route::post('/webhooks/chapa', [ChapaWebhookController::class, 'handleWebhook'])->name('api.webhooks.chapa');
Route::get('/payments/chapa/callback', [ChapaWebhookController::class, 'handleCallback'])->name('api.payments.chapa.callback');

// Authenticated Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Current user profile & session
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard')->middleware('tenant.user');

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

        // Weekly Timetable / Scheduling (Prompt 9)
        Route::get('/sections/{section}/timetable', [TimetableController::class, 'getSectionTimetable'])->name('api.sections.timetable.get');
        Route::post('/sections/{section}/timetable', [TimetableController::class, 'storeSlot'])->name('api.sections.timetable.post');
        Route::post('/sections/{section}/timetable/batch', [TimetableController::class, 'batchStoreSlots'])->name('api.sections.timetable.batch');
        Route::put('/timetable-slots/{timetableSlot}', [TimetableController::class, 'updateSlot'])->name('api.timetable-slots.update');
        Route::delete('/timetable-slots/{timetableSlot}', [TimetableController::class, 'destroySlot'])->name('api.timetable-slots.destroy');

        // Teacher Personal Timetable (Aggregated across sections)
        Route::get('/teachers/{teacher}/timetable', [TimetableController::class, 'getTeacherTimetable'])->name('api.teachers.timetable');
        Route::get('/teacher/timetable', [TimetableController::class, 'getMyTeacherTimetable'])->name('api.teacher.my-timetable');

        // Staff Directory (Read)
        Route::get('/staff', [StaffController::class, 'index'])->name('api.staff.index');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('api.staff.show');

        // Students (Read) & Attendance Summaries
        Route::get('/students', [StudentController::class, 'index'])->name('api.students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('api.students.show');
        Route::get('/students/{student}/attendance', [AttendanceController::class, 'getStudentAttendanceSummary'])->name('api.students.attendance');
        Route::get('/students/{student}/attendance-summary', [AttendanceController::class, 'getStudentAttendanceSummary'])->name('api.students.attendance-summary');
        Route::get('/students/{student}/timetable', [TimetableController::class, 'getStudentTimetable'])->name('api.students.timetable');

        // Student Self View
        Route::get('/student/attendance', [AttendanceController::class, 'getMyStudentAttendance'])->name('api.student.my-attendance');
        Route::get('/student/report-cards', [ReportCardController::class, 'studentReportCards'])->name('api.student.report-cards');
        Route::get('/student/report-cards/{reportCard}/pdf', [ReportCardController::class, 'studentDownloadPdf'])->name('api.student.report-cards.pdf');
        Route::get('/student/timetable', [TimetableController::class, 'getMyStudentTimetable'])->name('api.student.timetable');
        Route::get('/student/borrowed-books', [LibraryController::class, 'myBorrowedBooks'])->name('api.student.borrowed-books');
        Route::get('/student/transport', [TransportController::class, 'myStudentTransport'])->name('api.student.transport');
        Route::get('/student/electives/available', [ElectiveController::class, 'getAvailableForStudent'])->name('api.student.electives.available');
        Route::get('/student/electives', [ElectiveController::class, 'getMySelections'])->name('api.student.electives.index');
        Route::post('/student/electives/select', [ElectiveController::class, 'studentSelect'])->name('api.student.electives.select');
        Route::post('/student/electives/drop', [ElectiveController::class, 'studentDrop'])->name('api.student.electives.drop');

        // Parent Portal (Child switching & dashboard access)
        Route::prefix('parent')->group(function () {
            Route::get('/children', [ParentPortalController::class, 'children'])->name('api.parent.children');
            Route::get('/children/{student}/dashboard', [ParentPortalController::class, 'childDashboard'])->name('api.parent.child-dashboard');
            Route::get('/children/{student}/attendance', [AttendanceController::class, 'getParentChildAttendance'])->name('api.parent.child-attendance');
            Route::get('/children/{student}/report-cards', [ReportCardController::class, 'parentChildReportCards'])->name('api.parent.child-report-cards');
            Route::get('/children/{student}/report-cards/{reportCard}/pdf', [ReportCardController::class, 'parentDownloadChildPdf'])->name('api.parent.child-report-cards.pdf');
            Route::get('/children/{student}/timetable', [TimetableController::class, 'getParentChildTimetable'])->name('api.parent.child-timetable');
            Route::get('/children/{student}/borrowed-books', [LibraryController::class, 'childBorrowedBooks'])->name('api.parent.child-borrowed-books');
            Route::get('/children/{student}/transport', [TransportController::class, 'parentChildTransport'])->name('api.parent.child-transport');
            Route::get('/children/{student}/invoices', [ParentBillingController::class, 'invoices'])->name('api.parent.child-invoices');
            Route::get('/children/{student}/payments', [ParentBillingController::class, 'payments'])->name('api.parent.child-payments');
            Route::post('/invoices/{invoice}/pay-online', [ParentBillingController::class, 'payOnline'])->name('api.parent.invoices.pay-online');
            Route::get('/children/{student}/electives/available', [ElectiveController::class, 'getAvailableForChild'])->name('api.parent.child-electives.available');
            Route::get('/children/{student}/electives', [ElectiveController::class, 'getChildSelections'])->name('api.parent.child-electives.index');
            Route::post('/children/{student}/electives/select', [ElectiveController::class, 'parentSelect'])->name('api.parent.child-electives.select');
            Route::post('/children/{student}/electives/drop', [ElectiveController::class, 'parentDrop'])->name('api.parent.child-electives.drop');
        });

        // Payment Receipts (PDF download and browser preview)
        Route::get('/payments/{payment}/receipt', [PaymentReceiptController::class, 'downloadReceipt'])->name('api.payments.receipt.download');
        Route::get('/payments/{payment}/receipt/preview', [PaymentReceiptController::class, 'streamReceipt'])->name('api.payments.receipt.preview');

        // Transport Routes (Read)
        Route::get('/transport/routes', [TransportController::class, 'routes'])->name('api.transport.routes.index');
        Route::get('/transport/routes/{route}', [TransportController::class, 'showRoute'])->name('api.transport.routes.show');

        // Library Catalog (Read-only for all school members)
        Route::get('/library/books', [LibraryController::class, 'books'])->name('api.library.books.index');
        Route::get('/library/books/{book}', [LibraryController::class, 'showBook'])->name('api.library.books.show');

        // Library Management & Circulation (Librarian, School Admin, Super Admin)
        Route::middleware('role:school_admin,librarian,super_admin')->prefix('library')->group(function () {
            Route::get('/summary', [LibraryController::class, 'summary'])->name('api.library.summary');
            Route::post('/books', [LibraryController::class, 'storeBook'])->name('api.library.books.store');
            Route::put('/books/{book}', [LibraryController::class, 'updateBook'])->name('api.library.books.update');
            Route::delete('/books/{book}', [LibraryController::class, 'destroyBook'])->name('api.library.books.destroy');
            Route::get('/loans', [LibraryController::class, 'loans'])->name('api.library.loans.index');
            Route::post('/loans/checkout', [LibraryController::class, 'checkout'])->name('api.library.loans.checkout');
            Route::post('/loans/{loan}/checkin', [LibraryController::class, 'checkin'])->name('api.library.loans.checkin');
            Route::get('/fines', [LibraryController::class, 'fines'])->name('api.library.fines.index');
            Route::post('/fines/{fine}/pay', [LibraryController::class, 'payFine'])->name('api.library.fines.pay');
            Route::post('/fines/{fine}/waive', [LibraryController::class, 'waiveFine'])->name('api.library.fines.waive');
        });

        // Announcements (Prompt 12)
        Route::prefix('announcements')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])->name('api.announcements.index');
            Route::post('/', [AnnouncementController::class, 'store'])->name('api.announcements.store');
            Route::get('/{announcement}', [AnnouncementController::class, 'show'])->name('api.announcements.show');
            Route::put('/{announcement}', [AnnouncementController::class, 'update'])->name('api.announcements.update');
            Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->name('api.announcements.destroy');
            Route::post('/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('api.announcements.publish');
            Route::get('/{announcement}/stats', [AnnouncementController::class, 'stats'])->name('api.announcements.stats');
        });

        // Direct Teacher-Parent Messaging per Student (Prompt 12)
        Route::prefix('communications')->group(function () {
            Route::get('/threads', [CommunicationController::class, 'threads'])->name('api.communications.threads.index');
            Route::post('/threads', [CommunicationController::class, 'storeThread'])->name('api.communications.threads.store');
            Route::get('/threads/{thread}', [CommunicationController::class, 'showThread'])->name('api.communications.threads.show');
            Route::post('/threads/{thread}/messages', [CommunicationController::class, 'reply'])->name('api.communications.threads.reply');
            Route::get('/students/{student}/threads', [CommunicationController::class, 'studentThreads'])->name('api.communications.students.threads');
        });

        // In-App Notification Center & Preferences (Prompt 12 & 14)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationCenterController::class, 'index'])->name('api.notifications.index');
            Route::get('/preferences', [NotificationPreferenceController::class, 'show'])->name('api.notifications.preferences.show');
            Route::put('/preferences', [NotificationPreferenceController::class, 'update'])->name('api.notifications.preferences.update');
            Route::post('/{notification}/read', [NotificationCenterController::class, 'markRead'])->name('api.notifications.read');
            Route::post('/read-all', [NotificationCenterController::class, 'markAllRead'])->name('api.notifications.read-all');
            Route::delete('/{notification}', [NotificationCenterController::class, 'destroy'])->name('api.notifications.destroy');
        });

        // Telegram Integration (Prompt 12)
        Route::prefix('telegram')->group(function () {
            Route::get('/status', [TelegramController::class, 'status'])->name('api.telegram.status');
            Route::post('/link-code', [TelegramController::class, 'generateLinkCode'])->name('api.telegram.link-code');
            Route::post('/link', [TelegramController::class, 'linkAccount'])->name('api.telegram.link');
            Route::post('/unlink', [TelegramController::class, 'unlinkAccount'])->name('api.telegram.unlink');
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

            // Transport Management (Admin)
            Route::prefix('transport')->group(function () {
                Route::post('/routes', [TransportController::class, 'storeRoute'])->name('api.transport.routes.store');
                Route::put('/routes/{route}', [TransportController::class, 'updateRoute'])->name('api.transport.routes.update');
                Route::delete('/routes/{route}', [TransportController::class, 'destroyRoute'])->name('api.transport.routes.destroy');
                Route::post('/routes/{route}/stops', [TransportController::class, 'storeStop'])->name('api.transport.stops.store');
                Route::put('/stops/{stop}', [TransportController::class, 'updateStop'])->name('api.transport.stops.update');
                Route::delete('/stops/{stop}', [TransportController::class, 'destroyStop'])->name('api.transport.stops.destroy');
                Route::get('/assignments', [TransportController::class, 'assignments'])->name('api.transport.assignments.index');
                Route::post('/assignments', [TransportController::class, 'assignStudent'])->name('api.transport.assignments.store');
                Route::post('/sections/{section}/routes/{route}/assign', [TransportController::class, 'bulkAssignSection'])->name('api.transport.sections.assign');
                Route::delete('/students/{student}/assignment', [TransportController::class, 'unassignStudent'])->name('api.transport.students.unassign');
            });

            // Fee & Billing Management (Admin)
            Route::prefix('billing')->group(function () {
                Route::get('/fee-structures', [FeeStructureController::class, 'index'])->name('api.billing.fee-structures.index');
                Route::post('/fee-structures', [FeeStructureController::class, 'store'])->name('api.billing.fee-structures.store');
                Route::get('/fee-structures/{feeStructure}', [FeeStructureController::class, 'show'])->name('api.billing.fee-structures.show');
                Route::put('/fee-structures/{feeStructure}', [FeeStructureController::class, 'update'])->name('api.billing.fee-structures.update');
                Route::delete('/fee-structures/{feeStructure}', [FeeStructureController::class, 'destroy'])->name('api.billing.fee-structures.destroy');

                Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.billing.invoices.index');
                Route::post('/invoices/bulk-generate', [InvoiceController::class, 'bulkGenerate'])->name('api.billing.invoices.bulk-generate');
                Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('api.billing.invoices.show');
                Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('api.billing.invoices.payments.store');
                Route::get('/collections', [InvoiceController::class, 'collections'])->name('api.billing.collections');
            });

            // Elective / Optional Subject Management (Prompt 17)
            Route::prefix('electives')->group(function () {
                Route::get('/offerings', [ElectiveController::class, 'indexOfferings'])->name('api.electives.offerings.index');
                Route::post('/offerings', [ElectiveController::class, 'storeOffering'])->name('api.electives.offerings.store');
                Route::put('/offerings/{subjectOffering}', [ElectiveController::class, 'updateOffering'])->name('api.electives.offerings.update');
                Route::get('/offerings/{subjectOffering}/students', [ElectiveController::class, 'getOfferingStudents'])->name('api.electives.offerings.students');
                Route::post('/offerings/{subjectOffering}/enroll', [ElectiveController::class, 'adminEnroll'])->name('api.electives.offerings.enroll');
                Route::post('/offerings/{subjectOffering}/drop', [ElectiveController::class, 'adminDrop'])->name('api.electives.offerings.drop');
            });
        });
    });
});
