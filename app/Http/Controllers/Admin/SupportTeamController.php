<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTeam;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportTeamController extends Controller
{
    public function index(): View
    {
        return view('admin.support.teams.index', [
            'teams' => SupportTeam::query()->with('users')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'users' => ['nullable', 'array'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        $team = SupportTeam::query()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        $team->users()->syncWithPivotValues($data['users'] ?? [], ['is_active' => true]);

        return back()->with('status', 'تیم پشتیبانی ساخته شد.');
    }

    public function update(Request $request, SupportTeam $supportTeam): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'users' => ['nullable', 'array'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        $supportTeam->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        $supportTeam->users()->syncWithPivotValues($data['users'] ?? [], ['is_active' => true]);

        return back()->with('status', 'تیم پشتیبانی به‌روزرسانی شد.');
    }
}
