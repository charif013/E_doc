<?php

namespace App\Http\Controllers;

use App\Models\DocumentRoutePreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRoutePresetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'route_user_ids' => ['required', 'array', 'min:1', 'max:20'],
            'route_user_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:users,id',
                'not_in:'.$request->user()->id,
            ],
        ], [
            'name.required' => 'กรุณาตั้งชื่อเส้นทางประจำ',
            'route_user_ids.required' => 'กรุณาเลือกผู้พิจารณาอย่างน้อย 1 คน',
            'route_user_ids.min' => 'กรุณาเลือกผู้พิจารณาอย่างน้อย 1 คน',
            'route_user_ids.*.distinct' => 'เส้นทางประจำต้องไม่มีผู้พิจารณาซ้ำกัน',
        ]);

        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json([
                'message' => 'กรุณาตั้งชื่อเส้นทางประจำ',
                'errors' => ['name' => ['กรุณาตั้งชื่อเส้นทางประจำ']],
            ], 422);
        }

        $preset = DocumentRoutePreset::updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $name],
            ['route_user_ids' => array_map('intval', $validated['route_user_ids'])]
        );

        return response()->json([
            'message' => $preset->wasRecentlyCreated
                ? 'บันทึกเส้นทางประจำแล้ว'
                : 'อัปเดตเส้นทางประจำแล้ว',
            'preset' => $preset->only(['id', 'name', 'route_user_ids']),
        ], $preset->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $preset): JsonResponse
    {
        $routePreset = DocumentRoutePreset::query()
            ->whereKey($preset)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $routePreset->delete();

        return response()->json(['message' => 'ลบเส้นทางประจำแล้ว']);
    }
}
