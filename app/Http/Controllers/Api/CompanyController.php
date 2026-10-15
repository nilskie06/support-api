<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Company::query();

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $companies = $query->orderBy('id', 'asc')->get();

        $stats = [
            'total' => Company::count(),
            'active' => Company::where('status', 'active')->count(),
            'inactive' => Company::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'data' => $companies,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:companies,code'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $company = Company::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'address' => isset($validated['address']) ? trim($validated['address']) : null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Company created successfully',
            'data' => $company,
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show(int $id): JsonResponse
    {
        $company = Company::find($id);

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        return response()->json([
            'data' => $company,
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $company = Company::find($id);

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('companies', 'code')->ignore($company->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $company->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'address' => isset($validated['address']) ? trim($validated['address']) : null,
            'status' => $validated['status'] ?? $company->status ?? 'active',
        ]);

        return response()->json([
            'message' => 'Company updated successfully',
            'data' => $company->fresh(),
        ]);
    }

    /**
     * Remove the specified company.
     */
    public function destroy(int $id): JsonResponse
    {
        $company = Company::find($id);

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        if ($company->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete company because it is associated with existing tickets.',
            ], 422);
        }

        $company->delete();

        return response()->json([
            'message' => 'Company deleted successfully',
        ]);
    }
}
