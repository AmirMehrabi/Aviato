<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Models\CorporateDelivery;
use App\Models\CorporateDocument;
use App\Models\CorporateRequest;
use App\Services\Corporate\CorporateVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminCorporateController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(CorporateRequest::statuses()))], 'q' => ['nullable', 'string', 'max:100']]);
        $query = CorporateRequest::with('customer')->latest('submitted_at')->latest('id');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['q'])) {
            $q = $data['q'];
            $query->where(fn ($query) => $query->where('national_id', 'like', '%'.$q.'%')->orWhere('company->name', 'like', '%'.$q.'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')));
        }

        return view('admin.corporate.index', ['requests' => $query->paginate(25)->withQueryString(), 'pendingCount' => CorporateRequest::where('status', 'pending')->count()]);
    }

    public function show(CorporateRequest $corporate)
    {
        return view('admin.corporate.show', ['corporate' => $corporate->load('customer', 'reviewer', 'documents', 'events.actor'),
            'deliveries' => CorporateDelivery::whereHas('event', fn ($query) => $query->where('corporate_request_id', $corporate->id))->latest('id')->get()]);
    }

    public function review(Request $request, CorporateRequest $corporate, CorporateVerificationService $service)
    {
        $data = $request->validate(['version' => ['required', 'integer'], 'decision' => ['required', Rule::in(['approved', 'needs_correction', 'rejected'])],
            'reason' => ['required_unless:decision,approved', 'nullable', 'string', 'max:3000'], 'internal_notes' => ['nullable', 'string', 'max:5000']]);
        $service->review($corporate, $request->user('admin'), $data);

        return back()->with('status', 'نتیجه بررسی ثبت شد.');
    }

    public function retry(Request $request, CorporateDelivery $delivery)
    {
        $request->validate(['confirmed_not_sent' => ['nullable', 'accepted']]);
        DB::transaction(function () use ($delivery, $request) {
            $locked = CorporateDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['failed', 'uncertain']), 409);
            if ($locked->status === 'uncertain' && ! $request->boolean('confirmed_not_sent')) {
                throw ValidationException::withMessages(['confirmed_not_sent' => 'ابتدا عدم ارسال پیام را در سرویس‌دهنده بررسی و تأیید کنید.']);
            }
            $locked->update(['status' => 'queued', 'attempts' => 0, 'error' => null]);
        });

        // The recovery scheduler dispatches the durable row after five minutes.
        return back()->with('status', 'اعلان برای ارسال مجدد در صف بازیابی قرار گرفت.');
    }

    public function document(CorporateDocument $document)
    {
        return Storage::disk('local')->download($document->path, $document->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
