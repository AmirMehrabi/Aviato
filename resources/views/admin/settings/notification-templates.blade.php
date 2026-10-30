@extends('layouts.admin')
@section('title', 'قالب‌ها و اعلان‌ها')
@section('content')
<div class="space-y-6 px-4 py-6 md:px-8">
    <a href="{{ route('admin.settings.edit') }}" class="text-sm font-bold text-blue-600">→ بازگشت به تنظیمات</a>
    <h1 class="text-2xl font-black text-slate-950">قالب‌ها و اعلان‌ها</h1>
    <p class="text-sm leading-7 text-slate-500">برای هر رویداد، کانال ارسال و متن پیشنهادی را مدیریت کنید. کدهای امنیتی همیشه ارسال می‌شوند. اعلان‌های ایمیلی تیکت به فعال بودن ایمیل در تنظیمات تیکت هم نیاز دارند. تمام ایمیل‌های مشتریان در پس‌زمینه ارسال می‌شوند؛ اختلال SMTP عملیات پنل را متوقف نمی‌کند.</p>
    @if(session('status'))<div role="status" class="rounded-lg bg-blue-50 p-4 text-blue-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('admin.settings.notifications.branding') }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
        @csrf @method('PATCH')
        <h2 class="text-lg font-black">هویت ایمیل‌های آویاتو</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <x-form.input name="website" type="url" label="نشانی وب‌سایت" :value="$branding['website']" dir-ltr required />
            <x-form.input name="logo" type="url" label="نشانی عمومی لوگو" :value="$branding['logo']" dir-ltr required help="نشانی قابل دسترس با HTTPS؛ برای سازگاری بیشتر با ایمیل از PNG استفاده کنید." />
            <x-form.input name="tagline" label="شعار آویاتو" :value="$branding['tagline']" required />
        </div>
        <button class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-bold text-white">ذخیره هویت ایمیل</button>
    </form>
    <p class="rounded-lg bg-slate-100 p-4 text-sm leading-7">متغیرهای مجاز: <span dir="ltr">@{{machine_id}}, @{{creator_name}}, @{{name}}, @{{title}}, @{{body}}, @{{ticket_number}}, @{{server_name}}, @{{server_ip}}, @{{username}}, @{{amount}}, @{{receipt}}, @{{time}}, @{{ip}}</span>. متن ساده بنویسید؛ HTML و کد اجرا نمی‌شوند. کد امنیتی و اطلاعات ورود اولیه سرور به‌طور خودکار و جدا از متن ایمیل درج می‌شوند. رمز سرور در اعلان داخل پنل نمایش داده نمی‌شود. متغیرهای هر رویداد را در متن پیشنهادی آن ببینید.</p>
    @foreach($templates as $event => $template)
        <details id="{{ $event }}" class="rounded-2xl border border-slate-200 bg-white p-5" @if(old('event') === $event) open @endif>
            <summary class="cursor-pointer font-black">{{ $template['label'] }} <span class="text-xs font-normal text-slate-500">{{ $template['required'] ? 'پیام ضروری' : ($template['email_enabled'] ? 'ایمیل فعال' : 'ایمیل غیرفعال') }}</span></summary>
            <form method="POST" action="{{ route('admin.settings.notifications.update') }}" class="mt-5 space-y-4">
                @csrf @method('PATCH')
                <input type="hidden" name="event" value="{{ $event }}">
                <label class="block text-sm font-bold">موضوع ایمیل<input name="subject" value="{{ old('event') === $event ? old('subject') : $template['subject'] }}" required maxlength="200" class="mt-2 w-full rounded-lg border border-slate-200 p-3"></label>
                <label class="block text-sm font-bold">متن پیام<textarea name="body" rows="6" required maxlength="10000" class="mt-2 w-full rounded-lg border border-slate-200 p-3">{{ old('event') === $event ? old('body') : $template['body'] }}</textarea></label>
                <div class="flex gap-6">
                    <input type="hidden" name="email_enabled" value="0">
                    <label><input type="checkbox" name="email_enabled" value="1" @checked($template['email_enabled']) @disabled($template['required'])> ایمیل</label>
                    @if($template['required'])<input type="hidden" name="email_enabled" value="1">@endif
                    <input type="hidden" name="in_app_enabled" value="0">
                    @unless($template['required'])<label><input type="checkbox" name="in_app_enabled" value="1" @checked($template['in_app_enabled'])> اعلان داخل پنل</label>@endunless
                </div>
                @if(isset($template['sms_template']))
                    <input type="hidden" name="sms_enabled" value="0">
                    <label><input type="checkbox" name="sms_enabled" value="1" @checked($template['sms_enabled'])> پیامک Kavenegar</label>
                    <x-form.input name="sms_template" label="نام قالب Kavenegar" :value="$template['sms_template']" dir-ltr required />
                    <p class="text-sm text-slate-500">متغیر %token شناسه ماشین است. ابتدا قالب را در Kavenegar ایجاد و تأیید کنید.</p>
                @endif
                <div class="flex flex-wrap gap-3">
                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white">ذخیره قالب</button>
                    <button name="restore" value="1" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">بازگردانی متن و تنظیمات پیشنهادی</button>
                    <a href="{{ route('admin.settings.notifications.preview', $event) }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">پیش‌نمایش نسخه ذخیره‌شده</a>
                </div>
                <details class="text-sm text-slate-500"><summary class="cursor-pointer">مشاهده متن پیشنهادی</summary><p class="mt-2 whitespace-pre-line">{{ $defaults[$event]['subject'] }}<br>{{ $defaults[$event]['body'] }}</p></details>
            </form>
            <form method="POST" action="{{ route('admin.settings.notifications.test', $event) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4">
                @csrf
                <x-form.input name="test_email" type="email" label="گیرنده نمونه (از قالب ذخیره‌شده)" :value="auth('admin')->user()->email" dir-ltr required />
                <button class="rounded-lg border border-blue-200 px-4 py-2 text-sm font-bold text-blue-600">ارسال نمونه</button>
            </form>
        </details>
    @endforeach
    <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-lg font-black">تاریخچه ارسال ایمیل‌ها</h2>
        <p class="mb-4 text-xs text-slate-500">«تحویل به سرور» به معنی تأیید دریافت در صندوق مشتری نیست. ارسال پیام‌های امنیتی نیز ثبت می‌شود؛ کدها و رمزها در این فهرست ذخیره نمی‌شوند. ایمیل‌های نمونه جدا هستند.</p>
        <table class="w-full text-right text-sm"><thead><tr><th class="p-2">رویداد</th><th class="p-2">گیرنده</th><th class="p-2">وضعیت</th><th class="p-2">تلاش‌ها</th><th class="p-2">زمان</th></tr></thead><tbody>
        @forelse($deliveries as $delivery)<tr class="border-t border-slate-100"><td class="p-2">{{ $templates[$delivery->event]['label'] ?? $delivery->event }}</td><td dir="ltr" class="p-2">{{ $delivery->recipient }}</td><td class="p-2">{{ ['queued' => 'در صف', 'sent' => 'تحویل به سرور', 'failed' => 'ناموفق', 'suppressed' => 'لغوشده طبق تنظیمات'][$delivery->status] ?? $delivery->status }}</td><td class="p-2">{{ $delivery->attempts }}</td><td class="p-2">{{ $delivery->created_at }}</td></tr>@empty<tr><td colspan="5" class="p-4 text-slate-500">هنوز ارسالی ثبت نشده است.</td></tr>@endforelse
        </tbody></table>
        <div class="mt-4">{{ $deliveries->links() }}</div>
    </section>
</div>
@endsection
