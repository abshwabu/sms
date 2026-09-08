<?php

namespace App\Services;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

class ReceiptService
{
    /**
     * Generate a PDF instance for a payment receipt.
     */
    public function generatePdf(Payment $payment): DomPdfWrapper
    {
        $payment->loadMissing([
            'school',
            'invoice.student.user',
            'invoice.student.currentSection.gradeLevel',
            'invoice.term.academicYear',
            'invoice.items.feeStructure',
            'recordedByUser',
        ]);

        $invoice = $payment->invoice;
        $student = $invoice?->student;
        $school = $payment->school ?: $invoice?->school;

        $data = [
            'payment' => $payment,
            'invoice' => $invoice,
            'student' => $student,
            'school' => $school,
        ];

        return Pdf::loadView('pdf.receipt', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
    }
}
