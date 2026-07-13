<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ApiActivityController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ApiRequestLog::query()
            ->with('customer:id,name,email')
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status_code'), fn ($query) => $query->where('status_code', $request->integer('status_code')))
            ->when($request->filled('route'), fn ($query) => $query->where('route', 'like', '%'.$request->string('route')->toString().'%'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.api-activity.index', ['logs' => $logs]);
    }
}
