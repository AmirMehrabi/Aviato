<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IncidentTimelineEventRequest;
use App\Models\Incident;
use App\Models\IncidentTimelineEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class IncidentTimelineEventController extends Controller
{
    public function store(IncidentTimelineEventRequest $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('update', $incident);
        $incident->timelineEvents()->create($request->validated());

        return back()->with('status', 'رویداد زمانی اضافه شد.');
    }

    public function update(IncidentTimelineEventRequest $request, Incident $incident, IncidentTimelineEvent $timelineEvent): RedirectResponse
    {
        $this->authorizeEvent($incident, $timelineEvent);
        $timelineEvent->update($request->validated());

        return back()->with('status', 'رویداد زمانی به‌روزرسانی شد.');
    }

    public function destroy(Incident $incident, IncidentTimelineEvent $timelineEvent): RedirectResponse
    {
        $this->authorizeEvent($incident, $timelineEvent);
        $timelineEvent->delete();

        return back()->with('status', 'رویداد زمانی حذف شد.');
    }

    private function authorizeEvent(Incident $incident, IncidentTimelineEvent $event): void
    {
        abort_unless($event->incident_id === $incident->id, 404);
        Gate::authorize('update', $incident);
    }
}
