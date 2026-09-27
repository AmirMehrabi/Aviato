<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\AdminDashboardWarningDismissal;
use App\Services\AdminDashboardActions;
use App\Support\AdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardActions $actions) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user('admin')->role !== AdminRole::Admin) {
            return redirect()->route(AdminAccess::landingRoute($request->user('admin')));
        }

        $dismissedKeys = AdminDashboardWarningDismissal::query()
            ->where('user_id', $request->user('admin')->id)
            ->pluck('warning_key');
        $category = $request->query('category');
        $category = in_array($category, ['servers', 'machines', 'tickets'], true) ? $category : null;

        return view('admin.dashboard', [
            'dashboard' => $this->actions->snapshot($dismissedKeys, max(1, $request->integer('page', 1)), $category),
            'refreshedAt' => now(),
        ]);
    }

    public function dismissWarning(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warning_key' => ['required', 'string', 'size:64'],
        ]);

        if (! $this->actions->hasActiveKey($data['warning_key'])) {
            return back()->with('status', 'این مورد دیگر فعال نیست.');
        }

        AdminDashboardWarningDismissal::query()->updateOrCreate([
            'user_id' => $request->user('admin')->id,
            'warning_key' => $data['warning_key'],
        ]);

        return back()->with('status', 'مورد برای حساب شما پنهان شد.');
    }

    public function restoreWarnings(Request $request): RedirectResponse
    {
        AdminDashboardWarningDismissal::query()
            ->where('user_id', $request->user('admin')->id)
            ->delete();

        return back()->with('status', 'موارد پنهان‌شده دوباره نمایش داده شدند.');
    }
}
