<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\BillingService;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FeeAndBillingSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);
        $billingService = app(BillingService::class);

        // =========================================================================
        // 1. Greenwood High (High School)
        // =========================================================================
        $greenwood = School::where('subdomain', 'greenwood')->first();
        if ($greenwood) {
            $tenantManager->setTenant($greenwood);

            $activeYear = AcademicYear::where('school_id', $greenwood->id)->where('name', '2025/2026')->first();
            $fallTerm = Term::where('school_id', $greenwood->id)->where('academic_year_id', $activeYear?->id)->where('name', 'Fall Semester')->first();
            $grade10 = GradeLevel::where('school_id', $greenwood->id)->where('code', 'G10')->first();
            $grade9 = GradeLevel::where('school_id', $greenwood->id)->where('code', 'G9')->first();
            $greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->first();

            if ($activeYear && $fallTerm) {
                // A. Grade 9 Tuition
                if ($grade9) {
                    FeeStructure::updateOrCreate(
                        [
                            'school_id' => $greenwood->id,
                            'academic_year_id' => $activeYear->id,
                            'term_id' => $fallTerm->id,
                            'grade_level_id' => $grade9->id,
                            'name' => 'Grade 9 Tuition Fee',
                        ],
                        [
                            'category' => 'tuition',
                            'amount' => 15000.00,
                            'is_mandatory' => true,
                            'condition_type' => 'none',
                            'description' => 'Comprehensive academic curriculum instruction for Freshman year.',
                        ]
                    );
                }

                // B. Grade 10 Tuition
                if ($grade10) {
                    FeeStructure::updateOrCreate(
                        [
                            'school_id' => $greenwood->id,
                            'academic_year_id' => $activeYear->id,
                            'term_id' => $fallTerm->id,
                            'grade_level_id' => $grade10->id,
                            'name' => 'Grade 10 Tuition Fee',
                        ],
                        [
                            'category' => 'tuition',
                            'amount' => 16500.00,
                            'is_mandatory' => true,
                            'condition_type' => 'none',
                            'description' => 'Academic curriculum instruction for Sophomore high school year.',
                        ]
                    );
                }

                // C. School-wide Facility & Technology Fee
                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $greenwood->id,
                        'academic_year_id' => $activeYear->id,
                        'term_id' => $fallTerm->id,
                        'grade_level_id' => null,
                        'name' => 'Campus Facility & Technology Fee',
                    ],
                    [
                        'category' => 'facility',
                        'amount' => 2500.00,
                        'is_mandatory' => true,
                        'condition_type' => 'none',
                        'description' => 'Campus maintenance, high-speed WiFi, computer lab, and library facility upkeep.',
                    ]
                );

                // D. School Bus Transport Fee (Conditional)
                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $greenwood->id,
                        'academic_year_id' => $activeYear->id,
                        'term_id' => $fallTerm->id,
                        'name' => 'School Bus Transport Fee',
                    ],
                    [
                        'grade_level_id' => null,
                        'category' => 'transport',
                        'amount' => 4000.00,
                        'is_mandatory' => false,
                        'condition_type' => 'transport_enrollment',
                        'description' => 'Daily two-way school bus transportation service for enrolled bus route students.',
                    ]
                );

                // Bulk generate invoices for Grade 10
                if ($grade10) {
                    $billingService->bulkGenerateInvoices(
                        school: $greenwood,
                        academicYear: $activeYear,
                        term: $fallTerm,
                        gradeLevel: $grade10,
                        dueDate: Carbon::parse('2025-10-31'),
                        overrideExisting: true
                    );

                    // Record sample payments
                    $bart = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00101')->first();
                    $lisa = Student::where('school_id', $greenwood->id)->where('admission_number', 'GRE-25-00102')->first();

                    // Lisa: Paid in full online (19,000 ETB)
                    if ($lisa && $lisa->invoices()->first()) {
                        $lisaInvoice = $lisa->invoices()->first();
                        $billingService->recordPayment(
                            invoice: $lisaInvoice,
                            data: [
                                'amount' => $lisaInvoice->balance(),
                                'method' => 'online',
                                'paid_at' => Carbon::parse('2025-09-10 14:20:00'),
                                'gateway' => 'chapa',
                                'gateway_reference' => 'CHAPA-TX-LISA-' . time(),
                                'gateway_status' => 'success',
                                'notes' => 'Full payment via Chapa online gateway by Homer Simpson',
                            ]
                        );
                    }

                    // Bart: Partial payment (10,000 of 23,000 ETB) via cash
                    if ($bart && $bart->invoices()->first()) {
                        $bartInvoice = $bart->invoices()->first();
                        $billingService->recordPayment(
                            invoice: $bartInvoice,
                            data: [
                                'amount' => 10000.00,
                                'method' => 'cash',
                                'paid_at' => Carbon::parse('2025-09-12 11:00:00'),
                                'notes' => 'First installment paid in cash at bursar desk',
                            ],
                            recordedBy: $greenwoodAdmin
                        );
                    }
                }
            }
        }

        // =========================================================================
        // 2. Maplewood Elementary School (Elementary School)
        // =========================================================================
        $maplewood = School::where('subdomain', 'maplewood')->first();
        if ($maplewood) {
            $tenantManager->setTenant($maplewood);

            $mapleYear = AcademicYear::where('school_id', $maplewood->id)->where('name', '2025/2026')->first();
            $mapleTerm1 = Term::where('school_id', $maplewood->id)
                ->where('academic_year_id', $mapleYear?->id)
                ->where(function ($q) {
                    $q->where('name', 'Fall Term')->orWhere('name', 'Term 1');
                })->first() ?: Term::where('school_id', $maplewood->id)->first();
            $grade3 = GradeLevel::where('school_id', $maplewood->id)->where('code', 'G3')->first();

            if ($mapleYear && $mapleTerm1 && $grade3) {
                // A. Grade 3 Tuition
                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $maplewood->id,
                        'academic_year_id' => $mapleYear->id,
                        'term_id' => $mapleTerm1->id,
                        'grade_level_id' => $grade3->id,
                        'name' => 'Grade 3 Tuition Fee',
                    ],
                    [
                        'category' => 'tuition',
                        'amount' => 14000.00,
                        'is_mandatory' => true,
                        'condition_type' => 'none',
                        'description' => 'Primary school Grade 3 curriculum instruction.',
                    ]
                );

                // B. Learning Materials & Art Supplies Fee
                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $maplewood->id,
                        'academic_year_id' => $mapleYear->id,
                        'term_id' => $mapleTerm1->id,
                        'grade_level_id' => null,
                        'name' => 'Learning Materials & Art Supplies',
                    ],
                    [
                        'category' => 'activity',
                        'amount' => 1800.00,
                        'is_mandatory' => true,
                        'condition_type' => 'none',
                        'description' => 'Textbooks, workbooks, science discovery sets, and art stationery.',
                    ]
                );

                // C. Yellow Bus Transport Fee (Conditional)
                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $maplewood->id,
                        'academic_year_id' => $mapleYear->id,
                        'term_id' => $mapleTerm1->id,
                        'name' => 'Maplewood Yellow Bus Service',
                    ],
                    [
                        'grade_level_id' => null,
                        'category' => 'transport',
                        'amount' => 3500.00,
                        'is_mandatory' => false,
                        'condition_type' => 'transport_enrollment',
                        'description' => 'Elementary school bus transport for enrolled students.',
                    ]
                );

                // Bulk generate invoices for Grade 3
                $billingService->bulkGenerateInvoices(
                    school: $maplewood,
                    academicYear: $mapleYear,
                    term: $mapleTerm1,
                    gradeLevel: $grade3,
                    dueDate: Carbon::parse('2025-10-15'),
                    overrideExisting: true
                );

                // Tommy Vance: Fully paid online (includes transport)
                $tommy = Student::where('school_id', $maplewood->id)->where('admission_number', 'MAP-25-00101')->first();
                if ($tommy && $tommy->invoices()->first()) {
                    $tommyInvoice = $tommy->invoices()->first();
                    $billingService->recordPayment(
                        invoice: $tommyInvoice,
                        data: [
                            'amount' => $tommyInvoice->balance(),
                            'method' => 'online',
                            'paid_at' => Carbon::parse('2025-09-08 09:30:00'),
                            'gateway' => 'chapa',
                            'gateway_reference' => 'CHAPA-TX-TOMMY-' . time(),
                            'gateway_status' => 'success',
                            'notes' => 'Paid in full by Sarah Vance via Chapa',
                        ]
                    );
                }
            }
        }

        // =========================================================================
        // 3. Oakridge Academy (Preparatory)
        // =========================================================================
        $oakridge = School::where('subdomain', 'oakridge')->first();
        if ($oakridge) {
            $tenantManager->setTenant($oakridge);

            $oakYear = AcademicYear::where('school_id', $oakridge->id)->where('name', '2025/2026')->first();
            $autumnTerm = Term::where('school_id', $oakridge->id)->where('academic_year_id', $oakYear?->id)->where('name', 'Autumn Term')->first();
            $oakG11 = GradeLevel::where('school_id', $oakridge->id)->where('code', 'G11')->first();

            if ($oakYear && $autumnTerm && $oakG11) {
                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $oakridge->id,
                        'academic_year_id' => $oakYear->id,
                        'term_id' => $autumnTerm->id,
                        'grade_level_id' => $oakG11->id,
                        'name' => 'Sixth Form Tuition Fee',
                    ],
                    [
                        'category' => 'tuition',
                        'amount' => 25000.00,
                        'is_mandatory' => true,
                        'condition_type' => 'none',
                        'description' => 'Advanced collegiate level instruction.',
                    ]
                );

                FeeStructure::updateOrCreate(
                    [
                        'school_id' => $oakridge->id,
                        'academic_year_id' => $oakYear->id,
                        'term_id' => $autumnTerm->id,
                        'grade_level_id' => null,
                        'name' => 'Laboratory & STEM Facility Fee',
                    ],
                    [
                        'category' => 'facility',
                        'amount' => 4500.00,
                        'is_mandatory' => true,
                        'condition_type' => 'none',
                        'description' => 'Chemistry lab and computer server access.',
                    ]
                );

                $billingService->bulkGenerateInvoices(
                    school: $oakridge,
                    academicYear: $oakYear,
                    term: $autumnTerm,
                    gradeLevel: $oakG11,
                    dueDate: Carbon::parse('2025-10-31'),
                    overrideExisting: true
                );
            }
        }
    }
}
