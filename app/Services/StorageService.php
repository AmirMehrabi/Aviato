<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use App\Models\StorageAccessKey;
use App\Models\StorageBucket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorageService
{
    public function __construct(private readonly ProjectAccessService $projects) {}

    /** @return Collection<int, StorageBucket> */
    public function buckets(Project $project, Customer $customer): Collection
    {
        $this->assertViewAccess($project, $customer);

        return $project->storageBuckets()->latest()->get();
    }

    public function createBucket(Project $project, Customer $customer, string $name): StorageBucket
    {
        $this->assertManageAccess($project, $customer);
        $name = strtolower(trim($name));

        if (! preg_match('/^(?=.{3,63}$)(?!\d+\.\d+\.\d+\.\d+$)[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $name)) {
            throw ValidationException::withMessages(['name' => 'نام باکت باید بین ۳ تا ۶۳ کاراکتر و فقط شامل حروف کوچک، عدد، خط تیره یا نقطه باشد.']);
        }

        if (StorageBucket::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'این نام باکت قبلا استفاده شده است.']);
        }

        return $project->storageBuckets()->create(['name' => $name, 'region' => config('storage.aviato_region', 'aviato-1')]);
    }

    public function deleteBucket(Project $project, Customer $customer, StorageBucket $bucket): void
    {
        $this->assertManageAccess($project, $customer);
        abort_unless($bucket->project_id === $project->id, 404);

        if ($bucket->objects()->exists() || $bucket->multipartUploads()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['bucket' => 'برای حذف باکت، ابتدا همه اشیا و آپلودهای نیمه‌کاره را حذف کنید.']);
        }

        $bucket->delete();
    }

    /** @return array{model: StorageAccessKey, secret: string} */
    public function createAccessKey(Project $project, Customer $customer, ?string $description = null): array
    {
        $this->assertManageAccess($project, $customer);
        $secret = Str::random(48);

        $key = DB::transaction(fn (): StorageAccessKey => $project->storageAccessKeys()->create([
            'access_key_id' => 'AVT'.strtoupper(Str::random(17)),
            'secret' => $secret,
            'description' => $description,
        ]));

        return ['model' => $key, 'secret' => $secret];
    }

    public function revokeAccessKey(Project $project, Customer $customer, StorageAccessKey $key): void
    {
        $this->assertManageAccess($project, $customer);
        abort_unless($key->project_id === $project->id, 404);
        $key->update(['status' => StorageAccessKey::STATUS_REVOKED]);
    }

    private function assertViewAccess(Project $project, Customer $customer): void
    {
        abort_unless($this->projects->membership($project, $customer), 404);
    }

    private function assertManageAccess(Project $project, Customer $customer): void
    {
        abort_unless($this->projects->canManageMembers($project, $customer), 403);
    }
}
