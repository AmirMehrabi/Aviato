@extends('layouts.api-docs')

@section('title', 'مستندات API | آویاتو')
@section('content')
@php
    $baseUrl = url('/api/v1');
    $project = 'YOUR_PROJECT_UUID';
    $key = 'YOUR_API_KEY';
    $vm = 'YOUR_VM_UUID';
    $headers = "-H \"Accept: application/json\" -H \"Authorization: Bearer {$key}\"";
    $jsonHeaders = $headers.' -H "Content-Type: application/json"';

    $navGroups = [
        ['label' => 'شروع کار', 'items' => [
            ['id' => 'workflow', 'label' => 'نمای کلی workflow'],
            ['id' => 'authentication', 'label' => 'احراز هویت و دسترسی‌ها'],
        ]],
        ['label' => 'کشف منابع', 'items' => [
            ['id' => 'options', 'label' => 'گزینه‌های ساخت VM'],
            ['id' => 'projects', 'label' => 'پروژه و UUID'],
        ]],
        ['label' => 'چرخه عمر VM', 'items' => [
            ['id' => 'create-vm', 'label' => 'ساخت VM'],
            ['id' => 'list-vms', 'label' => 'لیست VMها'],
            ['id' => 'get-vm', 'label' => 'دریافت جزئیات VM'],
            ['id' => 'delete-vm', 'label' => 'حذف امن VM'],
        ]],
        ['label' => 'عملیات و اتصال', 'items' => [
            ['id' => 'states', 'label' => 'وضعیت‌ها و polling'],
            ['id' => 'credentials', 'label' => 'رمز عبور و SSH'],
        ]],
        ['label' => 'مرجع', 'items' => [
            ['id' => 'fields', 'label' => 'فیلدهای درخواست'],
            ['id' => 'errors', 'label' => 'خطاها و پاسخ‌ها'],
            ['id' => 'security', 'label' => 'امنیت'],
            ['id' => 'wallet-reference', 'label' => 'Wallet API قدیمی'],
        ]],
    ];
    $sectionIds = collect($navGroups)->flatMap(fn (array $group) => collect($group['items'])->pluck('id'))->values()->all();

    $curlOptions = "curl -X GET \"{$baseUrl}/projects/{$project}/virtual-machines/options\" \\\n+  {$headers}";
    $phpOptions = "\$response = Http::withToken('{$key}')\n+    ->acceptJson()\n+    ->get('{$baseUrl}/projects/{$project}/virtual-machines/options');\n+\n+\$options = \$response->json('data');";
    $jsOptions = "const response = await fetch('{$baseUrl}/projects/{$project}/virtual-machines/options', {\n+  headers: {\n+    Accept: 'application/json',\n+    Authorization: 'Bearer {$key}',\n+  },\n+});\n+const options = (await response.json()).data;";

    $curlCreate = "curl -X POST \"{$baseUrl}/projects/{$project}/virtual-machines\" \\\n+  {$jsonHeaders} \\\n+  -d '{\"infrastructure_location_id\":12,\"cloud_image_id\":4,\"vm_bundle_id\":2,\"display_name\":\"My Production Server\",\"login_username\":\"ubuntu\",\"ssh_public_key\":\"ssh-ed25519 ...\"}'";
    $phpCreate = "\$response = Http::withToken('{$key}')\n+    ->acceptJson()\n+    ->post('{$baseUrl}/projects/{$project}/virtual-machines', [\n+        'infrastructure_location_id' => 12,\n+        'cloud_image_id' => 4,\n+        'vm_bundle_id' => 2,\n+        'display_name' => 'My Production Server',\n+        'login_username' => 'ubuntu',\n+        'ssh_public_key' => 'ssh-ed25519 ...',\n+    ]);\n+\n+\$createdVm = \$response->json('data');";
    $jsCreate = "const response = await fetch('{$baseUrl}/projects/{$project}/virtual-machines', {\n+  method: 'POST',\n+  headers: {\n+    Accept: 'application/json',\n+    Authorization: 'Bearer {$key}',\n+    'Content-Type': 'application/json',\n+  },\n+  body: JSON.stringify({\n+    infrastructure_location_id: 12, cloud_image_id: 4, vm_bundle_id: 2,\n+    display_name: 'My Production Server', login_username: 'ubuntu',\n+    ssh_public_key: 'ssh-ed25519 ...',\n+  }),\n+});\n+const createdVm = (await response.json()).data;";

    $curlList = "curl -X GET \"{$baseUrl}/projects/{$project}/virtual-machines?per_page=25\" \\\n+  {$headers}";
    $phpList = "\$vms = Http::withToken('{$key}')\n+    ->acceptJson()\n+    ->get('{$baseUrl}/projects/{$project}/virtual-machines', ['per_page' => 25])\n+    ->json('data');";
    $jsList = "const response = await fetch('{$baseUrl}/projects/{$project}/virtual-machines?per_page=25', {\n+  headers: { Accept: 'application/json', Authorization: 'Bearer {$key}' },\n+});\n+const vms = (await response.json()).data;";

    $curlDetail = "curl -X GET \"{$baseUrl}/projects/{$project}/virtual-machines/{$vm}\" \\\n+  {$headers}";
    $phpDetail = "\$vm = Http::withToken('{$key}')\n+    ->acceptJson()\n+    ->get('{$baseUrl}/projects/{$project}/virtual-machines/{$vm}')\n+    ->json('data');";
    $jsDetail = "const response = await fetch('{$baseUrl}/projects/{$project}/virtual-machines/{$vm}', {\n+  headers: { Accept: 'application/json', Authorization: 'Bearer {$key}' },\n+});\n+const vm = (await response.json()).data;";

    $curlDelete = "curl -X DELETE \"{$baseUrl}/projects/{$project}/virtual-machines/{$vm}\" \\\n+  {$jsonHeaders} \\\n+  -d '{\"confirmation\":\"My Production Server\"}'";
    $phpDelete = "\$response = Http::withToken('{$key}')\n+    ->acceptJson()\n+    ->delete('{$baseUrl}/projects/{$project}/virtual-machines/{$vm}', [\n+        'confirmation' => 'My Production Server',\n+    ]);";
    $jsDelete = "const response = await fetch('{$baseUrl}/projects/{$project}/virtual-machines/{$vm}', {\n+  method: 'DELETE',\n+  headers: {\n+    Accept: 'application/json',\n+    Authorization: 'Bearer {$key}',\n+    'Content-Type': 'application/json',\n+  },\n+  body: JSON.stringify({ confirmation: 'My Production Server' }),\n+});\n+const deletion = (await response.json()).data;";

    foreach (['curlOptions', 'phpOptions', 'jsOptions', 'curlCreate', 'phpCreate', 'jsCreate', 'curlList', 'phpList', 'jsList', 'curlDetail', 'phpDetail', 'jsDetail', 'curlDelete', 'phpDelete', 'jsDelete'] as $sampleName) {
        ${$sampleName} = str_replace("\n+", "\n", ${$sampleName});
    }
