@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')
@section('title', 'Transfer VM Ownership')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6">
        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="text-sm font-bold text-[#0069FF] hover:underline">
            ← بازگشت به VM
        </a>
    </div>

    <div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="absolute -left-16 -top-16 size-48 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative">
            <p class="text-sm font-bold text-white/60">Transfer VM Ownership</p>
            <h1 class="mt-1 text-2xl font-black md:text-4xl" dir="ltr">{{ $vm->name }}</h1>
            <p class="mt-3 leading-8 text-white/75">
                Current Owner: <span class="font-black">{{ $vm->customer?->name }}</span> · 
                Project: <span class="font-black">{{ $vm->project?->name }}</span>
            </p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_450px]">
        <!-- Transfer Form -->
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Transfer to New Customer</h2>
            <p class="mt-2 text-sm text-slate-600">
                This will transfer the VM, its billing, and all dependencies to a new customer. The unbilled amount will be transferred to the new customer's wallet.
            </p>

            @if($vm->isDeleting() || $vm->isDeleted())
                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">
                    ⚠️ This VM is being deleted or has been deleted and cannot be transferred.
                </div>
            @elseif($vm->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING)
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    ⚠️ This VM is still being provisioned. Please wait until provisioning is complete before transferring.
                </div>
            @elseif($vm->pendingUpgradeOrders()->exists())
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                    ⚠️ This VM has pending upgrade orders. Please wait for them to complete before transferring.
                </div>
            @else
                <form method="POST" action="{{ route('admin.virtual-machines.transfer', $vm) }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Select New Customer *</label>
                        <select name="to_customer_id" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none focus:ring-2 focus:ring-[#0069FF]/20">
                            <option value="">-- Select Customer --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('to_customer_id') == $customer->id)>
                                    {{ $customer->name }} ({{ $customer->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('to_customer_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Transfer Notes (Optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes about this transfer..." class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none focus:ring-2 focus:ring-[#0069FF]/20">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-slate-500">These notes will be saved in the transfer history for audit purposes.</p>
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <h3 class="font-black text-amber-900">⚠️ Transfer Impact</h3>
                        <ul class="mt-3 space-y-2 text-sm text-amber-800">
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>VM ownership will be transferred to the selected customer</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>VM will be moved to the new customer's default project</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>Unbilled amount ({{ $money->format($vm->unbilled_amount ?? 0) }}) will be transferred</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>Future billing will be charged to the new customer</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>All VM data, backups, and configurations will remain intact</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5">•</span>
                                <span>A complete audit trail will be maintained</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <input type="checkbox" name="confirm_transfer" id="confirm_transfer" value="1" required class="mt-1 size-4 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                        <label for="confirm_transfer" class="text-sm font-bold text-slate-700">
                            I confirm that I want to transfer this VM to the selected customer. I understand this action will change billing ownership and cannot be easily reversed.
                        </label>
                    </div>
                    @error('confirm_transfer')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="flex gap-3">
                        <button type="submit" class="rounded-lg bg-[#0069FF] px-6 py-3 text-sm font-black text-white hover:bg-[#0052CC]">
                            Transfer VM Ownership
                        </button>
                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="rounded-lg border border-slate-200 bg-white px-6 py-3 text-sm font-black text-slate-700 hover:bg-slate-50">
                            Cancel
                        </a>
                    </div>
                </form>
            @endif
        </section>

        <!-- Current VM Info -->
        <section class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black">Current VM Details</h2>
                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">Status:</span>
                        <span class="font-black">{{ $vm->status }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">Provisioning:</span>
                        <span class="font-black">{{ $vm->provisioning_status }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">Resources:</span>
                        <span class="font-black">{{ $vm->cpu_cores }}C / {{ $vm->ram_gb }}GB / {{ $vm->disk_gb }}GB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">Monthly Cost:</span>
                        <span class="font-black">{{ $money->format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-500">Unbilled Amount:</span>
                        <span class="font-black">{{ $money->format($vm->unbilled_amount ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black">Current Ownership</h2>
                <div class="mt-5 space-y-3 text-sm">
                    <div>
                        <p class="font-bold text-slate-500">Billing Customer:</p>
                        <p class="mt-1 font-black">{{ $vm->customer?->name }}</p>
                        <p class="text-xs text-slate-500">{{ $vm->customer?->email }}</p>
                    </div>
                    <div>
                        <p class="font-bold text-slate-500">Project:</p>
                        <p class="mt-1 font-black">{{ $vm->project?->name }}</p>
                    </div>
                    <div>
                        <p class="font-bold text-slate-500">Created By:</p>
                        <p class="mt-1 font-black">{{ $vm->creator?->name }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Transfer History -->
    @if($transfers->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Transfer History</h2>
            <p class="mt-2 text-sm text-slate-600">Complete audit trail of all ownership transfers for this VM.</p>

            <div class="mt-5 space-y-4">
                @foreach($transfers as $transfer)
                    <div class="rounded-lg border border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <span class="rounded-lg bg-[#EBF3FF] px-3 py-1 text-xs font-black text-[#0069FF]">
                                        Transfer #{{ $transfer->id }}
                                    </span>
                                    <span class="text-sm text-slate-500">
                                        {{ $transfer->completed_at?->format('Y/m/d H:i') }}
                                    </span>
                                </div>
                                <div class="mt-3 flex items-center gap-2 text-sm">
                                    <span class="font-black">{{ $transfer->fromCustomer?->name }}</span>
                                    <span class="text-slate-400">→</span>
                                    <span class="font-black">{{ $transfer->toCustomer?->name }}</span>
                                </div>
                                @if($transfer->notes)
                                    <p class="mt-2 text-sm text-slate-600">{{ $transfer->notes }}</p>
                                @endif
                                <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
                                    <span>Initiated by: <span class="font-bold">{{ $transfer->initiatedBy?->name }}</span></span>
                                    <span>Unbilled transferred: <span class="font-bold">{{ $money->format($transfer->unbilled_amount_transferred) }}</span></span>
                                </div>
                            </div>
                        </div>

                        @if($transfer->snapshot_before || $transfer->snapshot_after)
                            <details class="mt-4">
                                <summary class="cursor-pointer text-xs font-bold text-slate-500 hover:text-slate-700">
                                    View Technical Details
                                </summary>
                                <div class="mt-3 grid gap-4 md:grid-cols-2">
                                    @if($transfer->snapshot_before)
                                        <div class="rounded-lg bg-slate-50 p-3">
                                            <p class="text-xs font-black text-slate-700">Before Transfer</p>
                                            <pre class="mt-2 overflow-x-auto text-xs text-slate-600">{{ json_encode($transfer->snapshot_before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                    @if($transfer->snapshot_after)
                                        <div class="rounded-lg bg-slate-50 p-3">
                                            <p class="text-xs font-black text-slate-700">After Transfer</p>
                                            <pre class="mt-2 overflow-x-auto text-xs text-slate-600">{{ json_encode($transfer->snapshot_after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
