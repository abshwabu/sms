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

        $validated = $request->validated();
        if (! empty($validated['is_active'])) {
            $academicYear->terms()->update(['is_active' => false]);
        }

        $term = $academicYear->terms()->create($validated);

        return $this->respondWithSuccess($term->load('academicYear'), 'Term created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Direct term creation endpoint with academic_year_id in payload.
     */
    public function storeDirect(StoreTermRequest $request): JsonResponse
    {
        $academicYear = AcademicYear::findOrFail($request->input('academic_year_id'));

        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot add terms to a closed academic year.');
        }

        $validated = $request->validated();
        if (! empty($validated['is_active'])) {
            $academicYear->terms()->update(['is_active' => false]);
        }

        $term = $academicYear->terms()->create($validated);

        return $this->respondWithSuccess($term->load('academicYear'), 'Term created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Update the specified term.
     */
    public function update(StoreTermRequest $request, Term $term): JsonResponse
    {
        if ($term->academicYear && $term->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot update terms in a closed academic year.');
        }

        $validated = $request->validated();
        if (! empty($validated['is_active'])) {
            Term::where('academic_year_id', $term->academic_year_id)->update(['is_active' => false]);
        }

        $term->update($validated);

        return $this->respondWithSuccess($term->load('academicYear'), 'Term updated successfully.');
    }

    /**
     * Activate a term (sets is_active to true and deactivates peer terms).
     */
    public function activate(Term $term): JsonResponse
    {
        if ($term->academicYear && $term->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot activate terms in a closed academic year.');
        }

        Term::where('academic_year_id', $term->academic_year_id)->update(['is_active' => false]);
        $term->update(['is_active' => true]);

        return $this->respondWithSuccess($term->load('academicYear'), 'Term activated successfully.');
    }

    /**
     * Remove the specified term.
     */
    public function destroy(Term $term): JsonResponse
    {
        if ($term->academicYear && $term->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot delete terms from a closed academic year.');
        }

        $term->delete();

        return $this->respondWithSuccess(null, 'Term deleted successfully.');
    }
}
