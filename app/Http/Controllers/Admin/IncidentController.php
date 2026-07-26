<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IncidentRequest;
use App\Models\Incident;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class IncidentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Incident::class);

        return view('admin.incidents.index', [
            'incidents' => Incident::query()->latest('started_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Incident::class);

        return view('admin.incidents.create', ['incident' => new Incident([
            'status' => Incident::STATUS_INVESTIGATING,
            'started_at' => now()->format('Y-m-d\\TH:i'),
        ])]);
    }

    public function store(IncidentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Incident::class);
        $incident = Incident::create($this->payload($request));

        return redirect()->route('admin.incidents.edit', $incident)->with('status', 'رخداد ایجاد شد. رویدادهای زمانی را در بخش زیر اضافه کنید.');
    }

    public function edit(Incident $incident): View
    {
        Gate::authorize('update', $incident);
        $incident->load('timelineEvents');

        return view('admin.incidents.edit', compact('incident'));
    }

    public function update(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('update', $incident);
        $incident->update($this->payload($request, $incident));

        return redirect()->route('admin.incidents.edit', $incident)->with('status', 'رخداد به‌روزرسانی شد.');
    }

    public function destroy(Incident $incident): RedirectResponse
    {
        Gate::authorize('delete', $incident);
        $incident->delete();

        return redirect()->route('admin.incidents.index')->with('status', 'رخداد حذف شد.');
    }

    private function payload(IncidentRequest $request, ?Incident $incident = null): array
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published']
            ? ($incident?->published_at ?: now())
            : null;

        return $data;
    }
}