@endphp

<div
    x-data="{
        activeSection: 'workflow',
        mobileNav: false,
        copied: null,
        copy(value, key) {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(value).then(() => {
                this.copied = key;
                setTimeout(() => this.copied = null, 1600);
            });
        },
        sectionIds: @js($sectionIds),
        init() {
            this.updateActiveSection();
            this.onScroll = () => window.requestAnimationFrame(() => this.updateActiveSection());
            window.addEventListener('scroll', this.onScroll, { passive: true });
            window.addEventListener('hashchange', () => this.updateActiveSection());
        },
        updateActiveSection() {
            const marker = window.scrollY + 170;
            let active = this.sectionIds[0];
            this.sectionIds.forEach((id) => {
                const section = document.getElementById(id);
                if (section && section.getBoundingClientRect().top + window.scrollY <= marker) active = id;
            });
            this.activeSection = active;
        },
        goTo(id) {
            document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            this.activeSection = id;
            this.mobileNav = false;
        }
    }"
    class="px-4 pb-24 pt-24 md:px-8 lg:px-10">
    <div class="mx-auto max-w-7xl">
        <header class="rounded-[1.75rem] border border-slate-200 bg-white px-6 py-10 shadow-[0_20px_60px_-35px_rgba(7,27,58,.35)] md:px-10">
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-[#EAF2FF] px-3 py-1.5 text-xs font-black text-[#0069FF]">AVIATO API · V1</span>
                <span class="inline-flex items-center gap-2 text-xs font-black text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span>Built for developers</span>
            </div>
            <h1 class="mt-5 max-w-4xl text-3xl font-black leading-[1.35] text-slate-950 md:text-5xl">ساخت زیرساخت ابری، با چند درخواست قابل اعتماد</h1>
            <p class="mt-4 max-w-3xl text-sm leading-8 text-slate-600 md:text-base">آویاتو برای تیم‌هایی ساخته شده که می‌خواهند از کد خودشان VM بسازند و کنترل کنند؛ بدون حدس زدن IDها، بدون مدیریت زیرساخت پشت‌صحنه و با وضعیت‌هایی که می‌شود به آن‌ها اعتماد کرد.</p>
            <div class="mt-6 flex flex-wrap gap-3"><a href="#workflow" @click.prevent="goTo('workflow')" class="rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white shadow-lg shadow-[#0069FF]/20">شروع سریع</a><a href="{{ route('customer.login') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-700">ساخت کلید API</a></div>
        </header>

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
            <aside class="w-full lg:block">
                <div class="lg:sticky lg:top-24">
                    <button type="button" @click="mobileNav = !mobileNav" class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black lg:hidden"><span>فهرست مستندات</span><span aria-hidden="true">⌄</span></button>
                    <nav :class="mobileNav ? 'block' : 'hidden lg:block'" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:mt-0" aria-label="فهرست مستندات">
                        <p class="px-3 pb-3 text-[10px] font-black tracking-[.16em] text-slate-400">ON THIS PAGE</p>
                        @foreach ($navGroups as $group)
                            <p class="px-3 pb-1 pt-4 text-[10px] font-black tracking-[.14em] text-slate-400">{{ $group['label'] }}</p>
                            @foreach ($group['items'] as $item)
                                <a href="#{{ $item['id'] }}" @click.prevent="goTo('{{ $item['id'] }}')" :class="activeSection === '{{ $item['id'] }}' ? 'bg-[#EEF5FF] text-[#0069FF] shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-[#0069FF]'" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-bold transition"><span>{{ $item['label'] }}</span><span x-show="activeSection === '{{ $item['id'] }}'" x-cloak class="shrink-0 text-[10px] font-black uppercase tracking-wider text-[#0069FF]">Active</span></a>
                            @endforeach
                        @endforeach
                    </nav>
                </div>
            </aside>

            <div class="min-w-0 space-y-8">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-8 text-amber-950"><strong>یک نکته قبل از شروع:</strong> مقدار <code dir="ltr">YOUR_PROJECT_UUID</code> را از آدرس یا صفحه جزئیات پروژه در پنل مشتری بردارید. گزینه‌های image، bundle و location را هم همیشه از endpoint گزینه‌ها بگیرید.</div>

                <section id="workflow" class="scroll-mt-28 rounded-2xl border border-[#B8D6FF] bg-[#F4F8FF] p-6 md:p-8">
                    <p class="text-xs font-black tracking-[.14em] text-[#0069FF]">START HERE</p><h2 class="mt-2 text-2xl font-black text-slate-950">از ایده تا VM آماده، در پنج قدم</h2>
                    <div class="mt-6 grid gap-3 md:grid-cols-5">@foreach (['کلید با دسترسی درست','options را بخوانید','VM را بسازید','detail را poll کنید','با نام دقیق حذف کنید'] as $i => $step)<div class="rounded-xl bg-white p-4 text-sm font-black"><span class="grid size-7 place-items-center rounded-lg bg-[#EAF2FF] text-xs text-[#0069FF]">{{ $i + 1 }}</span><p class="mt-4 leading-6">{{ $step }}</p></div>@endforeach</div>
                    <p class="mt-6 text-sm leading-8 text-slate-600">ساخت و حذف asynchronous هستند. پاسخ create به شما یک resource با <code dir="ltr">provisioning_status: pending</code> می‌دهد؛ با endpoint جزئیات، وضعیت را تا <code dir="ltr">ready</code> یا <code dir="ltr">failed</code> دنبال کنید.</p>
                </section>

                <section id="authentication" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">01 · ACCESS</p><h2 class="mt-2 text-2xl font-black text-slate-950">کلید API که فقط همان کار لازم را انجام می‌دهد</h2><p class="mt-4 text-sm leading-8 text-slate-600">کلید را از پروفایل مشتری بسازید و در همه درخواست‌ها به صورت Bearer Token بفرستید. برای یک integration خواندنی فقط <code dir="ltr">vm:read</code> بدهید؛ برای ساخت یا حذف، دسترسی جداگانه لازم است.</p><div class="mt-5 grid gap-3 sm:grid-cols-3 text-sm"><div class="rounded-xl bg-slate-50 p-4"><code dir="ltr" class="font-black">vm:read</code><p class="mt-2 text-slate-600">options، list و detail</p></div><div class="rounded-xl bg-slate-50 p-4"><code dir="ltr" class="font-black">vm:create</code><p class="mt-2 text-slate-600">ساخت VM</p></div><div class="rounded-xl bg-slate-50 p-4"><code dir="ltr" class="font-black">vm:delete</code><p class="mt-2 text-slate-600">حذف VM</p></div></div></section>

                <section id="options" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="GET" path="/projects/{project_uuid}/virtual-machines/options" ability="vm:read" /><h2 class="mt-5 text-xl font-black text-slate-950">به جای حدس زدن، گزینه‌های معتبر را بگیرید</h2><p class="mt-3 text-sm leading-8 text-slate-600">این endpoint locationهای فعال، imageهای فعال، خانواده و نسخه OS، سازگاری image و location، bundleهای مجاز، قیمت، ظرفیت IP، wallet و quota را برمی‌گرداند. credential، secret، <code dir="ltr">provider_metadata</code> و script داخلی هرگز در پاسخ نیستند.</p><x-api-code-samples key="options" :curl="$curlOptions" :php="$phpOptions" :javascript="$jsOptions" /></section>

                <section id="projects" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">SCOPE</p><h2 class="mt-2 text-2xl font-black text-slate-950">هر request متعلق به یک پروژه مشخص است</h2><p class="mt-4 text-sm leading-8 text-slate-600">هر مسیر VM با <code dir="ltr">/projects/{project_uuid}</code> محدود می‌شود. عضویت پروژه و دسترسی VM در همان درخواست بررسی می‌شود؛ بنابراین VM یک مشتری یا پروژه دیگر از طریق تغییر URL آشکار نمی‌شود.</p></section>

                <section id="create-vm" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="POST" path="/projects/{project_uuid}/virtual-machines" ability="vm:create" /><h2 class="mt-5 text-xl font-black text-slate-950">ساخت VM با همان قوانین پنل مشتری</h2><p class="mt-3 text-sm leading-8 text-slate-600"><code dir="ltr">cloud_image_id</code>، location و bundle را از options انتخاب کنید. با bundle، ابعاد از bundle می‌آید؛ بدون bundle هر سه <code dir="ltr">cpu_cores</code>، <code dir="ltr">ram_gb</code> و <code dir="ltr">disk_gb</code> لازم است. quota، wallet، سازگاری و ظرفیت IP قبل از ثبت بررسی می‌شوند.</p><div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">اگر password تولید شود، فقط یک‌بار در <code dir="ltr">generated_login_password</code> پاسخ create می‌آید. این مقدار در list و detail قابل بازیابی نیست.</div><x-api-code-samples key="create" :curl="$curlCreate" :php="$phpCreate" :javascript="$jsCreate" /></section>

                <section id="list-vms" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="GET" path="/projects/{project_uuid}/virtual-machines" ability="vm:read" /><h2 class="mt-5 text-xl font-black text-slate-950">لیست VMهای قابل مشاهده</h2><p class="mt-3 text-sm leading-8 text-slate-600">نتیجه صفحه‌بندی می‌شود. از <code dir="ltr">search</code> برای نام، hostname یا IP، از <code dir="ltr">status</code> برای وضعیت و از <code dir="ltr">per_page</code> برای اندازه صفحه استفاده کنید. VMهای deleted به صورت پیش‌فرض نمایش داده نمی‌شوند.</p><x-api-code-samples key="list" :curl="$curlList" :php="$phpList" :javascript="$jsList" /></section>

                <section id="get-vm" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="GET" path="/projects/{project_uuid}/virtual-machines/{virtual_machine_uuid}" ability="vm:read" /><h2 class="mt-5 text-xl font-black text-slate-950">جزئیات resource برای polling و اتصال</h2><p class="mt-3 text-sm leading-8 text-slate-600">این endpoint را بعد از create هر چند ثانیه بخوانید. password و اطلاعات حساس هیچ‌وقت برنمی‌گردند؛ وقتی <code dir="ltr">ssh_ready: true</code> شد، <code dir="ltr">ssh_command</code> آماده استفاده است.</p><x-api-code-samples key="detail" :curl="$curlDetail" :php="$phpDetail" :javascript="$jsDetail" /></section>

                <section id="delete-vm" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="DELETE" path="/projects/{project_uuid}/virtual-machines/{virtual_machine_uuid}" ability="vm:delete" /><h2 class="mt-5 text-xl font-black text-slate-950">حذف امن، با یک تایید انسانی</h2><p class="mt-3 text-sm leading-8 text-slate-600">بدنه درخواست باید شامل <code dir="ltr">confirmation</code> برابر <code dir="ltr">display_name</code> دقیق باشد. حذف remote در صف قرار می‌گیرد، status بلافاصله <code dir="ltr">deleting</code> می‌شود و billing از همان flow متوقف می‌شود. درخواست تکراری idempotent است.</p><x-api-code-samples key="delete" :curl="$curlDelete" :php="$phpDelete" :javascript="$jsDelete" /></section>

                <section id="states" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">OPERATIONS</p><h2 class="mt-2 text-2xl font-black text-slate-950">وضعیت‌هایی که می‌توانید به آن‌ها تکیه کنید</h2><div class="mt-5 grid gap-3 sm:grid-cols-2"><div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm"><code dir="ltr" class="font-black text-amber-800">pending</code><p class="mt-2 text-slate-600">درخواست ثبت شده و provisioning در صف است.</p></div><div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm"><code dir="ltr" class="font-black text-emerald-800">ready</code><p class="mt-2 text-slate-600">VM آماده استفاده و SSH در دسترس است.</p></div><div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm"><code dir="ltr" class="font-black text-rose-800">failed</code><p class="mt-2 text-slate-600">آماده‌سازی شکست خورده؛ failure را بررسی کنید.</p></div><div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm"><code dir="ltr" class="font-black text-slate-800">deleting / deleted</code><p class="mt-2 text-slate-600">حذف در حال انجام است یا نهایی شده است.</p></div></div></section>

                <section id="credentials" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">رمز و SSH، بدون سورپرایز</h2><p class="mt-4 text-sm leading-8 text-slate-600">password تولیدشده فقط در پاسخ create است؛ آن را در secret manager ذخیره کنید. API مسیر بازیابی password ندارد. پس از آماده شدن IP و provisioning، از username و <code dir="ltr">ssh_command</code> استفاده کنید.</p></section>

                <section id="fields" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">فیلدهای ساخت</h2><p class="mt-4 text-sm leading-8 text-slate-600">فیلدها شامل infrastructure_location_id، cloud_image_id، vm_bundle_id، display_name، login_username، login_password، ssh_public_key و requires_invoice هستند. اگر bundle ندارید، cpu_cores، ram_gb و disk_gb را هم ارسال کنید.</p></section>

                <section id="errors" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">وقتی چیزی طبق انتظار پیش نرفت</h2><p class="mt-4 text-sm leading-8 text-slate-600"><code dir="ltr">401</code> توکن نامعتبر، <code dir="ltr">403</code> دسترسی پروژه یا ability ناکافی، <code dir="ltr">404</code> resource خارج از scope، <code dir="ltr">422</code> validation یا quota/wallet/compatibility و <code dir="ltr">429</code> محدودیت درخواست است. برای پشتیبانی همیشه <code dir="ltr">meta.request_id</code> را نگه دارید.</p><pre class="mt-5 overflow-x-auto rounded-xl bg-red-50 p-4 text-xs leading-7 text-red-900" dir="ltr"><code>{ "error": { "code": "validation_error", "fields": {} }, "meta": { "request_id": "REQUEST_ID" } }</code></pre></section>

                <section id="security" class="scroll-mt-28 rounded-2xl border border-blue-100 bg-[#EEF5FF] p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">ساختن یک integration سالم</h2><p class="mt-4 text-sm leading-8 text-blue-950">کلید را در Git، log یا کد سمت کاربر نگذارید. برای هر سرویس و محیط کلید جدا بسازید، کمترین ability لازم را بدهید و در صورت افشا آن را revoke کنید. password اولیه را فقط در secret manager ذخیره کنید.</p></section>

                <section id="wallet-reference" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-xl font-black text-slate-950">Wallet API برای integrationهای موجود</h2><p class="mt-3 text-sm leading-8 text-slate-600">endpointهای کیف پول برای سازگاری همچنان در دسترس‌اند و به <code dir="ltr">wallet:read</code> نیاز دارند:</p><div class="mt-4 grid gap-2 text-sm font-bold"><span dir="ltr">/projects/{project_uuid}/wallet</span><span dir="ltr">/projects/{project_uuid}/wallet/transactions</span><span dir="ltr">/projects/{project_uuid}/wallet/transactions/{transaction}</span></div><div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-500"><span>Get remaining balance</span><span>List transactions</span><span>Get one transaction</span></div></section>
            </div>
        </div>
    </div>
</div>
@endsection
