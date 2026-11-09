<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Notifications\WorkspaceAddedNotification;
use App\Services\ProjectAccessService;
use App\Services\ProjectLifecycleService;
use App\Services\ProjectMemberVmAccessService;
use App\Services\WalletService;
use App\Services\WorkspaceWalletAlertPreferences;
use App\Services\WorkspaceWalletAlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $projects,
        private readonly ProjectLifecycleService $lifecycle,
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        $projects = $this->projects->projectsFor($customer)->map(function (Project $project) use ($customer): Project {
            $project->setAttribute('visible_virtual_machines_count', $this->projects->visibleVms($project, $customer)->count());

            return $project;
        });

        return view('customer.projects.index', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'projects' => $projects,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        $customer = $request->user('customer');
        abort_unless($this->projects->membership($project->loadMissing(['members.customer', 'owner']), $customer), 404);
        $activeProject = $this->projects->activeProject($request, $customer);
        $visibleVirtualMachineCount = $this->projects->visibleVms($project, $customer)->count();

        return view('customer.projects.show', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'projects' => $this->projects->projectsFor($customer),
            'project' => $project->load(['owner', 'members.customer']),
            'memberVmIds' => $this->projects->canManageMembers($project, $customer)
                ? $project->members()->with('specificVirtualMachines:id')->get()->mapWithKeys(fn (ProjectMember $member) => [$member->id => $member->specificVirtualMachines->modelKeys()])->all()
                : [],
            'assignableVms' => $this->projects->canManageMembers($project, $customer)
                ? $project->virtualMachines()->notDeleted()->orderBy('display_name')->get(['id', 'name', 'display_name', 'ip_address'])
                : collect(),
            'visibleVirtualMachineCount' => $visibleVirtualMachineCount,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projectMembership' => $this->projects->membership($project, $customer),
            'walletAlertThresholds' => app(WorkspaceWalletAlertService::class)->thresholds($project),
            'walletAlertRecipientIds' => array_map(
                fn (Customer $recipient): int => $recipient->id,
                app(WorkspaceWalletAlertService::class)->recipients($project),
            ),
            'defaultWalletAlertThresholds' => AppSetting::customerWalletAlertPercentages(),
            'defaultWalletAlertRecipientPolicy' => AppSetting::customerWalletAlertRecipientPolicy(),
            'deletionBlockers' => $this->projects->canDeleteProject($project, $customer)
                ? $this->lifecycle->deletionBlockers($project, $customer)
                : [],
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

        return redirect()->route('customer.projects.show', $project)
            ->with('workspace_created', true)
            ->with('status', 'فضای کاری ساخته و فعال شد.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing(['members', 'owner']);
        abort_unless($this->projects->canRenameProject($project, $customer), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $project->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($project->owner, $data['name'], $project),
        ]);

        return redirect()->route('customer.projects.show', ['project' => $project, 'section' => 'settings'])->with('status', 'نام فضای کاری تغییر کرد.');
    }

    public function updateWalletAlerts(Request $request, Project $project): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        abort_unless($this->projects->canManageMembers($project, $customer), 404);

        app(WorkspaceWalletAlertPreferences::class)->update($request, $project);

        return redirect()->route('customer.projects.show', ['project' => $project, 'section' => 'billing'])->with('status', 'تنظیمات هشدار کیف‌پول ذخیره شد.');
    }

    public function setDefault(Request $request, Project $project): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_unless($this->projects->canSetDefaultProject($project, $customer), 404);

        $this->lifecycle->setDefault($project, $customer);

        return redirect()->route('customer.projects.show', ['project' => $project, 'section' => 'settings'])
            ->with('status', 'فضای کاری پیش‌فرض تغییر کرد.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_unless($this->projects->canDeleteProject($project, $customer), 404);

        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$project->name])],
        ], [
            'confirmation.in' => 'نام فضای کاری دقیقاً با نام فعلی آن یکسان نیست.',
        ]);

        $blockers = $this->lifecycle->deletionBlockers($project, $customer);
        if ($blockers !== []) {
            return redirect()->route('customer.projects.show', ['project' => $project, 'section' => 'settings'])
                ->withErrors(['delete' => reset($blockers)]);
        }

        $wasActive = (int) $request->session()->get(ProjectAccessService::SESSION_KEY) === (int) $project->id;
        $replacement = $this->lifecycle->delete($project, $customer);

        if ($wasActive) {
            $request->session()->put(ProjectAccessService::SESSION_KEY, $replacement->id);
        }

        return redirect()->route('customer.projects.index')->with('status', 'فضای کاری حذف شد.');
    }

    public function switch(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'section' => ['sometimes', 'string', Rule::in(['overview', 'members', 'billing', 'settings'])],
        ]);
        $project = Project::query()->with('members')->findOrFail($data['project_id']);
        $this->projects->switch($request, $customer, $project);

        if ($request->input('destination') === 'management') {
            return redirect()->route('customer.projects.show', ['project' => $project, 'section' => $data['section'] ?? 'overview'])
                ->with('status', 'فضای کاری فعال تغییر کرد.');
        }

        if ($request->input('destination') === 'resources' && $this->projects->canViewVms($project, $customer)) {
            return redirect()->route('customer.servers.index')->with('status', 'فضای کاری فعال تغییر کرد.');
        }

        return redirect()->route('dashboard')->with('status', 'فضای کاری فعال تغییر کرد.');
    }

    public function enter(Request $request, Project $project): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        $this->projects->switch($request, $customer, $project);

        $customer->unreadNotifications()
            ->where('data->event', 'workspace_added')
            ->where('data->project_id', $project->id)
            ->update(['read_at' => now()]);

        if ($request->boolean('wallet_topup') && $this->projects->canViewBilling($project, $customer)) {
            return redirect()->route('customer.wallet.show', ['topup' => 1, 'restore' => 1]);
        }

        return redirect()->route('dashboard')
            ->with('status', "اکنون در فضای کاری «{$project->name}» هستید؛ ماشین‌ها، هزینه‌ها و دسترسی‌های پنل به این فضا مربوط است.");
    }

    public function storeMember(Request $request, Project $project): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        abort_unless($this->projects->canManageMembers($project, $customer), 404);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            ...app(ProjectMemberVmAccessService::class)->rules($project, required: false),
            'dns_read' => ['sometimes', 'boolean'],
            'dns_manage' => ['sometimes', 'boolean'],
            'role' => ['required', Rule::in([
                ProjectMember::ROLE_ADMIN,
                ProjectMember::ROLE_MEMBER,
                ProjectMember::ROLE_VIEWER,
                ProjectMember::ROLE_BILLING,
            ])],
        ], app(ProjectMemberVmAccessService::class)->messages());

        $identifier = trim($data['identifier']);
        $member = Customer::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $member) {
            return back()->withInput()->withErrors(['identifier' => 'مشتری با این ایمیل یا شماره موبایل پیدا نشد.']);
        }

        $existingMembership = $project->members()->where('customer_id', $member->id)->first();
        if ($member->id === $project->owner_customer_id) {
            throw ValidationException::withMessages(['identifier' => 'مالک از قبل عضو این فضای کاری است و همیشه دسترسی کامل دارد.']);
        }

        $membership = DB::transaction(function () use ($project, $member, $customer, $data, $request, $existingMembership): ProjectMember {
            $membership = $project->members()->updateOrCreate(
                ['customer_id' => $member->id],
                [
                    'role' => $data['role'],
                    'vm_access_scope' => $data['vm_access_scope'] ?? $existingMembership?->vm_access_scope
                        ?? ProjectMember::defaultVmAccessScopeForRole($data['role']),
                    'invited_by_customer_id' => $customer->id,
                    'dns_read' => $request->boolean('dns_read'),
                    'dns_manage' => $request->boolean('dns_manage'),
                ],
            );

            if (array_key_exists('vm_access_scope', $data)) {
                app(ProjectMemberVmAccessService::class)->sync($membership, $project, $data);
            }

            return $membership;
        });

        if (! $existingMembership && $member->id !== $customer->id) {
            $member->notify(new WorkspaceAddedNotification($project, $customer, $membership->role));
        }

        $message = $existingMembership
            ? "دسترسی {$member->name} در فضای کاری به‌روزرسانی شد."
            : "{$member->name} به «{$project->name}» اضافه شد و اعلان برای او ارسال شد.";

        return back()->with('status', $message);
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        abort_unless($member->project_id === $project->id, 404);
        abort_unless($this->projects->canManageMembers($project, $customer), 404);
        abort_if($member->customer_id === $project->owner_customer_id, 422);

        $data = $request->validate([
            ...app(ProjectMemberVmAccessService::class)->rules($project, required: false),
            'dns_read' => ['sometimes', 'boolean'],
            'dns_manage' => ['sometimes', 'boolean'],
            'role' => ['required', Rule::in([
                ProjectMember::ROLE_ADMIN,
                ProjectMember::ROLE_MEMBER,
                ProjectMember::ROLE_VIEWER,
                ProjectMember::ROLE_BILLING,
            ])],
        ], app(ProjectMemberVmAccessService::class)->messages());

        DB::transaction(function () use ($member, $project, $data, $request): void {
            $member->update([
                'role' => $data['role'],
                'vm_access_scope' => $data['vm_access_scope'] ?? $member->vm_access_scope,
                'dns_read' => $request->boolean('dns_read'),
                'dns_manage' => $request->boolean('dns_manage'),
            ]);

            if (array_key_exists('vm_access_scope', $data)) {
                app(ProjectMemberVmAccessService::class)->sync($member, $project, $data);
            }
        });

        return back()->with('status', 'دسترسی عضو به‌روزرسانی شد.');
    }

    public function destroyMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $customer = $request->user('customer');
        $project->loadMissing('members');
        abort_unless($member->project_id === $project->id, 404);
        abort_unless($this->projects->canManageMembers($project, $customer), 404);
        abort_if($member->customer_id === $project->owner_customer_id, 422);

        $member->delete();

        return back()->with('status', 'عضو از فضای کاری حذف شد.');
    }

    private function uniqueSlug(Customer $customer, string $name, ?Project $ignore = null): string
    {
        $slug = Str::slug($name) ?: 'project';
        $candidate = $slug;
        $suffix = 2;

        while ($customer->ownedProjects()
            ->where('slug', $candidate)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }
}
