<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Contracts\View\View;

class IncidentController extends Controller
{
    public function index(): View
    {
        return view('incidents.index', [
            'incidents' => Incident::query()
                ->where('is_published', true)
                ->latest('started_at')
                ->paginate(12),
            'activePage' => 'incidents',
        ]);
    }

    public function show(string $slug): View
    {
        $incident = Incident::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('timelineEvents')
            ->firstOrFail();

        return view('incidents.show', [
            'incident' => $incident,
            'activePage' => 'incidents',
        ]);
    }
}
