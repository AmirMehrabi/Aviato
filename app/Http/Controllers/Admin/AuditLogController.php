<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AdminAuditLog::query()->with('actor');
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
        return view('admin.audit.show', compact('auditLog'));
    }
}
