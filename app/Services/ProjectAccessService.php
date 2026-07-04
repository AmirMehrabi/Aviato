<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\VirtualMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class ProjectAccessService
{
    public const SESSION_KEY = 'customer.active_project_id';

    /** @return Collection<int, Project> */
    public function projectsFor(Customer $customer): Collection
    {
        return Project::query()
            ->visibleTo($customer)
            ->with(['owner', 'members.customer'])
            ->withCount(['members', 'virtualMachines'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function activeProject(Request $request, Customer $customer): Project
    {
        $projects = $this->projectsFor($customer);
        $sessionProjectId = (int) $request->session()->get(self::SESSION_KEY);
        $project = $projects->firstWhere('id', $sessionProjectId)
            ?? $projects->firstWhere('is_default', true)
            ?? $projects->first();

        if (! $project) {
            $project = $customer->ensureDefaultProject()
                ->load(['owner', 'members.customer'])
                ->loadCount(['members', 'virtualMachines']);
        }

        $request->session()->put(self::SESSION_KEY, $project->id);

        return $project;
    }

    public function switch(Request $request, Customer $customer, Project $project): void
    {
        abort_unless($this->membership($project, $customer), 404);

        $request->session()->put(self::SESSION_KEY, $project->id);
    }

    public function membership(Project $project, Customer $customer): ?ProjectMember
    {
        return $project->members
            ->firstWhere('customer_id', $customer->id)
            ?? $project->members()->where('customer_id', $customer->id)->first();
    }

    public function role(Project $project, Customer $customer): ?string
    {
        return $this->membership($project, $customer)?->role;
    }

    public function canViewVms(Project $project, Customer $customer): bool
    {
        return (bool) $this->membership($project, $customer)?->canViewVms();
    }

    public function canManageVms(Project $project, Customer $customer): bool
    {
        return (bool) $this->membership($project, $customer)?->canManageVms();
    }

    public function canManageMembers(Project $project, Customer $customer): bool
    {
        return (bool) $this->membership($project, $customer)?->canManageMembers();
    }

    public function canViewBilling(Project $project, Customer $customer): bool
    {
        return (bool) $this->membership($project, $customer)?->canViewBilling();
    }

    public function visibleVms(Project $project, Customer $customer): HasMany
    {
        $membership = $this->membership($project, $customer);
        abort_unless($membership, 404);

        $query = $project->virtualMachines()->notDeleted();

        if ($membership->canAccessAllVms()) {
            return $query;
        }

        if ($membership->canAccessOwnVms()) {
            $query->where(function (Builder $query) use ($customer): void {
                $query->where('created_by_customer_id', $customer->id)
                    ->orWhere(function (Builder $query) use ($customer): void {
                        $query->whereNull('created_by_customer_id')
                            ->where('customer_id', $customer->id);
                    });
            });

            return $query;
        }

        $query->whereExists(function (Builder $query) use ($membership): void {
            $query->selectRaw('1')
                ->from('project_member_virtual_machines')
                ->whereColumn('project_member_virtual_machines.virtual_machine_id', 'virtual_machines.id')
                ->where('project_member_virtual_machines.project_member_id', $membership->id);
        });

        return $query;
    }

    public function resolveCustomerVm(Request $request, VirtualMachine $vm, bool $manage = false): VirtualMachine
    {
        $customer = $request->user('customer');
        $vm->loadMissing(['project.members', 'project.owner']);

        abort_if($vm->isDeleted() || ! $vm->project, 404);

        $membership = $this->membership($vm->project, $customer);
        abort_unless($membership, 404);

        $allowed = $manage
            ? $this->canManageVms($vm->project, $customer)
            : $this->canViewVms($vm->project, $customer);

        abort_unless($allowed, 404);

        if ($membership->canAccessAllVms()) {
            return $vm;
        }

        if ($membership->canAccessOwnVms()) {
            $createdByCustomerId = $vm->created_by_customer_id ?? $vm->customer_id;
            abort_unless((int) $createdByCustomerId === (int) $customer->id, 404);

            return $vm;
        }

        abort_unless(
            $membership->specificVirtualMachines()
                ->whereKey($vm->getKey())
                ->exists(),
            404
        );

        return $vm;
    }
}
