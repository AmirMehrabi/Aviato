@extends('customer.layout')

@section('title', $project->name)
@section('header_title', $project->name)
@section('header_subtitle', 'نمای کلی فضای کاری، اعضا، دسترسی ماشین‌ها و تنظیمات')
@section('breadcrumbs')
    <a href="{{ route('customer.projects.index', [], false) }}" class="transition hover:text-[#0069FF]">فضاهای کاری</a>
    <span>/</span>
    <span class="truncate text-slate-700">{{ $project->name }}</span>
@endsection

@php
    $activeNav = 'projects';
    $canManageMembers = $projectMembership?->canManageMembers() ?? false;
    $ownerPays = (int) $project->owner_customer_id === (int) $customer->id;
    $isOwner = $ownerPays;
    $isActive = (int) $activeProject->id === (int) $project->id;
    $roleLabels = ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'];
    $sections = ['overview' => 'نمای کلی', 'members' => 'اعضا و دسترسی‌ها', 'settings' => 'تنظیمات'];
    if ($projectMembership?->canViewBilling()) {
        $sections = ['overview' => 'نمای کلی', 'members' => 'اعضا و دسترسی‌ها', 'billing' => 'مالی و هشدارها', 'settings' => 'تنظیمات'];
    }
    $requestedSection = request('section', 'overview');
    $section = is_string($requestedSection) && array_key_exists($requestedSection, $sections) ? $requestedSection : 'overview';
    $selectedWalletAlertRecipientIds = old('recipient_ids_present') !== null ? array_map('intval', old('recipient_ids', [])) : $walletAlertRecipientIds;
@endphp

@if($section === 'members' && old('_member_form'))
    @section('own_messages', '1')
@endif

