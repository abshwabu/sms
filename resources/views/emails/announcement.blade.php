<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $announcement->title }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .header { background: #4f46e5; color: #ffffff; padding: 24px; text-align: left; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 4px 0 0 0; font-size: 12px; opacity: 0.85; }
        .content { padding: 28px; line-height: 1.6; }
        .content h2 { margin-top: 0; color: #0f172a; font-size: 18px; }
        .body-text { white-space: pre-line; margin: 16px 0; font-size: 14px; color: #1e293b; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: #e0e7ff; color: #4338ca; }
        .footer { background: #f1f5f9; padding: 16px 24px; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $announcement->school?->name ?? 'Bina Schools' }}</h1>
            <p>Official School Announcement</p>
        </div>
        <div class="content">
            <span class="badge">{{ $announcement->priority }} priority</span>
            <h2>{{ $announcement->title }}</h2>
            <div class="body-text">{{ $announcement->body }}</div>
        </div>
        <div class="footer">
            Published on {{ $announcement->published_at ? $announcement->published_at->format('M d, Y') : now()->format('M d, Y') }} 
            by {{ $announcement->author?->name ?? 'School Administration' }}.
        </div>
    </div>
</body>
</html>
