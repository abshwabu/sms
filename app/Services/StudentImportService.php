<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use App\Tenancy\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StudentImportService
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Import students from a CSV file, enroll them into a section, and return generated credentials.
     *
     * @return array{imported_count: int, credentials: array<array<string, mixed>>, errors: array<string>}
     */
    public function importFromCsv(
        UploadedFile|string $file,
        int $academicYearId,
        int $sectionId,
        ?string $defaultPassword = null
    ): array {
        $school = $this->tenantManager->getTenant();
        $academicYear = AcademicYear::findOrFail($academicYearId);
        $section = Section::findOrFail($sectionId);

        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot import students into a closed academic year.');
        }

        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Failed to read CSV file.');
        }

        $header = null;
        $importedCount = 0;
        $credentials = [];
        $errors = [];
        $rowNumber = 0;

        // Ensure Spatie permissions team context is active
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $studentRole = Role::firstOrCreate([
            'name' => RoleEnum::STUDENT->value,
            'guard_name' => 'web',
            'school_id' => $school->id,
        ]);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $rowNumber++;

                // Skip completely empty lines
                if (count($row) === 1 && $row[0] === null) {
                    continue;
                }

                // First row header detection
                if (! $header) {
                    $header = array_map(fn ($col) => strtolower(trim((string) $col)), $row);
                    continue;
                }

                $data = array_combine($header, array_pad($row, count($header), null));

                $name = trim($data['name'] ?? $data['full_name'] ?? '');
                if (empty($name)) {
                    $errors[] = "Row {$rowNumber}: Name is missing.";
                    continue;
                }

                $rawDob = $data['dob'] ?? $data['date_of_birth'] ?? null;
                $dob = ! empty($rawDob) ? trim($rawDob) : null;

                $rawGender = $data['gender'] ?? null;
                $gender = ! empty($rawGender) ? strtolower(trim($rawGender)) : 'other';

                $rawAddress = $data['address'] ?? null;
                $address = ! empty($rawAddress) ? trim($rawAddress) : null;

                $rawMedical = $data['medical_notes'] ?? null;
                $medicalNotes = ! empty($rawMedical) ? trim($rawMedical) : null;

                // Unique Admission Number
                $rawAdmission = $data['admission_number'] ?? null;
                $admissionNumber = ! empty($rawAdmission) 
                    ? trim($rawAdmission) 
                    : $this->generateAdmissionNumber($school, $importedCount + 1);

                // Student Email (generated if not provided)
                $rawEmail = $data['email'] ?? null;
                $email = ! empty($rawEmail) ? trim($rawEmail) : null;
                if (empty($email)) {
                    $cleanName = Str::slug($name, '.');
                    $email = "{$cleanName}.{$admissionNumber}@{$school->subdomain}.edu";
                }

                // Generate Password
                $plainPassword = $defaultPassword ?: ('Stu!' . Str::random(8));

                // Guardian Info
                $guardianInfo = null;
                if (! empty($data['guardian_name']) || ! empty($data['guardian_phone']) || ! empty($data['guardian_email'])) {
                    $guardianInfo = [
                        'name' => $data['guardian_name'] ?? null,
                        'phone' => $data['guardian_phone'] ?? null,
                        'email' => $data['guardian_email'] ?? null,
                        'relationship' => $data['guardian_relationship'] ?? 'Guardian',
                    ];
                }

                // 1. Create or update User
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'school_id' => $school->id,
                        'name' => $name,
                        'password' => Hash::make($plainPassword),
                        'role' => RoleEnum::STUDENT->value,
                        'status' => UserStatus::ACTIVE,
                        'email_verified_at' => now(),
                    ]
                );
                $user->assignRole($studentRole);

                // 2. Create or update Student profile
                $student = Student::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'admission_number' => $admissionNumber,
                        'date_of_birth' => $dob,
                        'gender' => $gender,
                        'address' => $address,
                        'admission_date' => now()->toDateString(),
                        'current_section_id' => $section->id,
                        'status' => 'active',
                        'medical_notes' => $medicalNotes,
                        'guardian_info' => $guardianInfo,
                    ]
                );

                // 3. Create Enrollment for the academic year and section
                Enrollment::updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'school_id' => $school->id,
                        'section_id' => $section->id,
                        'enrolled_at' => now()->toDateString(),
                        'status' => 'enrolled',
                    ]
                );

                $importedCount++;
                $credentials[] = [
                    'student_id' => $student->id,
                    'admission_number' => $admissionNumber,
                    'name' => $name,
                    'email' => $email,
                    'plain_password' => $plainPassword,
                    'section' => $section->name,
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return [
            'imported_count' => $importedCount,
            'credentials' => $credentials,
            'errors' => $errors,
        ];
    }

    /**
     * Generate unique sequential admission number.
     */
    protected function generateAdmissionNumber(School $school, int $sequence = 1): string
    {
        $prefix = strtoupper(substr($school->subdomain, 0, 3));
        $year = date('y');
        $baseCount = Student::withoutGlobalScopes()->where('school_id', $school->id)->count();
        $uniqueNum = $baseCount + $sequence;

        // Ensure uniqueness even if concurrent imports happen
        $candidate = sprintf('%s-%s-%05d', $prefix, $year, $uniqueNum);
        while (Student::withoutGlobalScopes()->where('school_id', $school->id)->where('admission_number', $candidate)->exists()) {
            $uniqueNum++;
            $candidate = sprintf('%s-%s-%05d', $prefix, $year, $uniqueNum);
        }

        return $candidate;
    }
}
