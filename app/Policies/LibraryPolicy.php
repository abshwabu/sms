<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Student;
use App\Models\User;

class LibraryPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view the book catalog.
     */
    public function viewCatalog(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isLibrarian()
            || $user->isTeacher()
            || $user->isStudent()
            || $user->isParent();
    }

    /**
     * Determine whether the user can add/edit/delete books in the catalog.
     */
    public function manageCatalog(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isLibrarian();
    }

    /**
     * Determine whether the user can checkout or checkin books.
     */
    public function circulate(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isLibrarian();
    }

    /**
     * Determine whether the user can view loan records.
     */
    public function viewLoan(User $user, BookLoan $loan): bool
    {
        if (! $this->isSameSchool($user, $loan)) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isSchoolAdmin() || $user->isLibrarian()) {
            return true;
        }

        // Student can view own loans
        if ($user->isStudent() && $user->student && (int) $loan->student_id === (int) $user->student->id) {
            return true;
        }

        // Staff can view own loans
        if ($user->isTeacher() && $user->staff && (int) $loan->staff_id === (int) $user->staff->id) {
            return true;
        }

        // Parent can view linked children loans
        if ($user->isParent() && $user->parentProfile && $loan->student_id) {
            return $user->parentProfile->students()->where('students.id', $loan->student_id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can view a specific student's loans.
     */
    public function viewStudentLoans(User $user, Student $student): bool
    {
        if (! $this->isSameSchool($user, $student)) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isSchoolAdmin() || $user->isLibrarian()) {
            return true;
        }

        if ($user->isStudent() && $user->student && (int) $user->student->id === (int) $student->id) {
            return true;
        }

        if ($user->isParent() && $user->parentProfile) {
            return $user->parentProfile->students()->where('students.id', $student->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can manage/collect library fines.
     */
    public function manageFines(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isSchoolAdmin()
            || $user->isLibrarian();
    }
}
