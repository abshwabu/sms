<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Official Invitation</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <div style="background-color: #4f46e5; padding: 24px; color: #ffffff;">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; opacity: 0.9; margin-bottom: 4px;">Official School Invitation</div>
            <h1 style="margin: 0; font-size: 20px; font-weight: 700;">{{ $schoolName }}</h1>
        </div>

        <div style="padding: 28px;">
            <p style="font-size: 14px; margin-top: 0; color: #1e293b;">Dear {{ $recipientName }},</p>
            <p style="font-size: 14px; line-height: 1.6; color: #334155;">
                You have been invited by <strong>{{ $inviterName }}</strong> to join <strong>{{ $schoolName }}</strong> on the Bina Schools educational platform as a <strong>{{ $roleLabel }}</strong>.
            </p>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; color: #64748b;">Role:</span>
                    <strong style="font-size: 12px; color: #1e293b;">{{ $roleLabel }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; color: #64748b;">School Subdomain:</span>
                    <strong style="font-size: 12px; color: #4f46e5; font-family: monospace;">{{ $school?->subdomain ?? 'school' }}.bina.edu</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 12px; color: #64748b;">Invitation Valid Until:</span>
                    <strong style="font-size: 12px; color: #b45309;">{{ $invitation->expires_at ? $invitation->expires_at->format('l, F j, Y') : '7 days' }}</strong>
                </div>
            </div>

            @if(!empty($temporaryPassword))
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin: 20px 0;">
                <div style="font-size: 12px; font-weight: 700; color: #166534; margin-bottom: 6px;">Your Temporary Login Credentials:</div>
                <div style="font-size: 12px; color: #15803d; margin-bottom: 4px;">Email: <strong>{{ $invitation->email }}</strong></div>
                <div style="font-size: 12px; color: #15803d;">Temporary Password: <strong style="font-family: monospace; background: #dcfce7; padding: 2px 6px; border-radius: 4px;">{{ $temporaryPassword }}</strong></div>
                <p style="font-size: 11px; color: #166534; margin: 8px 0 0 0;">Please sign in and change your password immediately upon first access.</p>
            </div>
            @endif

            <div style="margin: 28px 0; text-align: center;">
                <a href="{{ !empty($temporaryPassword) ? $loginUrl : $inviteUrl }}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; padding: 12px 24px; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);">
                    {{ !empty($temporaryPassword) ? 'Sign In to Portal &rarr;' : 'Accept Invitation & Activate Account &rarr;' }}
                </a>
            </div>

            <p style="font-size: 12px; line-height: 1.5; color: #64748b;">
                If the button above does not work, copy and paste this link into your web browser:
                <br>
                <a href="{{ !empty($temporaryPassword) ? $loginUrl : $inviteUrl }}" style="color: #4f46e5; word-break: break-all; font-family: monospace; font-size: 11px;">
                    {{ !empty($temporaryPassword) ? $loginUrl : $inviteUrl }}
                </a>
            </p>
        </div>

        <div style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px; text-align: center; font-size: 11px; color: #94a3b8;">
            Sent automatically by {{ $schoolName }} via Bina Schools. If you were not expecting this invitation, you can safely disregard this email.
        </div>
    </div>
</body>
</html>
