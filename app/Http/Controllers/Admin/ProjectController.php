<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Project;
use App\Services\BillingService;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'owner' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $projects = Project::query()
            ->with(['owner'])
            ->withCount(['members', 'virtualMachines'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('owner', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['owner'] ?? null, fn ($query, int $owner) => $query->where('owner_customer_id', $owner))
            ->orderByDesc('is_default')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'owners' => Customer::query()
                ->whereHas('ownedProjects')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function show(Project $project): View
    {
        $project->load([
            'owner',
            'members.customer',
            'virtualMachines' => fn ($query) => $query->notDeleted()->with(['creator', 'customer', 'proxmoxServer', 'bundle', 'disks']),
        ])->loadCount(['members', 'virtualMachines']);

        $vmPrices = $project->virtualMachines->mapWithKeys(function ($vm) {
            return [$vm->uuid => $this->billing->estimateMonthly($vm)];
        });

        $totalMonthlyCost = $vmPrices->sum();

        return view('admin.projects.show', [
            'project' => $project,
            'vmPrices' => $vmPrices,
            'totalMonthlyCost' => $totalMonthlyCost,
        ]);
    }

    public function proforma(Project $project): View
    {
        $project->load([
            'owner',
            'virtualMachines' => fn ($query) => $query->notDeleted()->with(['bundle', 'proxmoxServer', 'disks', 'creator', 'customer']),
        ])->loadCount(['members', 'virtualMachines']);

        [$periodStart, $periodEnd] = Jalali::currentJalaliMonthRange();

        $jalaliNow = Jalali::now();
        $jYear = $jalaliNow->getYear();
        $jMonth = $jalaliNow->getMonth();

        $vmPrices = $project->virtualMachines->mapWithKeys(function ($vm) {
            return [$vm->uuid => $this->billing->estimateMonthly($vm)];
        });

        $totalMonthlyCost = $vmPrices->sum();

        return view('admin.projects.proforma', [
            'project' => $project,
            'vmPrices' => $vmPrices,
            'totalMonthlyCost' => $totalMonthlyCost,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'jalaliYear' => $jYear,
            'jalaliMonth' => $jMonth,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $project->loadMissing('owner');
        $project->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($project->owner, $data['name'], $project),
        ]);

        return back()->with('status', 'نام فضای کاری تغییر کرد.');
    }

    private function uniqueSlug(Customer $customer, string $name, Project $ignore): string
    {
        $slug = Str::slug($name) ?: 'project';
        $candidate = $slug;
        $suffix = 2;

        while ($customer->ownedProjects()
            ->where('slug', $candidate)
            ->whereKeyNot($ignore->getKey())
            ->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }
}
