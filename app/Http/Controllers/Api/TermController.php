<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTermRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TermController extends Controller
{
    use HasApiResponse;

    /**
     * Display all terms for active school.
     */
    public function indexAll(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = Term::with('academicYear');

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        $terms = $query->orderByDesc('is_active')->orderBy('start_date')->get();

        return $this->respondWithSuccess($terms, 'Terms retrieved successfully.');
    }

    /**
     * Display terms for the specified academic year.
     */
    public function index(AcademicYear $academicYear): JsonResponse
    {
        $terms = $academicYear->terms;

        return $this->respondWithSuccess($terms, 'Terms retrieved successfully.');
    }

    /**
     * Add a term under the specified academic year.
     */
    public function store(StoreTermRequest $request, AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot add terms to a closed academic year.');
        }

        $term = $academicYear->terms()->create($request->validated());

        return $this->respondWithSuccess($term, 'Term created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Update the specified term.
     */
    public function update(StoreTermRequest $request, Term $term): JsonResponse
    {
        if ($term->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot update terms in a closed academic year.');
        }

        $term->update($request->validated());

        return $this->respondWithSuccess($term, 'Term updated successfully.');
    }

    /**
     * Remove the specified term.
     */
    public function destroy(Term $term): JsonResponse
    {
        if ($term->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot delete terms from a closed academic year.');
        }

        $term->delete();

        return $this->respondWithSuccess(null, 'Term deleted successfully.');
    }
}
