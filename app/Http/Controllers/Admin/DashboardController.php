<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDashboardWarningDismissal;
use App\Models\AdminDashboardWarningPreference;
use App\Services\AdminDashboardActions;
use App\Services\AdminDashboardActivity;
use App\Services\AdminDashboardFinance;
use App\Support\AdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardActions $actions, private readonly AdminDashboardFinance $finance, private readonly AdminDashboardActivity $activity) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! $request->user('admin')->allows('dashboard.view')) {
            return redirect()->route(AdminAccess::landingRoute($request->user('admin')));
        }

        $dismissedKeys = AdminDashboardWarningDismissal::query()
            ->where('user_id', $request->user('admin')->id)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('warning_key');
        $category = $request->query('category');
        $category = in_array($category, ['servers', 'machines', 'tickets', 'payments'], true) ? $category : null;
        $days = in_array($request->integer('period', 1), [1, 7, 30], true) ? $request->integer('period', 1) : 1;

        $warningType = $request->query('warning_type');
        $warningType = is_string($warningType) && array_key_exists($warningType, $this->actions->availableTypes($request->user('admin'))) ? $warningType : null;

        return view('admin.dashboard', [
            'dashboard' => $this->actions->snapshot($dismissedKeys, max(1, $request->integer('page', 1)), $category, $request->user('admin'), $request->boolean('hidden'), $warningType),
            'finance' => $request->user('admin')->allows('billing.read') ? $this->finance->snapshot($days) : null,
            'activity' => $this->activity->snapshot($days, $request->user('admin')),
            'expandedQueue' => $request->boolean('queue') || $category !== null || $warningType !== null || $request->boolean('hidden') || $request->integer('page', 1) > 1,
            'warningTypes' => $this->actions->availableTypes($request->user('admin')),
            'mutedTypes' => AdminDashboardWarningPreference::query()->where('user_id', $request->user('admin')->id)->pluck('warning_type')->all(),
            'period' => $days,
            'refreshedAt' => now(),
        ]);
    }

    public function dismissWarning(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warning_key' => ['required', 'string', 'size:64'],
            'mode' => ['nullable', Rule::in(['dismiss', 'snooze'])],
        ]);

        if (! $this->actions->hasActiveKey($data['warning_key'], $request->user('admin'))) {
            return back()->with('status', 'این مورد دیگر فعال نیست.');
        }

        AdminDashboardWarningDismissal::query()->updateOrCreate([
            'user_id' => $request->user('admin')->id,
            'warning_key' => $data['warning_key'],
        ], ['expires_at' => ($data['mode'] ?? 'dismiss') === 'snooze' ? now()->addDay()->startOfDay() : null]);

        return back()->with('status', ($data['mode'] ?? 'dismiss') === 'snooze' ? 'مورد تا فردا به تعویق افتاد.' : 'این رخداد برای حساب شما کنار گذاشته شد.')->with('dismissed_warning_key', $data['warning_key']);
    }

    public function restoreWarnings(Request $request): RedirectResponse
    {
        $data = $request->validate(['warning_key' => ['nullable', 'string', 'size:64']]);
        AdminDashboardWarningDismissal::query()
            ->where('user_id', $request->user('admin')->id)
            ->when($data['warning_key'] ?? null, fn ($query, $key) => $query->where('warning_key', $key))
            ->delete();

        return back()->with('status', 'موارد پنهان‌شده دوباره نمایش داده شدند.');
    }

    public function updateWarningPreference(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warning_type' => ['required', Rule::in(array_keys($this->actions->availableTypes($request->user('admin'))))],
            'muted' => ['required', 'boolean'],
        ]);
        $attributes = ['user_id' => $request->user('admin')->id, 'warning_type' => $data['warning_type']];
        if ($data['muted']) {
            AdminDashboardWarningPreference::query()->firstOrCreate($attributes);
        } else {
            AdminDashboardWarningPreference::query()->where($attributes)->delete();
        }

        return back()->with('status', $data['muted'] ? 'این نوع هشدار فقط برای داشبورد شما غیرفعال شد.' : 'این نوع هشدار دوباره فعال شد.');
    }
}
