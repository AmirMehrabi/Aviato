<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PromotionUserController extends Controller
{
    public function index(): View
    {
        return view('admin.promotions.users', ['users' => User::query()->orderBy('name')->paginate(30)]);
    }

    public function update(Request $request, User $user, PromotionService $promotions): RedirectResponse
    {
        $data = $request->validate(['can_manage_promotions' => ['required', 'boolean']]);
        $user->forceFill(['can_manage_promotions' => (bool) $data['can_manage_promotions']])->save();
        $promotions->event('permission_updated', user: $request->user('admin'), request: $request, metadata: ['target_user_id' => $user->id, 'enabled' => (bool) $data['can_manage_promotions']]);

        return back()->with('status', 'دسترسی مدیریت پروموشن به‌روزرسانی شد.');
    }
}
