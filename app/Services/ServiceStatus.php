<?php

namespace App\Services;

use App\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

class ServiceStatus
{
    public function build(): array
    {
        $now = CarbonImmutable::now('Asia/Tehran');
        $today = $now->startOfDay();
        $start = $today->subDays(89);

        $incidents = Incident::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', $now->setTimezone(config('app.timezone'))))
            ->where('started_at', '<=', $now->setTimezone(config('app.timezone')))
            ->where(fn ($query) => $query->where('ended_at', '>', $start->setTimezone(config('app.timezone')))->orWhereNull('ended_at'))
            ->latest('started_at')
            ->get()
            ->map(function (Incident $incident) use ($now): array {
                $began = CarbonImmutable::instance($incident->started_at)->setTimezone('Asia/Tehran');
                // Older resolved reports may not have an explicit end time.
                $ended = $incident->ended_at ?? ($incident->status === Incident::STATUS_RESOLVED ? $incident->updated_at : null);
                $end = $ended ? CarbonImmutable::instance($ended)->setTimezone('Asia/Tehran')->max($began)->min($now) : $now;

                return [
                    'id' => $incident->id,
                    'service' => $incident->category,
                    'severity' => $incident->severity,
                    'title' => $incident->title,
                    'description' => $incident->impact_summary,
                    'date' => $began,
                    'start' => $began,
                    'end' => $end,
                    'active' => $incident->status !== Incident::STATUS_RESOLVED && (! $ended || $incident->ended_at->isAfter($now)),
                    'status' => $incident->statusLabel(),
                    'minutes' => (int) ceil($began->diffInMinutes($end)),
                    'url' => route('incidents.show', $incident->slug),
                ];
            })
            ->filter(fn (array $incident) => $incident['end']->gt($start) || $incident['active'])
            ->values();

        $services = [];
        $descriptions = [
            'compute' => 'ایجاد، مدیریت و دسترسی به سرورها',
            'network' => 'اتصال اینترنت و شبکه داخلی',
            'panel' => 'مدیریت سرویس‌ها و رابط برنامه‌نویسی',
        ];
        foreach (Incident::CATEGORIES as $id => $name) {
            $events = $incidents->where('service', $id);
            $active = $events->where('active', true);
            $services[] = [
                'id' => $id,
                'name' => $name,
                'description' => $descriptions[$id],
                'severity' => $this->severity($active),
                'uptime' => number_format(100 * (1 - $this->affectedSeconds($events, $start, $now) / max(1, $start->diffInSeconds($now))), 2),
            ];
        }

        $months = [];
        for ($date = $start; $date->lte($today); $date = $date->addDay()) {
            $jalali = Jalalian::fromCarbon($date->toMutable());
            $key = $jalali->format('Y-m');
            $months[$key]['label'] = $jalali->format('F Y');
            $months[$key]['days'][] = [
                'date' => $date->toDateString(),
                'label' => $jalali->format('l، j F Y'),
                'incidents' => $incidents->filter(fn ($incident) => $incident['start']->lt($date->addDay()) && ($incident['end']->gt($date) || ($incident['active'] && $date->isSameDay($now->setTimezone(config('app.timezone')))))),
            ];
        }

        $overall = $this->severity($incidents->where('active', true));

        return compact('now', 'today', 'start', 'services', 'incidents', 'months', 'overall');
    }

    private function severity(Collection $incidents): string
    {
        return $incidents->contains('severity', 'danger') ? 'danger' : ($incidents->isNotEmpty() ? 'warning' : 'healthy');
    }

    /** Merge intervals so overlapping reports count as one period of disruption. */
    private function affectedSeconds(Collection $incidents, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $total = 0;
        $until = $start;
        foreach ($incidents->sortBy(fn ($incident) => $incident['start']->getTimestamp()) as $incident) {
            $from = $incident['start']->max($start)->max($until);
            $to = $incident['end']->min($end);
            if ($to->gt($from)) {
                $total += $from->diffInSeconds($to);
                $until = $to;
            }
        }

        return $total;
    }
}
