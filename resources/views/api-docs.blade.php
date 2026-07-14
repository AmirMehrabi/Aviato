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
                <span class="inline-flex items-center gap-2 text-xs font-black text-slate-500"><span class="size-2 rounded-full bg-slate-400"></span>راهنمای استفاده</span>
            </div>
            <h1 class="mt-5 max-w-4xl text-3xl font-black leading-[1.35] text-slate-950 md:text-5xl">راهنمای استفاده از API آویاتو</h1>
            <p class="mt-4 max-w-3xl text-sm leading-8 text-slate-600 md:text-base">در این صفحه، از ساخت کلید API و پیدا کردن UUID پروژه تا دریافت گزینه‌ها، ساخت VM، پیگیری وضعیت، اتصال SSH و حذف امن را قدم‌به‌قدم می‌بینید.</p>
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
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-8 text-amber-950"><strong>قبل از اولین درخواست:</strong> وارد پنل مشتری شوید، از لینک «پروفایل» در بالای سمت چپ صفحه وارد صفحه پروفایل شوید و یک کلید API بسازید. سپس UUID واقعی پروژه و گزینه‌های ساخت را از همین راهنما و endpoint گزینه‌ها بردارید.</div>

                <section id="workflow" class="scroll-mt-28 rounded-2xl border border-[#B8D6FF] bg-[#F4F8FF] p-6 md:p-8">
                    <p class="text-xs font-black tracking-[.14em] text-[#0069FF]">راهنمای شروع</p><h2 class="mt-2 text-2xl font-black text-slate-950">ترتیب پیشنهادی استفاده از API</h2>
                    <div class="mt-6 grid gap-3 md:grid-cols-5">@foreach (['وارد پنل شوید و کلید بسازید','UUID پروژه را پیدا کنید','گزینه‌های ساخت را بخوانید','VM بسازید و وضعیت را بخوانید','در صورت نیاز، VM را حذف کنید'] as $i => $step)<div class="rounded-xl bg-white p-4 text-sm font-black"><span class="grid size-7 place-items-center rounded-lg bg-[#EAF2FF] text-xs text-[#0069FF]">{{ $i + 1 }}</span><p class="mt-4 leading-6">{{ $step }}</p></div>@endforeach</div>
                    <p class="mt-6 text-sm leading-8 text-slate-600">ساخت و حذف فوری نیستند. پس از ارسال درخواست، از endpoint جزئیات استفاده کنید و مقدار <code dir="ltr">provisioning_status</code> یا <code dir="ltr">status</code> را تا پایان عملیات بررسی کنید.</p>
                </section>

                <section id="authentication" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">۰۱ · دسترسی</p><h2 class="mt-2 text-2xl font-black text-slate-950">ساخت کلید API و انتخاب دسترسی‌ها</h2><p class="mt-4 text-sm leading-8 text-slate-600">برای ساخت کلید، ابتدا وارد پنل مشتری شوید. لینک «پروفایل» در بالای سمت چپ صفحه قرار دارد؛ روی آن کلیک کنید، وارد صفحه پروفایل شوید و در بخش «دسترسی API» نام کلید را وارد کنید. دسترسی‌های مورد نیاز را انتخاب کنید و روی «ساخت کلید API» بزنید. مقدار کلید فقط همان لحظه نمایش داده می‌شود، پس آن را در محل امن ذخیره کنید.</p><div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-7 text-blue-950"><strong>هدر هر درخواست:</strong> کلید را با فرمت <code dir="ltr">Authorization: Bearer YOUR_API_KEY</code> ارسال کنید. کلید را داخل کد سمت کاربر، Git یا لاگ‌ها قرار ندهید.</div><div class="mt-5 grid gap-3 sm:grid-cols-3 text-sm"><div class="rounded-xl bg-slate-50 p-4"><code dir="ltr" class="font-black">vm:read</code><p class="mt-2 text-slate-600">خواندن options، list و detail</p></div><div class="rounded-xl bg-slate-50 p-4"><code dir="ltr" class="font-black">vm:create</code><p class="mt-2 text-slate-600">ارسال درخواست ساخت VM</p></div><div class="rounded-xl bg-slate-50 p-4"><code dir="ltr" class="font-black">vm:delete</code><p class="mt-2 text-slate-600">ارسال درخواست حذف VM</p></div></div><p class="mt-5 text-sm leading-8 text-slate-600">برای خواندن، فقط <code dir="ltr">vm:read</code> کافی است. برای ساخت باید <code dir="ltr">vm:create</code> و برای حذف باید <code dir="ltr">vm:delete</code> روی همان کلید فعال باشد. این دسترسی‌ها مستقل‌اند و یک کلید فقط به endpointهایی دسترسی دارد که ability آن را دارد.</p></section>

                <section id="options" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="GET" path="/projects/{project_uuid}/virtual-machines/options" ability="vm:read" /><h2 class="mt-5 text-xl font-black text-slate-950">اول گزینه‌های قابل استفاده را دریافت کنید</h2><p class="mt-3 text-sm leading-8 text-slate-600">قبل از ساخت VM این endpoint را صدا بزنید. از پاسخ آن، ID مربوط به location، image و bundle را انتخاب کنید؛ این IDها را دستی حدس نزنید یا از یک پروژه دیگر کپی نکنید. پاسخ شامل locationهای فعال، imageهای فعال، نسخه‌های سیستم‌عامل، bundleهای مجاز، منابع، قیمت، ظرفیت IP، موجودی کیف پول و وضعیت quota است.</p><p class="mt-3 text-sm leading-8 text-slate-600">برای انتخاب معتبر، ابتدا image را انتخاب کنید، سپس یکی از locationهایی را بردارید که در <code dir="ltr">location_ids</code> آن image آمده است. اگر bundle استفاده می‌کنید، ID آن باید در <code dir="ltr">allowed_bundle_ids</code> image باشد. اطلاعات محرمانه زیرساخت در پاسخ نمایش داده نمی‌شود.</p><x-api-code-samples key="options" :curl="$curlOptions" :php="$phpOptions" :javascript="$jsOptions" /></section>

                <section id="projects" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">۰۲ · پروژه</p><h2 class="mt-2 text-2xl font-black text-slate-950">پیدا کردن UUID پروژه</h2><p class="mt-4 text-sm leading-8 text-slate-600">درخواست‌های VM با UUID پروژه ارسال می‌شوند، نه با نام پروژه. وارد پنل مشتری شوید و پروژه مورد نظر را باز کنید؛ UUID واقعی پروژه را از آدرس صفحه یا بخش جزئیات همان پروژه بردارید و جای <code dir="ltr">YOUR_PROJECT_UUID</code> قرار دهید.</p><p class="mt-3 text-sm leading-8 text-slate-600">اگر کلید متعلق به مشتری دیگری باشد، عضو پروژه نباشد، یا ability لازم را نداشته باشد، API دسترسی را رد می‌کند. تغییر UUID در URL باعث دیده شدن VMهای پروژه دیگر نمی‌شود.</p></section>

                <section id="create-vm" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="POST" path="/projects/{project_uuid}/virtual-machines" ability="vm:create" /><h2 class="mt-5 text-xl font-black text-slate-950">ساخت VM</h2><p class="mt-3 text-sm leading-8 text-slate-600">پس از دریافت options، IDهای انتخاب‌شده را در این درخواست قرار دهید. <code dir="ltr">cloud_image_id</code> الزامی است. اگر <code dir="ltr">vm_bundle_id</code> بفرستید، CPU، RAM و disk از همان bundle خوانده می‌شود. اگر bundle نمی‌فرستید، هر سه <code dir="ltr">cpu_cores</code>، <code dir="ltr">ram_gb</code> و <code dir="ltr">disk_gb</code> الزامی‌اند.</p><p class="mt-3 text-sm leading-8 text-slate-600">نام نمایشی، username و SSH key اختیاری‌اند. سرویس quota، موجودی و قفل بودن wallet، سازگاری image/location/bundle، حداقل منابع و ظرفیت IP را بررسی می‌کند. پاسخ موفق با <code dir="ltr">201 Created</code> برمی‌گردد و VM ابتدا در وضعیت pending است.</p><div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">اگر رمز ورود به صورت خودکار تولید شود، فقط یک‌بار در <code dir="ltr">generated_login_password</code> پاسخ create قرار می‌گیرد. آن را همان لحظه ذخیره کنید؛ در list و detail قابل بازیابی نیست.</div><x-api-code-samples key="create" :curl="$curlCreate" :php="$phpCreate" :javascript="$jsCreate" /></section>

                <section id="list-vms" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="GET" path="/projects/{project_uuid}/virtual-machines" ability="vm:read" /><h2 class="mt-5 text-xl font-black text-slate-950">دیدن VMهای پروژه</h2><p class="mt-3 text-sm leading-8 text-slate-600">برای نمایش فهرست VMها، این endpoint را صدا بزنید. نتیجه صفحه‌بندی می‌شود؛ <code dir="ltr">per_page</code> تعداد رکوردهای هر صفحه را مشخص می‌کند، <code dir="ltr">search</code> در نام، hostname یا IP جست‌وجو می‌کند و <code dir="ltr">status</code> می‌تواند running، stopped، suspended یا deleting باشد. VMهای deleted به صورت پیش‌فرض برگردانده نمی‌شوند.</p><x-api-code-samples key="list" :curl="$curlList" :php="$phpList" :javascript="$jsList" /></section>

                <section id="get-vm" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="GET" path="/projects/{project_uuid}/virtual-machines/{virtual_machine_uuid}" ability="vm:read" /><h2 class="mt-5 text-xl font-black text-slate-950">خواندن وضعیت و اطلاعات اتصال</h2><p class="mt-3 text-sm leading-8 text-slate-600">بعد از create، UUID VM را از پاسخ بگیرید و این endpoint را با همان UUID بخوانید. تا زمانی که <code dir="ltr">provisioning_status</code> برابر pending است، چند ثانیه صبر کنید و دوباره درخواست بفرستید. وقتی <code dir="ltr">ssh_ready</code> برابر true شد، می‌توانید از username و <code dir="ltr">ssh_command</code> استفاده کنید. رمز عبور در این endpoint نمایش داده نمی‌شود.</p><x-api-code-samples key="detail" :curl="$curlDetail" :php="$phpDetail" :javascript="$jsDetail" /></section>

                <section id="delete-vm" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><x-api-endpoint method="DELETE" path="/projects/{project_uuid}/virtual-machines/{virtual_machine_uuid}" ability="vm:delete" /><h2 class="mt-5 text-xl font-black text-slate-950">حذف VM</h2><p class="mt-3 text-sm leading-8 text-slate-600">برای جلوگیری از حذف اشتباه، مقدار <code dir="ltr">confirmation</code> در بدنه درخواست باید دقیقاً با <code dir="ltr">display_name</code> VM یکسان باشد. بعد از پذیرش درخواست، وضعیت VM فوراً deleting می‌شود و حذف زیرساخت در پس‌زمینه انجام می‌شود. تا پایان کار، detail را دوباره بخوانید. درخواست تکراری همان VM نتیجه‌ای امن و idempotent دارد.</p><x-api-code-samples key="delete" :curl="$curlDelete" :php="$phpDelete" :javascript="$jsDelete" /></section>

                <section id="states" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><p class="text-xs font-black tracking-[.14em] text-[#0069FF]">۰۳ · وضعیت‌ها</p><h2 class="mt-2 text-2xl font-black text-slate-950">کدام وضعیت را باید منتظر بمانید؟</h2><div class="mt-5 grid gap-3 sm:grid-cols-2"><div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm"><code dir="ltr" class="font-black text-amber-800">pending</code><p class="mt-2 text-slate-600">درخواست ثبت شده است. چند ثانیه بعد detail را دوباره بخوانید.</p></div><div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm"><code dir="ltr" class="font-black text-emerald-800">ready</code><p class="mt-2 text-slate-600">آماده استفاده است؛ اگر IP موجود باشد SSH هم آماده است.</p></div><div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm"><code dir="ltr" class="font-black text-rose-800">failed</code><p class="mt-2 text-slate-600">آماده‌سازی موفق نشده است. مقدار failure را بخوانید و برای پشتیبانی request ID را نگه دارید.</p></div><div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm"><code dir="ltr" class="font-black text-slate-800">deleting / deleted</code><p class="mt-2 text-slate-600">حذف در حال انجام است یا رکورد به طور کامل حذف شده است.</p></div></div></section>

                <section id="credentials" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">رمز عبور و SSH</h2><p class="mt-4 text-sm leading-8 text-slate-600">اگر در create رمز عبور ارسال نکنید، سرویس ممکن است یک رمز تولید کند و آن را فقط در همان پاسخ با نام <code dir="ltr">generated_login_password</code> برگرداند. آن را همان لحظه در محل امن ذخیره کنید؛ مسیر بازیابی رمز وجود ندارد و detail آن را نشان نمی‌دهد.</p><p class="mt-3 text-sm leading-8 text-slate-600">برای اتصال، تا آماده شدن VM صبر کنید. وقتی <code dir="ltr">ssh_ready</code> برابر true شد، مقدار <code dir="ltr">login_username</code> و <code dir="ltr">ssh_command</code> را از detail بخوانید.</p></section>

                <section id="fields" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">فیلدهای ساخت و زمان استفاده از آن‌ها</h2><div class="mt-5 overflow-x-auto"><table class="min-w-full text-right text-sm"><thead class="border-b border-slate-200 text-xs font-black text-slate-500"><tr><th class="px-3 py-3">فیلد</th><th class="px-3 py-3">چه زمانی لازم است؟</th></tr></thead><tbody class="divide-y divide-slate-100 text-slate-600"><tr><td class="px-3 py-3 font-mono text-slate-900" dir="ltr">cloud_image_id</td><td class="px-3 py-3">همیشه؛ از options انتخاب کنید.</td></tr><tr><td class="px-3 py-3 font-mono text-slate-900" dir="ltr">infrastructure_location_id</td><td class="px-3 py-3">برای انتخاب location مشخص؛ باید با image سازگار باشد.</td></tr><tr><td class="px-3 py-3 font-mono text-slate-900" dir="ltr">vm_bundle_id</td><td class="px-3 py-3">برای استفاده از bundle آماده و منابع/قیمت آن.</td></tr><tr><td class="px-3 py-3 font-mono text-slate-900" dir="ltr">cpu_cores, ram_gb, disk_gb</td><td class="px-3 py-3">فقط وقتی bundle نمی‌فرستید؛ هر سه لازم‌اند.</td></tr><tr><td class="px-3 py-3 font-mono text-slate-900" dir="ltr">display_name, login_username, ssh_public_key</td><td class="px-3 py-3">اختیاری؛ برای نام و روش اتصال دلخواه.</td></tr></tbody></table></div></section>

                <section id="errors" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">اگر درخواست خطا داد</h2><p class="mt-4 text-sm leading-8 text-slate-600"><code dir="ltr">401</code> یعنی توکن وجود ندارد یا معتبر نیست. <code dir="ltr">403</code> یعنی ability یا دسترسی پروژه کافی نیست. <code dir="ltr">404</code> یعنی project یا VM در scope این کلید نیست. <code dir="ltr">422</code> یعنی فیلدها، سازگاری، quota، wallet یا ظرفیت IP مشکل دارد و <code dir="ltr">429</code> یعنی باید بعداً دوباره درخواست کنید. در همه حالت‌ها <code dir="ltr">meta.request_id</code> را برای پشتیبانی نگه دارید.</p><pre class="mt-5 overflow-x-auto rounded-xl bg-red-50 p-4 text-xs leading-7 text-red-900" dir="ltr"><code>{ "error": { "code": "validation_error", "fields": {} }, "meta": { "request_id": "REQUEST_ID" } }</code></pre></section>

                <section id="security" class="scroll-mt-28 rounded-2xl border border-blue-100 bg-[#EEF5FF] p-6 md:p-8"><h2 class="text-2xl font-black text-slate-950">نکات امنیتی هنگام استفاده</h2><p class="mt-4 text-sm leading-8 text-blue-950">کلید را در Git، log، مرورگر یا کد frontend نگذارید. برای هر سرویس و محیط یک کلید جدا بسازید، فقط ability لازم را فعال کنید و در صورت افشا از صفحه پروفایل آن را لغو کنید. رمز اولیه را فقط در secret manager ذخیره کنید.</p></section>

                <section id="wallet-reference" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-6 md:p-8"><h2 class="text-xl font-black text-slate-950">Wallet API برای integrationهای موجود</h2><p class="mt-3 text-sm leading-8 text-slate-600">endpointهای کیف پول برای سازگاری همچنان در دسترس‌اند و به <code dir="ltr">wallet:read</code> نیاز دارند:</p><div class="mt-4 grid gap-2 text-sm font-bold"><span dir="ltr">/projects/{project_uuid}/wallet</span><span dir="ltr">/projects/{project_uuid}/wallet/transactions</span><span dir="ltr">/projects/{project_uuid}/wallet/transactions/{transaction}</span></div><div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-500"><span>Get remaining balance</span><span>List transactions</span><span>Get one transaction</span></div></section>
            </div>
        </div>
    </div>
</div>
@endsection
