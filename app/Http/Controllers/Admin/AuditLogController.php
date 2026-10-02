<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AdminAuditLog::query()->with('actor');
        if ($request->user('admin')->role !== AdminRole::Admin) {
            $query->whereIn('route_name', $this->visibleRoutes($request->user('admin')));
        }
        $query->when($request->filled('user_id'), fn ($q) => $q->where('actor_user_id', $request->integer('user_id')))
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->string('result')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', 'like', '%'.$request->string('event').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));

        return view('admin.audit.index', [
            'logs' => $query->latest('created_at')->paginate(30)->withQueryString(),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(AdminAuditLog $auditLog): View
    {
        $user = auth('admin')->user();
        abort_unless($user->role === AdminRole::Admin || in_array($auditLog->route_name, $this->visibleRoutes($user), true), 403);

        if (! $user->allows('billing.read')) {
            $auditLog->metadata = AdminAccess::redactFinancialData($auditLog->metadata ?? []);
            $auditLog->changes = AdminAccess::redactFinancialData($auditLog->changes ?? []);
        }

        return view('admin.audit.show', compact('auditLog'));
    }

    /** @return list<string> */
    private function visibleRoutes(User $user): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('admin.route-access', $route->gatherMiddleware(), true)
                && AdminAccess::canVisit($user, $route->getName()))
            ->map(fn ($route): string => $route->getName())->values()->all();
    }
}
