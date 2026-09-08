<?php

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Course;
use App\Models\GradeLevel;
use App\Models\LibraryFine;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolCalendar;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\TransportRoute;
use App\Services\NotificationPipelineService;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scheduled Job 1: Daily Homeroom Attendance Reminders
 * Checks which sections have not yet recorded attendance on school days and alerts homeroom teachers.
 */
Artisan::command('attendance:remind-daily', function () {
    $today = Carbon::today()->format('Y-m-d');
    $schools = School::where('subscription_status', 'active')->get();
    $remindersSent = 0;

    foreach ($schools as $school) {
        app(TenantManager::class)->setTenant($school);

        // Skip non-school days (holidays or declared closures)
        $isHoliday = SchoolCalendar::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('date', $today)
            ->where('is_school_day', false)
            ->exists();

        if ($isHoliday) {
            continue;
        }

        // Active Academic Year
        $activeYear = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->where('is_closed', false)
            ->first();

        if (! $activeYear) {
            continue;
        }

        $sections = Section::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $activeYear->id)
            ->whereNotNull('homeroom_teacher_id')
            ->with('homeroomTeacher')
            ->get();

        foreach ($sections as $section) {
            $marked = AttendanceRecord::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('section_id', $section->id)
                ->where('date', $today)
                ->exists();

            if (! $marked && $section->homeroomTeacher) {
                app(NotificationPipelineService::class)->send(
                    recipient: $section->homeroomTeacher,
                    type: 'attendance_reminder',
                    title: "Attendance Roll Call Pending: {$section->name}",
                    body: "Daily attendance for {$section->name} has not been recorded yet today ({$today}). Please submit the section roll call.",
                    payload: [
                        'section_id' => $section->id,
                        'section_name' => $section->name,
                        'date' => $today,
                        'action_url' => "/attendance?section_id={$section->id}",
                    ],
                    category: 'attendance',
                    schoolId: $school->id
                );
                $remindersSent++;
            }
        }
    }

    $this->info("Attendance reminders sent to {$remindersSent} homeroom teacher(s).");
})->purpose('Send daily attendance roll call reminders to homeroom teachers');

/**
 * Scheduled Job 2: Overdue Library Checks & Fines Ledger Update
 * Identifies overdue loans, calculates progressive overdue fines, and notifies borrowers.
 */
Artisan::command('library:check-overdue', function () {
    $now = Carbon::now();
    $schools = School::where('subscription_status', 'active')->get();
    $processedCount = 0;

    foreach ($schools as $school) {
        app(TenantManager::class)->setTenant($school);

        $overdueLoans = BookLoan::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereNull('returned_at')
            ->where('due_at', '<', $now)
            ->with(['book', 'student.user'])
            ->get();

        foreach ($overdueLoans as $loan) {
            if ($loan->status !== 'overdue') {
                $loan->update(['status' => 'overdue']);
            }

            $daysOverdue = max(1, (int) $now->diffInDays($loan->due_at));
            $dailyRate = 0.50; // $0.50 per day overdue fine
            $fineAmount = round($daysOverdue * $dailyRate, 2);

            $fine = LibraryFine::withoutGlobalScopes()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'book_loan_id' => $loan->id,
                ],
                [
                    'student_id' => $loan->student_id,
                    'user_id' => $loan->user_id,
                    'amount' => $fineAmount,
                    'type' => 'overdue',
                    'status' => 'unpaid',
                    'notes' => "Automated overdue check: {$daysOverdue} day(s) overdue at \${$dailyRate}/day.",
                ]
            );

            if ($loan->student?->user) {
                app(NotificationPipelineService::class)->send(
                    recipient: $loan->student->user,
                    type: 'library_overdue',
                    title: "Overdue Library Book: {$loan->book?->title}",
                    body: "The borrowed book '{$loan->book?->title}' is {$daysOverdue} day(s) overdue. Outstanding fine: \${$fineAmount}.",
                    payload: [
                        'loan_id' => $loan->id,
                        'book_title' => $loan->book?->title,
                        'due_at' => $loan->due_at->format('Y-m-d'),
                        'fine_amount' => $fineAmount,
                        'action_url' => '/library',
                    ],
                    category: 'library',
                    schoolId: $school->id
                );
            }

            $processedCount++;
        }
    }

    $this->info("Processed {$processedCount} overdue library loan(s).");
})->purpose('Check for overdue library loans, update fines, and notify borrowers');

/**
 * Scheduled Job 3: Per-Tenant Automated Backup
 * Creates isolated JSON archive per tenant containing academic and user records.
 */
Artisan::command('tenants:backup {school_id?}', function () {
    $targetId = $this->argument('school_id');
    $schools = $targetId
        ? School::where('id', $targetId)->get()
        : School::all();

    if ($schools->isEmpty()) {
        $this->error('No matching school found for backup.');
        return 1;
    }

    $backupDir = storage_path('app/backups');
    if (! file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Ymd_His');

    foreach ($schools as $school) {
        app(TenantManager::class)->setTenant($school);

        $data = [
            'metadata' => [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'subdomain' => $school->subdomain,
                'backup_timestamp' => $timestamp,
                'generator' => 'Bina Schools Tenant Backup System v1.0',
            ],
            'school' => $school->toArray(),
            'academic_years' => AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->with('terms')->get()->toArray(),
            'grade_levels' => GradeLevel::withoutGlobalScopes()->where('school_id', $school->id)->get()->toArray(),
            'sections' => Section::withoutGlobalScopes()->where('school_id', $school->id)->get()->toArray(),
            'courses' => Course::withoutGlobalScopes()->where('school_id', $school->id)->get()->toArray(),
            'staff' => Staff::withoutGlobalScopes()->where('school_id', $school->id)->with('user:id,name,email,role')->get()->toArray(),
            'students' => Student::withoutGlobalScopes()->where('school_id', $school->id)->with('user:id,name,email')->get()->toArray(),
            'attendance_records_count' => AttendanceRecord::withoutGlobalScopes()->where('school_id', $school->id)->count(),
            'report_cards' => ReportCard::withoutGlobalScopes()->where('school_id', $school->id)->with('items')->get()->toArray(),
            'timetable_slots' => TimetableSlot::withoutGlobalScopes()->where('school_id', $school->id)->get()->toArray(),
            'library_books' => Book::withoutGlobalScopes()->where('school_id', $school->id)->get()->toArray(),
            'library_loans' => BookLoan::withoutGlobalScopes()->where('school_id', $school->id)->get()->toArray(),
            'transport_routes' => TransportRoute::withoutGlobalScopes()->where('school_id', $school->id)->with('stops')->get()->toArray(),
            'announcements' => Announcement::withoutGlobalScopes()->where('school_id', $school->id)->get()->toArray(),
        ];

        $filename = "{$school->subdomain}_backup_{$timestamp}.json";
        $filepath = "{$backupDir}/{$filename}";
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Backup created for [{$school->name}]: {$filename} (" . round(strlen(json_encode($data)) / 1024, 1) . ' KB)');
    }

    return 0;
})->purpose('Generate complete tenant backup export in JSON format per school');

/**
 * Console Schedule Definition
 */
Schedule::command('attendance:remind-daily')->weekdays()->at('09:30');
Schedule::command('library:check-overdue')->dailyAt('06:00');
Schedule::command('tenants:backup')->dailyAt('01:00');
