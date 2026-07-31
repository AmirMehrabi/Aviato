<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use App\Models\VmTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectLifecycleService
{
    /**
     * @return array<string, string>
     */
    public function deletionBlockers(Project $project, Customer $owner): array
    {
        $blockers = [];

        if ($project->is_default) {
            $blockers['default'] = 'ابتدا یک فضای کاری دیگر را به‌عنوان پیش‌فرض انتخاب کنید.';
        }

        if ($owner->ownedProjects()->count() <= 1) {
            $blockers['last_owned'] = 'آخرین فضای کاری شما قابل حذف نیست.';
        }

        if ($project->virtualMachines()->notDeleted()->exists()) {
            $blockers['virtual_machines'] = 'این فضای کاری هنوز ماشین مجازی فعال یا در حال حذف دارد.';
        }

        if ($project->storageBuckets()->exists() || $project->storageAccessKeys()->exists()) {
            $blockers['storage'] = 'ابتدا باکت‌ها و کلیدهای فضای ذخیره‌سازی این فضای کاری را حذف کنید.';
        }

        if (VmTransfer::query()
            ->whereNull('completed_at')
            ->where(function ($query) use ($project): void {
                $query->where('from_project_id', $project->id)
                    ->orWhere('to_project_id', $project->id);
            })
            ->exists()) {
            $blockers['transfers'] = 'یک انتقال ماشین مجازی مرتبط با این فضای کاری هنوز کامل نشده است.';
        }

        return $blockers;
    }

    public function setDefault(Project $project, Customer $owner): void
    {
        DB::transaction(function () use ($project, $owner): void {
            $owner->ownedProjects()->lockForUpdate()->get();
            $owner->ownedProjects()->whereKeyNot($project->id)->update(['is_default' => false]);
            $project->update(['is_default' => true]);
        });
    }

    public function delete(Project $project, Customer $owner): Project
    {
        return DB::transaction(function () use ($project, $owner): Project {
            $owner->ownedProjects()->lockForUpdate()->get();
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            $blockers = $this->deletionBlockers($project, $owner);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['delete' => reset($blockers)]);
            }

            $replacement = $owner->ownedProjects()
                ->whereKeyNot($project->id)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->firstOrFail();

            if (! $replacement->is_default) {
                $replacement->update(['is_default' => true]);
            }

            $project->delete();

            return $replacement;
        });
    }
}
