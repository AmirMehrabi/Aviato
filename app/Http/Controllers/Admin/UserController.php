<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->withCount(['supportTeams', 'assignedTickets']);
        $query->when($request->filled('q'), function ($query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
        })->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($query) => $query->where('is_active', false));

        return view('admin.users.index', [
            'users' => $query->latest()->paginate(25)->withQueryString(),
            'roles' => AdminRole::options(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User(['role' => AdminRole::Support, 'is_active' => true]), 'roles' => AdminRole::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $user = User::create($data);

        return redirect()->route('admin.users.show', $user)->with('status', 'کاربر پنل ساخته شد.');
    }

    public function show(User $user): View
    {
        $user->loadCount(['supportTeams', 'assignedTickets'])->load(['supportTeams']);

        return view('admin.users.show', [
            'managedUser' => $user,
            'recentAuditLogs' => $user->adminAuditLogs()->latest('created_at')->limit(15)->get(),
            'activeSessions' => $this->sessionCount($user),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['managedUser' => $user, 'roles' => AdminRole::options()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, false, $user);
        $roleChanged = $user->role->value !== $data['role'];
        $statusChanged = (bool) $user->is_active !== (bool) $data['is_active'];

        DB::transaction(function () use ($request, $user, $data): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->guardCriticalChange($request, $locked, $data['role'], (bool) $data['is_active']);
            $locked->update($data);
        });

        if ($roleChanged || $statusChanged) {
            $this->revokeUserSessions($user);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'حساب کاربری به‌روز شد.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        $user->update(['password' => $data['password']]);
        $this->revokeUserSessions($user);

        return back()->with('status', 'رمز عبور موقت ثبت و نشست‌های قبلی بسته شد.');
    }

    public function revokeSessions(Request $request, User $user): RedirectResponse
    {
        $this->revokeUserSessions($user);

        return back()->with('status', 'نشست‌های کاربر بسته شد.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user('admin')->is($user), 422, 'نمی‌توانید حساب خود را حذف کنید.');
        $this->guardLastAdmin($user, deleting: true);
        $this->revokeUserSessions($user);

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->withErrors(['delete' => 'این کاربر سابقه عملیاتی مرتبط دارد و قابل حذف نیست؛ حساب را غیرفعال کنید.']);
        }

        return redirect()->route('admin.users.index')->with('status', 'حساب کاربری حذف شد.');
    }

    private function validated(Request $request, bool $creating, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(AdminRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
        if ($creating) {
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        }

        return $request->validate($rules);
    }

    private function guardCriticalChange(Request $request, User $user, string $newRole, bool $active): void
    {
        if ($request->user('admin')->is($user) && (! $active || $newRole !== AdminRole::Admin->value)) {
            abort(422, 'نمی‌توانید نقش یا وضعیت حساب خود را تغییر دهید.');
        }
        if ($user->role === AdminRole::Admin && ($newRole !== AdminRole::Admin->value || ! $active)) {
            $this->guardLastAdmin($user);
        }
    }

    private function guardLastAdmin(User $user, bool $deleting = false): void
    {
        if ($user->role === AdminRole::Admin && $user->is_active && User::query()->where('role', AdminRole::Admin)->where('is_active', true)->count() <= 1) {
            abort(422, 'حداقل یک مدیر فعال باید باقی بماند.');
        }
    }

    private function revokeUserSessions(User $user): void
    {
        if (Schema::hasTable('admin_session_users')) {
            $sessionIds = DB::table('admin_session_users')->where('user_id', $user->id)->pluck('session_id');
            if ($sessionIds->isNotEmpty() && Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->whereIn('id', $sessionIds)->delete();
            }
            DB::table('admin_session_users')->where('user_id', $user->id)->delete();
        }
    }

    private function sessionCount(User $user): int
    {
        return Schema::hasTable('admin_session_users')
            ? DB::table('admin_session_users')->where('user_id', $user->id)->count()
            : 0;
    }
}
