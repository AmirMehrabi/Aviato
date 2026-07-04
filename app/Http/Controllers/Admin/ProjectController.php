<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\BillingService;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $hasSpecificVmPivot = Schema::hasTable('project_member_virtual_machines');

        $project->load([
            'owner',
            'members.customer',
            'virtualMachines' => fn ($query) => $query->notDeleted()->with(['creator', 'customer', 'proxmoxServer', 'bundle', 'disks']),
        ])->loadCount(['members', 'virtualMachines']);

        if ($hasSpecificVmPivot) {
            $project->members->load([
                'specificVirtualMachines' => fn ($query) => $query->notDeleted()->orderBy('display_name'),
            ]);
        } else {
            $project->members->each->setRelation('specificVirtualMachines', collect());
        }

        $vmPrices = $project->virtualMachines->mapWithKeys(function ($vm) {
            return [$vm->uuid => $this->billing->estimateMonthly($vm)];
        });

        $totalMonthlyCost = $vmPrices->sum();

        return view('admin.projects.show', [
            'project' => $project,
            'vmPrices' => $vmPrices,
            'totalMonthlyCost' => $totalMonthlyCost,
            'workspaceVirtualMachines' => $project->virtualMachines->sortBy('display_name')->values(),
            'roleOptions' => collect([
                ProjectMember::ROLE_ADMIN => 'مدیر',
                ProjectMember::ROLE_MEMBER => 'عضو',
                ProjectMember::ROLE_VIEWER => 'فقط مشاهده',
                ProjectMember::ROLE_BILLING => 'مالی',
            ]),
            'vmAccessScopeOptions' => collect([
                ProjectMember::VM_ACCESS_ALL => 'همه VMها',
                ProjectMember::VM_ACCESS_OWN => 'VMهای خود عضو',
                ProjectMember::VM_ACCESS_SPECIFIC => 'VMهای مشخص',
            ]),
            'hasSpecificVmPivot' => $hasSpecificVmPivot,
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

    public function storeMember(Request $request, Project $project): RedirectResponse
    {
        $this->assertManageMembers($request, $project);

        $data = $this->validateMemberStoreData($request, $project);
        $member = $this->findMember($data['identifier']);

        if (! $member) {
            return back()
                ->withInput()
                ->withErrors(['identifier' => 'مشتری با این ایمیل یا موبایل پیدا نشد.']);
        }

        DB::transaction(function () use ($project, $member, $request, $data): void {
            $projectMember = $project->members()->updateOrCreate(
                ['customer_id' => $member->id],
                [
                    'role' => $member->id === $project->owner_customer_id ? ProjectMember::ROLE_OWNER : $data['role'],
                    'vm_access_scope' => $member->id === $project->owner_customer_id
                        ? ProjectMember::VM_ACCESS_ALL
                        : $data['vm_access_scope'],
                    'invited_by_customer_id' => $request->user('admin')->id,
                ],
            );

            $this->syncProjectMemberVms($projectMember, $project, $data);
        });

        return back()->with('status', 'عضو جدید به فضای کاری اضافه شد.');
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $this->assertManageMembers($request, $project);
        abort_unless($member->project_id === $project->id, 404);
        abort_if($member->customer_id === $project->owner_customer_id, 422);

        $data = $this->validateMemberUpdateData($request, $project);

        DB::transaction(function () use ($project, $member, $data): void {
            $member->update([
                'role' => $data['role'],
                'vm_access_scope' => $data['vm_access_scope'],
            ]);

            $this->syncProjectMemberVms($member, $project, $data);
        });

        return back()->with('status', 'دسترسی عضو به‌روزرسانی شد.');
    }

    public function destroyMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $this->assertManageMembers($request, $project);
        abort_unless($member->project_id === $project->id, 404);
        abort_if($member->customer_id === $project->owner_customer_id, 422);

        $member->delete();

        return back()->with('status', 'عضو از فضای کاری حذف شد.');
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

    private function assertManageMembers(Request $request, Project $project): void
    {
        $admin = $request->user('admin');
        abort_unless($admin, 404);
    }

    private function findMember(string $identifier): ?Customer
    {
        $identifier = trim($identifier);

        return Customer::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();
    }

    private function validateMemberStoreData(Request $request, Project $project): array
    {
        return $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            ...$this->validateMemberFields($project),
        ], $this->memberValidationMessages());
    }

    private function validateMemberUpdateData(Request $request, Project $project): array
    {
        return $request->validate($this->validateMemberFields($project), $this->memberValidationMessages());
    }

    private function validateMemberFields(Project $project): array
    {
        return [
            'role' => ['required', Rule::in([
                ProjectMember::ROLE_ADMIN,
                ProjectMember::ROLE_MEMBER,
                ProjectMember::ROLE_VIEWER,
                ProjectMember::ROLE_BILLING,
            ])],
            'vm_access_scope' => ['required', Rule::in(ProjectMember::vmAccessScopes())],
            'vm_ids' => ['required_if:vm_access_scope,specific', 'array', 'min:1'],
            'vm_ids.*' => ['integer', Rule::exists('virtual_machines', 'id')->where('project_id', $project->id)],
        ];
    }

    private function memberValidationMessages(): array
    {
        return [
            'vm_ids.required_if' => 'در حالت VMهای مشخص، باید حداقل یک ماشین انتخاب شود.',
        ];
    }

    private function syncProjectMemberVms(ProjectMember $member, Project $project, array $data): void
    {
        if ($member->vm_access_scope !== ProjectMember::VM_ACCESS_SPECIFIC) {
            $member->specificVirtualMachines()->detach();

            return;
        }

        $vmIds = collect($data['vm_ids'] ?? [])
            ->map(fn ($value): int => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        abort_if($vmIds === [], 422, 'در حالت VMهای مشخص، باید حداقل یک ماشین انتخاب شود.');

        $projectVmIds = $project->virtualMachines()->whereKey($vmIds)->pluck('id')->all();
        abort_if(count($projectVmIds) !== count($vmIds), 422, 'یکی از VMهای انتخاب‌شده متعلق به همین فضای کاری نیست.');

        $member->specificVirtualMachines()->sync($projectVmIds);
    }
}
