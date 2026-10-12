<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    /**
     * Display a listing of menus (both tree hierarchy and flat list).
     */
    public function index(Request $request): JsonResponse
    {
        $tree = Menu::root()->with('allChildren')->get();
        $flat = Menu::orderBy('order')->get();

        return response()->json([
            'data' => $tree,
            'flat' => $flat,
        ]);
    }

    /**
     * Store a newly created menu item.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', 'unique:tkt_menus,code'],
            'parent_id' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer'],
            'status' => ['nullable', 'boolean'],
            'is_side_menu' => ['nullable', 'boolean'],
        ]);

        $parentId = (int) ($validated['parent_id'] ?? 0);

        $code = $validated['code'] ?? null;
        if (empty($code)) {
            $baseCode = Str::slug($validated['name'], '_');
            $code = $baseCode;
            $counter = 1;
            while (Menu::where('code', $code)->exists()) {
                $code = "{$baseCode}_{$counter}";
                $counter++;
            }
        }

        $order = isset($validated['order']) && $validated['order'] !== ''
            ? (int) $validated['order']
            : (Menu::where('parent_id', $parentId)->max('order') ?? 0) + 1;

        $menu = Menu::create([
            'name' => $validated['name'],
            'code' => $code,
            'parent_id' => $parentId,
            'icon' => $validated['icon'] ?? null,
            'url' => $validated['url'] ?? null,
            'order' => $order,
            'status' => $validated['status'] ?? true,
            'is_side_menu' => $validated['is_side_menu'] ?? true,
        ]);

        return response()->json([
            'message' => 'Menu created successfully',
            'data' => $menu->load('allChildren'),
        ], 201);
    }

    /**
     * Display the specified menu item.
     */
    public function show(int $id): JsonResponse
    {
        $menu = Menu::with('allChildren', 'parent')->findOrFail($id);

        return response()->json([
            'data' => $menu,
        ]);
    }

    /**
     * Update the specified menu item.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('tkt_menus', 'code')->ignore($menu->id)],
            'parent_id' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer'],
            'status' => ['nullable', 'boolean'],
            'is_side_menu' => ['nullable', 'boolean'],
        ]);

        $newParentId = (int) ($validated['parent_id'] ?? 0);

        // Prevent setting a menu item as its own parent or child of itself
        if ($newParentId === $menu->id) {
            return response()->json([
                'message' => 'A menu cannot be its own parent.',
            ], 422);
        }

        $menu->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'parent_id' => $newParentId,
            'icon' => $validated['icon'] ?? null,
            'url' => $validated['url'] ?? null,
            'order' => isset($validated['order']) ? (int) $validated['order'] : $menu->order,
            'status' => $validated['status'] ?? $menu->status,
            'is_side_menu' => $validated['is_side_menu'] ?? $menu->is_side_menu,
        ]);

        return response()->json([
            'message' => 'Menu updated successfully',
            'data' => $menu->fresh(['allChildren', 'parent']),
        ]);
    }

    /**
     * Remove the specified menu item and its children.
     */
    public function destroy(int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        DB::transaction(function () use ($menu) {
            $this->deleteRecursively($menu);
        });

        return response()->json([
            'message' => 'Menu and associated submenus deleted successfully',
        ]);
    }

    /**
     * Bulk reorder and re-parent menus from draggable tree.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->saveTreeRecursively($validated['items'], 0);
        });

        $tree = Menu::root()->with('allChildren')->get();
        $flat = Menu::orderBy('order')->get();

        return response()->json([
            'message' => 'Menu hierarchy updated successfully',
            'data' => $tree,
            'flat' => $flat,
        ]);
    }

    /**
     * Recursively update order and parent_id for draggable items.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveTreeRecursively(array $items, int $parentId): void
    {
        foreach ($items as $index => $item) {
            $id = $item['id'] ?? null;
            if (! $id) {
                continue;
            }

            Menu::where('id', $id)->update([
                'parent_id' => $parentId,
                'order' => $index,
            ]);

            if (! empty($item['all_children']) && is_array($item['all_children'])) {
                $this->saveTreeRecursively($item['all_children'], (int) $id);
            } elseif (! empty($item['children']) && is_array($item['children'])) {
                $this->saveTreeRecursively($item['children'], (int) $id);
            }
        }
    }

    /**
     * Recursively delete menu item and submenus.
     */
    private function deleteRecursively(Menu $menu): void
    {
        foreach ($menu->children as $child) {
            $this->deleteRecursively($child);
        }

        $menu->delete();
    }
}
