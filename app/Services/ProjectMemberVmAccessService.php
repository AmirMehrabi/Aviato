<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\VirtualMachine;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectMemberVmAccessService
{
    public function rules(Project $project, bool $required = true): array
    {
        return [
            'vm_access_scope' => [$required ? 'required' : 'sometimes', Rule::in(ProjectMember::vmAccessScopes())],
            'vm_ids' => ['required_if:vm_access_scope,specific', 'array', 'min:1'],
            'vm_ids.*' => [
                'integer',
                Rule::exists('virtual_machines', 'id')->where('project_id', $project->id)
                    ->whereNull('deleted_at')->where('status', '!=', VirtualMachine::STATUS_DELETED),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'vm_access_scope.required' => 'محدوده دسترسی به ماشین‌ها را انتخاب کنید.',
            'vm_access_scope.in' => 'محدوده دسترسی انتخاب‌شده معتبر نیست.',
            'vm_ids.required_if' => 'حداقل یک ماشین برای دسترسی این عضو انتخاب کنید.',
            'vm_ids.min' => 'حداقل یک ماشین برای دسترسی این عضو انتخاب کنید.',
            'vm_ids.*.exists' => 'ماشین انتخاب‌شده باید فعال و متعلق به همین فضای کاری باشد.',
        ];
    }

    // Call inside the transaction that saves the membership.
    public function sync(ProjectMember $member, Project $project, array $data): void
    {
        if ($member->vm_access_scope !== ProjectMember::VM_ACCESS_SPECIFIC) {
            $member->specificVirtualMachines()->detach();

            return;
        }

        $vmIds = array_values(array_unique(array_map('intval', $data['vm_ids'] ?? [])));
        $projectVmIds = $project->virtualMachines()->notDeleted()->whereKey($vmIds)->pluck('id')->all();

        if ($vmIds === [] || count($projectVmIds) !== count($vmIds)) {
            throw ValidationException::withMessages(['vm_ids' => 'حداقل یک ماشین موجود در همین فضای کاری انتخاب کنید.']);
        }

        $member->specificVirtualMachines()->sync($projectVmIds);
    }
}
