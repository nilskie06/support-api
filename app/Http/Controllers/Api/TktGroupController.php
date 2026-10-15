<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\TktGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TktGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $query = TktGroup::with([
            'department:id,code,name',
            'approvers:id,name,fullname,email',
            'companies:id,code,name',
            'members:id,name,fullname,email',
        ]);

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
            'stats' => [
                'total' => TktGroup::count(),
                'active_members' => DB::table('tkt_group_user')->distinct('user_id')->count('user_id'),
            ],
        ]);
    }

    public function lookups(): JsonResponse
    {
        return response()->json([
            'departments' => Department::where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'companies' => Company::where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'users' => User::with('assignedDepartments:id,user_id,reference,reference_code')
                ->where('status', 'active')
                ->orderBy('fullname')
                ->orderBy('name')
                ->get(['id', 'name', 'fullname', 'email']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateGroup($request);
        $group = DB::transaction(fn () => $this->persistGroup(new TktGroup, $validated));

        return response()->json(['message' => 'Team created successfully', 'data' => $group], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->loadGroup(TktGroup::findOrFail($id))]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $group = TktGroup::findOrFail($id);
        $validated = $this->validateGroup($request, $group->id);
        $group = DB::transaction(fn () => $this->persistGroup($group, $validated));

        return response()->json(['message' => 'Team updated successfully', 'data' => $group]);
    }

    public function destroy(int $id): JsonResponse
    {
        TktGroup::findOrFail($id)->delete();

        return response()->json(['message' => 'Team deleted successfully']);
    }

    private function validateGroup(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tkt_groups', 'name')->ignore($ignoreId)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'approver_ids' => ['nullable', 'array'],
            'approver_ids.*' => ['integer', 'exists:users,id'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);
    }

    private function persistGroup(TktGroup $group, array $validated): TktGroup
    {
        $approverIds = collect($validated['approver_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $approverId = $approverIds->first();
        $memberIds = collect($validated['member_ids'] ?? [])->map(fn ($id) => (int) $id);
        $memberIds = $memberIds->merge($approverIds);

        $group->fill([
            'name' => trim($validated['name']),
            'department_id' => $validated['department_id'] ?? null,
            'approver_id' => $approverId,
            'updated_by' => auth()->id() ?? 1,
        ]);
        if (! $group->exists) {
            $group->created_by = auth()->id() ?? 1;
        }
        $group->save();

        $group->approvers()->sync($approverIds->all());
        $group->companies()->sync($validated['company_ids'] ?? []);
        $group->members()->sync($memberIds->unique()->values()->all());

        return $this->loadGroup($group);
    }

    private function loadGroup(TktGroup $group): TktGroup
    {
        return $group->load([
            'department:id,code,name',
            'approvers:id,name,fullname,email',
            'companies:id,code,name',
            'members:id,name,fullname,email',
        ]);
    }
}
