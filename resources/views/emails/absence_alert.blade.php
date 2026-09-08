<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Absence Notification</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="background-color: #b91c1c; padding: 20px 24px; color: #ffffff;">
            <h2 style="margin: 0; font-size: 18px; font-weight: 700;">Student Daily Absence Alert</h2>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 4px;">{{ $student->school?->name ?? 'Bina Schools' }}</div>
        </div>

        <div style="padding: 24px;">
            <p style="font-size: 14px; margin-top: 0;">Dear {{ $parent->name }},</p>
            <p style="font-size: 14px; line-height: 1.6; color: #334155;">
                This notice is to inform you that your child, <strong>{{ $student->user?->name }}</strong> (Admission #: {{ $student->admission_number }}), was recorded as <strong>ABSENT</strong> for daily homeroom attendance on:
            </p>

            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 18px; margin: 18px 0;">
                <div style="font-size: 13px; color: #991b1b; font-weight: 600;">
                    Date: {{ $record->date instanceof \DateTimeInterface ? $record->date->format('l, F j, Y') : $record->date }}
                </div>
                <div style="font-size: 12px; color: #b91c1c; margin-top: 4px;">
                    Section: {{ $student->currentSection?->name ?? 'Homeroom' }}
                    @if($record->remarks)
                        &bull; Remarks: {{ $record->remarks }}
                    @endif
                </div>
            </div>

            <p style="font-size: 13px; line-height: 1.5; color: #64748b;">
                If this absence was unplanned or in error, please contact the school office or send a direct message to the homeroom teacher via the Bina Parent Portal.
            </p>

            <div style="margin-top: 24px; text-align: center;">
                <a href="{{ url('/parents') }}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; padding: 10px 20px; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                    Open Parent Portal &rarr;
                </a>
            </div>
        </div>

        <div style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px; text-align: center; font-size: 11px; color: #94a3b8;">
            Sent automatically by {{ $student->school?->name ?? 'Bina Schools' }} via Bina Unified Notifications.
        </div>
    </div>
</body>
</html>
