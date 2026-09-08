<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Report Card - {{ $student->user->name ?? 'Student' }}</title>
    <style>
        @page {
            margin: 24px 30px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .school-name {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .school-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .report-title-badge {
            text-align: right;
        }
        .report-badge-text {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 12px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-badge {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 4px;
            color: {{ $reportCard->isPublished() ? '#15803d' : '#b45309' }};
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .info-grid {
            width: 100%;
        }
        .info-grid td {
            padding: 3px 6px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
        }
        .val {
            color: #0f172a;
            font-size: 11px;
        }
        .val-highlight {
            font-weight: bold;
            color: #0f172a;
        }
        table.grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.grades-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 7px 8px;
            text-align: left;
            border: 1px solid #0f172a;
        }
        table.grades-table th.center, table.grades-table td.center {
            text-align: center;
        }
        table.grades-table th.right, table.grades-table td.right {
            text-align: right;
        }
        table.grades-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10.5px;
        }
        table.grades-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .exam-pill {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            font-size: 8.5px;
            padding: 1px 4px;
            border-radius: 3px;
            margin-right: 3px;
        }
        .summary-wrapper {
            width: 100%;
            margin-bottom: 12px;
        }
        .summary-box {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 8px 12px;
        }
        .summary-num {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }
        .remarks-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }
        .remarks-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 3px;
        }
        .remarks-content {
            font-size: 10.5px;
            color: #1e293b;
            font-style: italic;
        }
        .scale-legend {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .scale-legend th {
            background-color: #e2e8f0;
            color: #334155;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            text-align: center;
        }
        .scale-legend td {
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            font-size: 9px;
            text-align: center;
        }
        .signatures-table {
            width: 100%;
            margin-top: 25px;
        }
        .signature-line {
            border-top: 1px solid #475569;
            margin-top: 35px;
            padding-top: 4px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #334155;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 65%; vertical-align: middle;">
                <div class="school-name">{{ $school->name ?? 'Bina Schools' }}</div>
                <div class="school-sub">
                    {{ $school->address ?? 'Main Campus' }} 
                    @if($school->contact_phone) | Tel: {{ $school->contact_phone }} @endif
                    @if($school->contact_email) | Email: {{ $school->contact_email }} @endif
                </div>
            </td>
            <td style="width: 35%; text-align: right; vertical-align: middle;">
                <div class="report-badge-text">ACADEMIC REPORT CARD</div>
                <div class="status-badge">
                    {{ strtoupper($reportCard->status) }}
                    @if($reportCard->published_at)
                        - {{ $reportCard->published_at->format('M d, Y') }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Student and Academic Context -->
    <div class="info-card">
        <table class="info-grid" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width: 25%;"><span class="label">Student:</span> <div class="val val-highlight">{{ $student->user->name ?? 'N/A' }}</div></td>
                <td style="width: 25%;"><span class="label">Admission No:</span> <div class="val">{{ $student->admission_number ?? 'N/A' }}</div></td>
                <td style="width: 25%;"><span class="label">Academic Year:</span> <div class="val">{{ $academicYear->name ?? 'N/A' }}</div></td>
                <td style="width: 25%;"><span class="label">Term / Semester:</span> <div class="val val-highlight">{{ $term->name ?? 'N/A' }}</div></td>
            </tr>
            <tr>
                <td><span class="label">Grade Level:</span> <div class="val">{{ $section->gradeLevel->name ?? 'N/A' }}</div></td>
                <td><span class="label">Section / Stream:</span> <div class="val">{{ $section->name ?? 'N/A' }}</div></td>
                <td><span class="label">Homeroom Teacher:</span> <div class="val">{{ $section->homeroomTeacher->name ?? 'Not Assigned' }}</div></td>
                <td><span class="label">Grading Scale:</span> <div class="val">{{ $gradingScale->name ?? 'Standard' }}</div></td>
            </tr>
        </table>
    </div>

    <!-- Subjects & Grades Table -->
    <table class="grades-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 28%;">Subject</th>
                <th style="width: 22%;">Assessments Breakdown</th>
                <th style="width: 10%;" class="center">Average %</th>
                <th style="width: 10%;" class="center">Letter Grade</th>
                <th style="width: 8%;" class="center">GPA</th>
                <th style="width: 22%;">Teacher Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>
                        <strong style="color: #0f172a;">{{ $item->subject->name ?? 'Subject' }}</strong>
                        <div style="font-size: 9px; color: #64748b;">Code: {{ $item->subject->code ?? '-' }}</div>
                    </td>
                    <td>
                        @if(!empty($item->exam_breakdown))
                            @foreach($item->exam_breakdown as $exam)
                                <span class="exam-pill">
                                    {{ $exam['exam_name'] ?? 'Exam' }}: {{ $exam['marks_obtained'] }}/{{ $exam['max_marks'] }} ({{ $exam['percentage'] }}%)
                                </span>
                            @endforeach
                        @else
                            <span style="color: #94a3b8; font-size: 9px;">—</span>
                        @endif
                    </td>
                    <td class="center">
                        <strong>{{ number_format($item->percentage, 1) }}%</strong>
                    </td>
                    <td class="center">
                        <strong style="font-size: 12px; color: #0f172a;">{{ $item->letter_grade ?? 'N/A' }}</strong>
                    </td>
                    <td class="center">
                        {{ number_format($item->gpa_point, 2) }}
                    </td>
                    <td>
                        <span style="font-size: 9.5px; color: #334155;">{{ $item->teacher_remarks ?? 'Good effort' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center" style="padding: 16px; color: #64748b;">
                        No graded subjects recorded for this term.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Performance & Attendance Summaries -->
    <table class="summary-wrapper" cellpadding="0" cellspacing="0">
        <tr>
            <!-- Academic Performance -->
            <td style="width: 50%; padding-right: 8px; vertical-align: top;">
                <div class="summary-box">
                    <div class="label" style="margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;">
                        Term Academic Standing
                    </div>
                    <table style="width: 100%;" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                <span class="label">Overall Average:</span>
                                <div class="summary-num">{{ number_format($reportCard->average_percentage, 1) }}%</div>
                            </td>
                            <td>
                                <span class="label">Overall Grade:</span>
                                <div class="summary-num" style="color: #1d4ed8;">{{ $reportCard->overall_grade ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <span class="label">Term GPA:</span>
                                <div class="summary-num">{{ number_format($reportCard->gpa, 2) }}</div>
                            </td>
                            <td>
                                <span class="label">Class Rank:</span>
                                <div class="summary-num">
                                    @if($reportCard->rank_in_section)
                                        #{{ $reportCard->rank_in_section }} <span style="font-size: 11px; font-weight: normal; color: #64748b;">/ {{ $reportCard->total_students_in_section ?? '-' }}</span>
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- Attendance Summary -->
            <td style="width: 50%; padding-left: 8px; vertical-align: top;">
                <div class="summary-box">
                    <div class="label" style="margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;">
                        Attendance Summary (School Calendar Adjusted)
                    </div>
                    <table style="width: 100%;" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                <span class="label">School Days:</span>
                                <div style="font-size: 13px; font-weight: bold;">{{ $attendanceSummary['total_school_days'] ?? 0 }}</div>
                            </td>
                            <td>
                                <span class="label">Present:</span>
                                <div style="font-size: 13px; font-weight: bold; color: #16a34a;">{{ $attendanceSummary['present_days'] ?? 0 }}</div>
                            </td>
                            <td>
                                <span class="label">Absent:</span>
                                <div style="font-size: 13px; font-weight: bold; color: #dc2626;">{{ $attendanceSummary['absent_days'] ?? 0 }}</div>
                            </td>
                            <td>
                                <span class="label">Attendance:</span>
                                <div style="font-size: 13px; font-weight: bold; color: #0284c7;">
                                    {{ isset($attendanceSummary['attendance_percentage']) ? number_format($attendanceSummary['attendance_percentage'], 1) . '%' : '100%' }}
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Remarks Blocks -->
    @if($reportCard->homeroom_remarks)
        <div class="remarks-box">
            <div class="remarks-title">Homeroom Teacher Remarks:</div>
            <div class="remarks-content">"{{ $reportCard->homeroom_remarks }}"</div>
        </div>
    @endif

    @if($reportCard->principal_remarks)
        <div class="remarks-box">
            <div class="remarks-title">Principal / Administrator Remarks:</div>
            <div class="remarks-content">"{{ $reportCard->principal_remarks }}"</div>
        </div>
    @endif

    <!-- Grading Scale Key Legend -->
    <div style="margin-top: 6px; margin-bottom: 4px;">
        <span class="label">Grading Scale Legend ({{ $gradingScale->name ?? 'Standard' }}):</span>
    </div>
    <table class="scale-legend" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                @foreach($gradingScale->rules ?? [] as $rule)
                    <th>{{ $rule['grade'] ?? '-' }} ({{ $rule['min_score'] }}% - {{ $rule['max_score'] }}%)</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($gradingScale->rules ?? [] as $rule)
                    <td>{{ $rule['description'] ?? 'GPA: ' . ($rule['gpa_point'] ?? '-') }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <!-- Signatures -->
    <table class="signatures-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 30%;">
                <div class="signature-line">Homeroom Teacher Signature</div>
            </td>
            <td style="width: 40%; text-align: center; vertical-align: bottom; font-size: 9.5px; color: #64748b;">
                Date Issued: {{ now()->format('F d, Y') }}
            </td>
            <td style="width: 30%;">
                <div class="signature-line">Principal Signature & Stamp</div>
            </td>
        </tr>
    </table>

</body>
</html>
