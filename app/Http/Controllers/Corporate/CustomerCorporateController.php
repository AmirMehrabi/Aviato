<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CorporateDocument;
use App\Models\CorporateRequest;
use App\Services\Corporate\CorporateVerificationService;
use App\Services\NationalCodeService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerCorporateController extends Controller
{
    public function start(Request $request, CorporateVerificationService $service)
    {
        abort_unless(AppSetting::getValue('corporate.enabled', false), 403);
        abort_unless($request->user('customer')->hasVerifiedNationalCode(), 403);
        $request->validate(['national_id' => ['required', 'string', 'max:30']]);
        $id = app(NationalCodeService::class)->normalize($request->string('national_id')->toString());
        validator(['national_id' => $id], ['national_id' => ['required', 'regex:/^\d{11}$/', 'not_regex:/^(\d)\1{10}$/']])->validate();
        $corporate = $service->start($request->user('customer'), $id);

        return redirect()->route('customer.corporate.show', $corporate);
    }

    public function show(Request $request, CorporateRequest $corporate)
    {
        $this->authorizeOwner($request, $corporate);

        return view('customer.corporate.show', ['corporate' => $corporate->load('documents', 'events'), 'customer' => $request->user('customer'), 'wallets' => app(WalletService::class)]);
    }

    public function submit(Request $request, CorporateRequest $corporate, CorporateVerificationService $service)
    {
        $this->authorizeOwner($request, $corporate);
        abort_unless(AppSetting::getValue('corporate.enabled', false) && $request->user('customer')->hasVerifiedNationalCode(), 403);
        $request->merge(['postal_code' => app(NationalCodeService::class)->normalize((string) $request->input('postal_code'))]);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'], 'representative_role' => ['required', Rule::in(['signatory', 'delegate'])],
            'billing_email' => ['required', 'email', 'max:255'], 'company_phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'], 'postal_code' => ['required', 'regex:/^\d{10}$/'],
            'tax_number' => ['nullable', 'string', 'max:50'], 'consent' => ['accepted'],
            'incorporation' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'signatories' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'authorization' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $service->submit($corporate, $data, Arr::only($request->allFiles(), ['incorporation', 'signatories', 'authorization']));

        return back()->with('status', 'درخواست برای بررسی ارسال شد.');
    }

    public function document(Request $request, CorporateDocument $document)
    {
        $this->authorizeOwner($request, $document->request);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeOwner(Request $request, CorporateRequest $corporate): void
    {
        abort_unless((int) $corporate->customer_id === (int) $request->user('customer')->id, 404);
    }
}
