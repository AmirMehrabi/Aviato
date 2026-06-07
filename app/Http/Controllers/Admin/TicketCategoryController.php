<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTeam;
use App\Models\TicketCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TicketCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.support.categories.index', [
            'categories' => TicketCategory::query()->with('supportTeam')->orderBy('sort_order')->orderBy('name')->get(),
            'teams' => SupportTeam::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->prepend('بدون تیم', ''),
            'strategies' => TicketCategory::strategies(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        TicketCategory::query()->create($this->payload($data));

        return back()->with('status', 'دسته‌بندی تیکت ساخته شد.');
    }

    public function update(Request $request, TicketCategory $category): RedirectResponse
    {
        $category->update($this->payload($this->validated($request)));

        return back()->with('status', 'دسته‌بندی تیکت به‌روزرسانی شد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'support_team_id' => ['nullable', 'integer', 'exists:support_teams,id'],
            'assignment_strategy' => ['required', Rule::in(array_keys(TicketCategory::strategies()))],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
    }

    private function payload(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null,
            'support_team_id' => $data['support_team_id'] ?: null,
            'assignment_strategy' => $data['assignment_strategy'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) $data['sort_order'],
        ];
    }
}
