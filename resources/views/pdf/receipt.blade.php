<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt - {{ $payment->payment_number }}</title>
    <style>
        @page {
            margin: 28px 36px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .school-name {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .school-meta {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }
        .receipt-badge-container {
            text-align: right;
            vertical-align: top;
        }
        .receipt-badge {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            padding: 6px 14px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .receipt-status-paid {
            font-size: 10px;
            font-weight: bold;
            color: #15803d;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 16px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
        }
        .info-col {
            vertical-align: top;
            width: 50%;
        }
        .info-row {
            margin-bottom: 6px;
        }
        .info-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 11px;
            color: #0f172a;
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .items-table th {
            background-color: #0f172a;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        .items-table th.text-right,
        .items-table td.text-right {
            text-align: right;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
        }
        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .summary-container {
            width: 100%;
            margin-top: 12px;
            margin-bottom: 24px;
        }
        .summary-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 6px 8px;
            font-size: 11px;
        }
        .summary-table .summary-label {
            color: #64748b;
            text-align: right;
        }
        .summary-table .summary-value {
            font-weight: bold;
            color: #0f172a;
            text-align: right;
        }
        .summary-table .highlight-row td {
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            font-size: 13px;
            font-weight: bold;
            background-color: #f1f5f9;
        }
        .signature-table {
            width: 100%;
            margin-top: 40px;
        }
        .signature-cell {
            width: 45%;
            vertical-align: top;
            text-align: center;
        }
        .signature-line {
            border-top: 1px dashed #64748b;
            margin: 36px 20px 6px 20px;
        }
        .signature-title {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
        }
        .footer-note {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>

    <!-- School Header -->
    <table class="header-table">
        <tr>
            <td>
                <div class="school-name">{{ $school->name ?? 'Bina Schools' }}</div>
                <div class="school-sub">Official Payment Receipt & Billing Acknowledgment</div>
                <div class="school-meta">Date Issued: {{ \Carbon\Carbon::parse($payment->paid_at)->format('F d, Y - h:i A') }}</div>
            </td>
            <td class="receipt-badge-container">
                <div class="receipt-badge">Payment Receipt</div>
                <div class="receipt-status-paid">● Status: Completed</div>
            </td>
        </tr>
    </table>

    <!-- Student & Payment Information -->
    <table class="info-grid">
        <tr>
            <td class="info-col">
                <div class="info-row">
                    <div class="info-label">Student Name</div>
                    <div class="info-value">{{ $student->user->name ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Admission Number</div>
                    <div class="info-value">{{ $student->admission_number }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Grade & Section</div>
                    <div class="info-value">
                        {{ $student->currentSection?->gradeLevel?->name ?? 'Grade' }} - {{ $student->currentSection?->name ?? 'Section' }}
                    </div>
                </div>
            </td>
            <td class="info-col">
                <div class="info-row">
                    <div class="info-label">Receipt Number</div>
                    <div class="info-value" style="color: #0369a1;">{{ $payment->payment_number }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Invoice Number & Term</div>
                    <div class="info-value">#{{ $invoice->invoice_number }} ({{ $invoice->term?->name ?? 'Term' }})</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Method & Gateway</div>
                    <div class="info-value">
                        {{ strtoupper(str_replace('_', ' ', $payment->method)) }}
                        @if($payment->gateway)
                            <span style="color: #64748b; font-weight: normal;">({{ ucfirst($payment->gateway) }})</span>
                        @endif
                    </div>
                </div>
                @if($payment->gateway_reference)
                <div class="info-row">
                    <div class="info-label">Gateway Reference / TxRef</div>
                    <div class="info-value" style="font-size: 10px; font-family: monospace;">{{ $payment->gateway_reference }}</div>
                </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Invoice Line Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 55%;">Description</th>
                <th style="width: 20%;">Category</th>
                <th class="text-right" style="width: 20%;">Amount (ETB)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->name }}</strong>
                    @if($item->description)
                        <div style="font-size: 9px; color: #64748b;">{{ $item->description }}</div>
                    @endif
                </td>
                <td style="text-transform: capitalize;">{{ $item->feeStructure?->category ?? 'Fee' }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals Summary -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-label">Invoice Total:</td>
                <td class="summary-value">{{ number_format($invoice->total_amount, 2) }} ETB</td>
            </tr>
            <tr class="highlight-row">
                <td class="summary-label" style="color: #0f172a;">Amount Paid (This Receipt):</td>
                <td class="summary-value" style="color: #15803d;">{{ number_format($payment->amount, 2) }} ETB</td>
            </tr>
            <tr>
                <td class="summary-label">Total Paid to Date:</td>
                <td class="summary-value">{{ number_format($invoice->paid_amount, 2) }} ETB</td>
            </tr>
            <tr>
                <td class="summary-label">Remaining Balance:</td>
                <td class="summary-value" style="color: {{ $invoice->balance() > 0 ? '#b91c1c' : '#15803d' }};">
                    {{ number_format($invoice->balance(), 2) }} ETB
                </td>
            </tr>
        </table>
    </div>

    <!-- Signatures -->
    <table class="signature-table">
        <tr>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-title">Authorized School Bursar / Cashier</div>
            </td>
            <td style="width: 10%;"></td>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-title">Parent / Guardian Signature</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        This is an official system-generated electronic receipt issued by Bina Schools Management System.
        Receipt Verification Code: {{ sha1($payment->payment_number . $payment->paid_at) }}
    </div>

</body>
</html>
