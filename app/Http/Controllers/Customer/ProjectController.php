<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $projects,
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);

        return view('customer.projects.index', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'projects' => $this->projects->projectsFor($customer),
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        $customer = $request->user('customer');
        abort_unless($this->projects->membership($project->loadMissing(['members.customer', 'owner']), $customer), 404);
        $this->projects->switch($request, $customer, $project);

        return view('customer.projects.show', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'projects' => $this->projects->projectsFor($customer),
            'project' => $project->load(['owner', 'members.customer', 'virtualMachines.creator']),
            'activeProject' => $project,
            'activeMembership' => $this->projects->membership($project, $customer),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $project = $customer->ownedProjects()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($customer, $data['name']),
            'is_default' => false,
        ]);
        $project->members()->create([
            'customer_id' => $customer->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
        $this->projects->switch($request, $customer, $project);

        return redirect()->route('customer.projects.show', $project)->with('status', 'Project created.');
    }

    public function switch(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);
        $project = Project::query()->with('members')->findOrFail($data['project_id']);
        $this->projects->switch($request, $customer, $project);

        return back()->with('status', 'Project switched.');
    }

    public function storeMember(Request $request, Project $project): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        abort_unless($this->projects->canManageMembers($project, $customer), 404);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in([
                ProjectMember::ROLE_ADMIN,
                ProjectMember::ROLE_MEMBER,
                ProjectMember::ROLE_VIEWER,
                ProjectMember::ROLE_BILLING,
            ])],
        ]);

        $identifier = trim($data['identifier']);
        $member = Customer::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $member) {
            return back()->withInput()->withErrors(['identifier' => 'No customer was found with this email or phone.']);
        }

        $project->members()->updateOrCreate(
            ['customer_id' => $member->id],
            [
                'role' => $member->id === $project->owner_customer_id ? ProjectMember::ROLE_OWNER : $data['role'],
                'invited_by_customer_id' => $customer->id,
            ],
        );

        return back()->with('status', 'Project member updated.');
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        abort_unless($member->project_id === $project->id, 404);
        abort_unless($this->projects->canManageMembers($project, $customer), 404);
        abort_if($member->customer_id === $project->owner_customer_id, 422);

        $data = $request->validate([
            'role' => ['required', Rule::in([
                ProjectMember::ROLE_ADMIN,
                ProjectMember::ROLE_MEMBER,
                ProjectMember::ROLE_VIEWER,
                ProjectMember::ROLE_BILLING,
            ])],
        ]);

        $member->update(['role' => $data['role']]);

        return back()->with('status', 'Project member role updated.');
    }

    public function destroyMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        abort_unless($member->project_id === $project->id, 404);
        abort_unless($this->projects->canManageMembers($project, $customer), 404);
        abort_if($member->customer_id === $project->owner_customer_id, 422);

        $member->delete();

        return back()->with('status', 'Project member removed.');
    }

    private function uniqueSlug(Customer $customer, string $name): string
    {
        $slug = Str::slug($name) ?: 'project';
        $candidate = $slug;
        $suffix = 2;

        while ($customer->ownedProjects()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }
}
