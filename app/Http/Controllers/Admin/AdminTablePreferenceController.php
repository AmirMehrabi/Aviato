<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTablePreference;
use App\Support\AdminTableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminTablePreferenceController extends Controller
{
    public function update(Request $request, string $tableKey): JsonResponse
    {
        abort_unless(AdminTableSort::exists($tableKey), 404);

        $validator = Validator::make($request->all(), [
            'column' => ['required', 'string', Rule::in(AdminTableSort::columns($tableKey))],
            'direction' => ['required', 'in:asc,desc'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'اطلاعات مرتب‌سازی معتبر نیست.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $preference = AdminTablePreference::query()->updateOrCreate(
            ['user_id' => $request->user('admin')->id, 'table_key' => $tableKey],
            ['sort_column' => $data['column'], 'sort_direction' => $data['direction']],
        );

        return response()->json([
            'data' => [
                'table' => $preference->table_key,
                'column' => $preference->sort_column,
                'direction' => $preference->sort_direction,
            ],
        ]);
    }

    public function destroy(Request $request, string $tableKey): JsonResponse
    {
        abort_unless(AdminTableSort::exists($tableKey), 404);

        AdminTablePreference::query()
            ->where('user_id', $request->user('admin')->id)
            ->where('table_key', $tableKey)
            ->delete();

        return response()->json(status: 204);
    }
}