@section('content')
    <nav aria-label="بخش‌های مدیریت فضای کاری" class="mb-5 flex gap-2 overflow-x-auto border-b border-slate-200 pb-3">
        @foreach($sections as $key => $label)
            <a href="{{ route('customer.projects.show', ['project' => $project, 'section' => $key], false) }}" @if($section === $key) aria-current="page" @endif class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-black transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0069FF] {{ $section === $key ? 'bg-[#EBF3FF] text-[#0069FF]' : 'text-slate-500 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </nav>
    @unless($isActive)
        <section class="mb-5 flex flex-col gap-4 rounded-lg border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="inactive-workspace-title">
            <div>
                <h2 id="inactive-workspace-title" class="font-black text-amber-950">در حال مدیریت یک فضای کاری غیرفعال هستید</h2>
                <p class="mt-1 text-sm font-bold leading-7 text-amber-800">تغییرات این صفحه برای «{{ $project->name }}» ذخیره می‌شود، اما منابع منو هنوز مربوط به «{{ $activeProject->name }}» است.</p>
            </div>
            <form method="POST" action="{{ route('customer.projects.switch', [], false) }}" class="shrink-0">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                <input type="hidden" name="destination" value="management">
                <input type="hidden" name="section" value="{{ $section }}">
                <button class="w-full rounded-lg bg-[#031B4E] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0A2A66] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] sm:w-auto">ورود به این فضای کاری</button>
            </form>
        </section>
    @endunless

    @if($section === 'overview')
        <section class="grid gap-5 lg:grid-cols-4">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black tracking-wide text-slate-500">مالک</p>
                <p class="mt-2 truncate text-xl font-black text-slate-950">{{ $project->owner?->name }}</p>
                <p class="mt-1 truncate text-sm font-bold text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black tracking-wide text-slate-500">نقش شما</p>
                <p class="mt-2 text-xl font-black text-slate-950">{{ $roleLabels[$projectMembership?->role ?? 'member'] ?? 'عضو' }}</p>
                <p class="mt-1 text-sm font-bold text-slate-500">{{ $canManageMembers ? 'امکان مدیریت اعضا دارید' : 'دسترسی شما محدود است' }}</p>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black tracking-wide text-slate-500">اعضا</p>
                <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($project->members->count()) }}</p>
                <p class="mt-1 text-sm font-bold text-slate-500">افراد دارای دسترسی</p>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black tracking-wide text-slate-500">ماشین‌ها</p>
                <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($visibleVirtualMachineCount ?? $project->virtualMachines->count()) }}</p>
                @if($isActive && ($projectMembership?->canViewVms() ?? false))
                    <a href="{{ route('customer.servers.index', [], false) }}" class="mt-1 inline-flex text-sm font-black text-[#0069FF]">مشاهده ماشین‌ها</a>
                @elseif(!$isActive)
                    <p class="mt-1 text-sm font-bold text-slate-500">برای مشاهده، ابتدا وارد این فضا شوید</p>
                @endif
            </article>
        </section>

        <div class="mt-5 rounded-xl border border-[#B8D6FF] bg-[#F8FBFF] p-5">
            <h2 class="text-lg font-black text-[#031B4E]">{{ session('workspace_created') ? 'فضای کاری شما آماده است' : 'گام بعدی در این فضای کاری' }}</h2>
            <p class="mt-2 text-sm leading-7 text-slate-600">{{ $project->name }} محیط مشترک منابع و اعضای شماست. هزینه منابع این فضا بر عهده {{ $ownerPays ? 'شما' : $project->owner?->name }} است.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                @if($isActive && ($projectMembership?->canManageVms() ?? false))
                    <a href="{{ route('customer.servers.create', [], false) }}" class="rounded-lg bg-[#0069FF] px-4 py-3 text-sm font-black text-white hover:bg-[#0050D0]">{{ $visibleVirtualMachineCount === 0 ? 'ساخت اولین ماشین' : 'ساخت ماشین جدید' }}</a>
                @endif
                @if($isActive && ($projectMembership?->canViewVms() ?? false))
                    <a href="{{ route('customer.servers.index', [], false) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700">مشاهده منابع</a>
                @endif
                <a href="{{ route('customer.projects.show', ['project' => $project, 'section' => 'members'], false) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700">{{ $canManageMembers ? 'افزودن عضو و مدیریت دسترسی‌ها' : 'مشاهده اعضا' }}</a>
            </div>
        </div>
    @elseif($section === 'members')
        <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">اعضا و دسترسی‌ها</h2>
                <p class="mt-2 text-sm leading-7 text-slate-500">نقش هر فرد مشخص می‌کند چه کاری می‌تواند انجام دهد؛ محدوده دسترسی مشخص می‌کند کدام ماشین‌ها را می‌بیند.</p>
                <div class="mt-5 space-y-3">
                    @foreach($project->members as $member)
                        @php
                            $isProjectOwner = (int) $member->customer_id === (int) $project->owner_customer_id;
                            $scopeLabel = $isProjectOwner ? 'همه ماشین‌ها' : (['all' => 'همه ماشین‌ها', 'own' => 'ماشین‌های خود عضو', 'specific' => 'ماشین‌های مشخص'][$member->vm_access_scope] ?? 'بدون دسترسی');
                        @endphp
                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words font-black text-slate-950">{{ $member->customer?->name }}</p>
                                    <p class="mt-1 break-all text-xs text-slate-500" dir="auto">{{ $member->customer?->email ?: $member->customer?->phone }}</p>
                                </div>
                                <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $roleLabels[$member->role] ?? $member->role }}</span>
                            </div>
                            <p class="mt-3 text-xs font-bold text-slate-600">
                                {{ $member->role === 'billing' ? 'بدون دسترسی به ماشین‌ها' : $scopeLabel }}
                                @if($canManageMembers && $member->vm_access_scope === 'specific' && $member->role !== 'billing')
                                    · {{ count($memberVmIds[$member->id] ?? []) }} ماشین
                                @endif
                            </p>
                            @if($isProjectOwner)
                                <p class="mt-2 text-xs leading-6 text-slate-400">مالک همیشه دسترسی کامل دارد.</p>
                            @elseif($canManageMembers)
                                <details class="group mt-3 border-t border-slate-100 pt-3" @if(old('_member_form') === 'member-'.$member->id) open @endif>
                                    <summary class="cursor-pointer text-sm font-bold text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0069FF]">ویرایش دسترسی</summary>
                                    <form method="POST" action="{{ route('customer.projects.members.update', [$project, $member], false) }}" class="mt-4">
                                        @csrf
                                        @method('PATCH')
                                        @include('customer.projects.partials.member-access-fields', ['editingMember' => $member])
                                    </form>
                                    <form method="POST" action="{{ route('customer.projects.members.destroy', [$project, $member], false) }}" class="mt-4 border-t border-slate-100 pt-3" onsubmit="return confirm('این عضو از فضای کاری حذف شود؟ دسترسی او به منابع این فضا قطع خواهد شد.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">حذف از فضای کاری</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">افزودن عضو</h2>
                @if($canManageMembers)
                    <p class="mt-2 text-sm leading-7 text-slate-500">دسترسی را انتخاب کنید؛ عضو جدید بلافاصله اضافه می‌شود و اعلان ورود به این فضا را دریافت می‌کند.</p>
                    <form method="POST" action="{{ route('customer.projects.members.store', $project, false) }}" class="mt-5">
                        @csrf
                        @include('customer.projects.partials.member-access-fields', ['editingMember' => null])
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm leading-7 text-slate-500">برای افزودن عضو و تغییر دسترسی‌ها باید مالک یا مدیر این فضای کاری باشید.</p>
                @endif
            </aside>
        </section>
    @elseif($section === 'billing')
        <section class="max-w-3xl space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-black text-slate-950">مسئول پرداخت: {{ $project->owner?->name }}</h2>
                <p class="mt-2 text-sm leading-7 text-slate-500">هزینه همه منابع این فضا از کیف‌پول مالک پرداخت می‌شود. هشدارهای این بخش بر اساس موجودی و هزینه ماهانه مالک هستند.</p>
                @if($isActive)
                    <a href="{{ route('customer.wallet.show', [], false) }}" class="mt-3 inline-flex text-sm font-black text-[#0069FF]">مشاهده کیف‌پول و پرداخت‌ها ←</a>
                @endif
            </div>
                @if($canManageMembers)
                        <form method="POST" action="{{ route('customer.projects.update', $project, false) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                            @csrf
                            @method('PATCH')
                            <input name="name" value="{{ old('name', $project->name) }}" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none" aria-label="نام فضای کاری">
                            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">تغییر نام</button>
                        </form>
                    @else
                        <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-500">فقط مالک یا مدیر این فضای کاری می‌تواند نام آن را تغییر دهد.</p>
                    @endif

                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-black text-slate-950">فضای کاری پیش‌فرض</h3>
                                <p class="mt-1 text-sm font-bold leading-7 text-slate-500">پس از ورود، این فضا به‌صورت پیش‌فرض انتخاب می‌شود. تغییر آن، فضای فعال فعلی را عوض نمی‌کند.</p>
                            </div>
                            @if($project->is_default)
                                <span class="w-fit shrink-0 rounded-lg bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-700">پیش‌فرض</span>
                            @elseif($isOwner)
                                <form method="POST" action="{{ route('customer.projects.default', $project, false) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-lg border border-[#0069FF] px-4 py-2 text-sm font-black text-[#0069FF] transition hover:bg-[#EBF3FF]">انتخاب به‌عنوان پیش‌فرض</button>
                                </form>
                            @else
                                <span class="text-sm font-bold text-slate-500">فقط مالک می‌تواند این گزینه را تغییر دهد.</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($canManageMembers)
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <h2 class="text-lg font-black text-slate-950">هشدارهای موجودی کیف‌پول</h2>
                        <p class="mt-1 text-sm leading-7 text-slate-500">این هشدارها با توجه به درصد موجودی مؤثر کیف‌پول مالک نسبت به هزینه ماهانه او ارسال می‌شوند. تنظیمات این فضا را می‌توانید مستقل از پیش‌فرض مدیر تغییر دهید.</p>
                        <form method="POST" action="{{ route('customer.projects.wallet-alerts.update', $project, false) }}" class="mt-5 space-y-5" x-data="{ thresholdMode: @js(old('threshold_mode', $project->wallet_alert_thresholds === null ? 'default' : 'custom')), recipientMode: @js(old('recipient_mode', $project->wallet_alert_recipient_ids === null ? 'default' : 'custom')) }">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="wallet-alert-threshold-mode" class="block text-sm font-black text-slate-700">درصدهای هشدار</label>
                                <select id="wallet-alert-threshold-mode" name="threshold_mode" x-model="thresholdMode" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                                    <option value="default">پیش‌فرض مدیر</option>
                                    <option value="custom">درصدهای سفارشی این فضا</option>
                                </select>
                                <p class="mt-2 text-xs font-bold leading-6 text-slate-500">پیش‌فرض فعلی: {{ implode('، ', $defaultWalletAlertThresholds) }} درصد</p>
                                @error('threshold_mode') <p class="mt-1 text-sm font-bold text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div x-cloak x-show="thresholdMode === 'custom'">
                                <label for="wallet-alert-thresholds" class="block text-sm font-black text-slate-700">درصدهای سفارشی</label>
                                <input id="wallet-alert-thresholds" name="thresholds" value="{{ old('thresholds', implode(', ', $walletAlertThresholds)) }}" placeholder="15, 10, 5" dir="ltr" :required="thresholdMode === 'custom'" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                                <p class="mt-2 text-xs font-bold leading-6 text-slate-500">تا ۱۰ درصد یکتا از ۱ تا ۱۰۰ را با ویرگول جدا کنید.</p>
                                @error('thresholds') <p class="mt-1 text-sm font-bold text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="border-t border-slate-100 pt-5">
                                <label for="wallet-alert-recipient-mode" class="block text-sm font-black text-slate-700">دریافت‌کنندگان</label>
                                <select id="wallet-alert-recipient-mode" name="recipient_mode" x-model="recipientMode" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                                    <option value="default">پیش‌فرض مدیر</option>
                                    <option value="custom">اعضای انتخاب‌شده این فضا</option>
                                </select>
                                <p class="mt-2 text-xs font-bold leading-6 text-slate-500">پیش‌فرض فعلی: {{ $defaultWalletAlertRecipientPolicy === 'owner_and_billing' ? 'مالک و اعضای مالی' : 'مالک فضای کاری' }}</p>
                                @error('recipient_mode') <p class="mt-1 text-sm font-bold text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div x-cloak x-show="recipientMode === 'custom'">
                                <p class="text-sm font-black text-slate-700">اعضای منتخب</p>
                                <input type="hidden" name="recipient_ids_present" value="1">
                                <div class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-4">
                                    @foreach($project->members as $member)
                                        @if($member->customer?->status === \App\Models\Customer::STATUS_ACTIVE)
                                            <label class="flex items-center gap-3 text-sm font-bold text-slate-700">
                                                <input type="checkbox" name="recipient_ids[]" value="{{ $member->customer_id }}" @checked(in_array($member->customer_id, $selectedWalletAlertRecipientIds, true)) class="rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                                                <span>{{ $member->customer->name }} ({{ $roleLabels[$member->role] ?? $member->role }})</span>
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs font-bold leading-6 text-slate-500">اگر هیچ عضوی را انتخاب نکنید، ارسال خودکار هشدارهای این فضا متوقف می‌شود.</p>
                                @error('recipient_ids') <p class="mt-1 text-sm font-bold text-red-600" role="alert">{{ $message }}</p> @enderror
                                @error('recipient_ids.*') <p class="mt-1 text-sm font-bold text-red-600" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ذخیره تنظیمات هشدار</button>
                        </form>
                    </div>
                @endif

        </section>
    @elseif($section === 'settings')
        <section class="max-w-3xl space-y-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <h2 class="text-lg font-black text-slate-950">تنظیمات فضای کاری</h2>
                    <p class="mt-1 text-sm leading-7 text-slate-500">مالک و مدیر می‌توانند نام فضای کاری را تغییر دهند. مسئول پرداخت تغییر نمی‌کند.</p>
                    @if($isOwner)
                    <div class="rounded-lg border border-red-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <h2 class="text-lg font-black text-red-700">حذف فضای کاری</h2>
                        <p class="mt-2 text-sm font-bold leading-7 text-slate-600">حذف دائمی است. فقط فضای کاری غیرپیش‌فرض و خالی را می‌توانید حذف کنید؛ سوابق مالی و انتقال‌های تکمیل‌شده حفظ می‌شوند.</p>

                        @if($deletionBlockers !== [])
                            <div class="mt-4 rounded-lg bg-red-50 p-4" role="status">
                                <p class="text-sm font-black text-red-800">این فضای کاری اکنون قابل حذف نیست:</p>
                                <ul class="mt-2 list-inside list-disc space-y-1 text-sm font-bold leading-7 text-red-700">
                                    @foreach($deletionBlockers as $blocker)
                                        <li>{{ $blocker }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <form method="POST" action="{{ route('customer.projects.destroy', $project, false) }}" class="mt-4" onsubmit="return confirm('فضای کاری «{{ addslashes($project->name) }}» برای همیشه حذف شود؟')">
                                @csrf
                                @method('DELETE')
                                <label class="block">
                                    <span class="text-sm font-black text-slate-700">برای تأیید، نام «{{ $project->name }}» را وارد کنید</span>
                                    <input name="confirmation" autocomplete="off" class="mt-2 w-full rounded-xl border border-red-200 px-4 py-3 focus:border-red-500 focus:outline-none" aria-describedby="delete-workspace-help">
                                </label>
                                <p id="delete-workspace-help" class="mt-2 text-xs font-bold text-slate-500">نام باید دقیقاً یکسان باشد.</p>
                                @error('delete')
                                    <p class="mt-2 text-sm font-bold text-red-600" role="alert">{{ $message }}</p>
                                @enderror
                                @error('confirmation')
                                    <p class="mt-2 text-sm font-bold text-red-600" role="alert">{{ $message }}</p>
                                @enderror
                                <button class="mt-4 rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">حذف دائمی فضای کاری</button>
                            </form>
                        @endif
                    </div>
                @endif
        </section>
    @endif
@endsection
