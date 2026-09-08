<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Card Published</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="background-color: #059669; padding: 20px 24px; color: #ffffff;">
            <h2 style="margin: 0; font-size: 18px; font-weight: 700;">Official Report Card Published</h2>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 4px;">{{ $reportCard->school?->name ?? 'Bina Schools' }}</div>
        </div>

        <div style="padding: 24px;">
            <p style="font-size: 14px; margin-top: 0;">Dear {{ $parent->name }},</p>
            <p style="font-size: 14px; line-height: 1.6; color: #334155;">
                The official academic report card for <strong>{{ $reportCard->student?->user?->name }}</strong> for <strong>{{ $reportCard->term?->name }}</strong> (Academic Year: {{ $reportCard->academicYear?->name }}) has been officially published and is now available.
            </p>

            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 18px; margin: 18px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-size: 12px; color: #065f46;">Overall Grade:</span>
                    <strong style="font-size: 14px; color: #047857;">{{ $reportCard->overall_grade }} ({{ $reportCard->average_percentage }}%)</strong>
                </div>
                @if($reportCard->gpa !== null)
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-size: 12px; color: #065f46;">GPA:</span>
                    <strong style="font-size: 13px; color: #047857;">{{ number_format($reportCard->gpa, 2) }}</strong>
                </div>
                @endif
                @if($reportCard->rank_in_section !== null)
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 12px; color: #065f46;">Rank in Section:</span>
                    <strong style="font-size: 13px; color: #047857;">#{{ $reportCard->rank_in_section }} of {{ $reportCard->total_students_in_section }}</strong>
                </div>
                @endif
            </div>

            <p style="font-size: 13px; line-height: 1.5; color: #64748b;">
                You can view the full subject-by-subject assessment breakdown, homeroom teacher remarks, attendance summary, and download the official signed PDF through the Parent Portal.
            </p>

            <div style="margin-top: 24px; text-align: center;">
                <a href="{{ url('/parents') }}" style="display: inline-block; background-color: #059669; color: #ffffff; padding: 10px 20px; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                    View &amp; Download Report Card &rarr;
                </a>
            </div>
        </div>

        <div style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px; text-align: center; font-size: 11px; color: #94a3b8;">
            Sent automatically by {{ $reportCard->school?->name ?? 'Bina Schools' }} via Bina Unified Notifications.
        </div>
    </div>
</body>
</html>
