<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\StorageAccessKey;
use App\Models\StorageBucket;
use App\Services\ProjectAccessService;
use App\Services\StorageService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $projects,
        private readonly StorageService $storage,
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);

        return view('customer.storage.index', [
            'customer' => $customer,
            'activeProject' => $project,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'buckets' => $this->storage->buckets($project, $customer),
            'accessKeys' => $project->storageAccessKeys()->latest()->get(),
            'endpoint' => rtrim(config('storage.aviato_endpoint', config('app.url')), '/'),
        ]);
    }

    public function storeBucket(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);
        $this->storage->createBucket($project, $customer, (string) $request->validate(['name' => ['required', 'string', 'max:63']])['name']);

        return back()->with('status', 'باکت با موفقیت ساخته شد. اکنون می‌توانید آن را با ابزارهای استاندارد S3 استفاده کنید.');
    }

    public function destroyBucket(Request $request, StorageBucket $bucket): RedirectResponse
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);
        $this->storage->deleteBucket($project, $customer, $bucket);

        return back()->with('status', 'باکت حذف شد.');
    }

    public function storeAccessKey(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);
        $data = $request->validate(['description' => ['nullable', 'string', 'max:100']]);
        $credentials = $this->storage->createAccessKey($project, $customer, $data['description'] ?? null);

        return back()->with('storage_credentials', [
            'access_key_id' => $credentials['model']->access_key_id,
            'secret' => $credentials['secret'],
        ])->with('status', 'کلید دسترسی ساخته شد. رمز مخفی فقط همین یک بار نمایش داده می‌شود.');
    }

    public function destroyAccessKey(Request $request, StorageAccessKey $key): RedirectResponse
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);
        $this->storage->revokeAccessKey($project, $customer, $key);

        return back()->with('status', 'کلید دسترسی لغو شد.');
    }
}
