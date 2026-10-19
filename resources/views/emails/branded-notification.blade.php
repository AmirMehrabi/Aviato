<!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $notification['subject'] }}</title></head>
<body style="margin:0;padding:24px 12px;background:#f3f6fb;color:#031b4e;font-family:Tahoma,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:auto;background:#fff;border:1px solid #dce5f2;border-radius:16px;">
    <tr><td style="padding:28px;border-bottom:3px solid #0069ff;"><a href="{{ $branding['website'] }}"><img src="{{ $branding['logo'] }}" width="150" alt="آویاتو | Aviato" style="display:block;max-width:150px;height:auto;border:0;"></a><p style="font-size:12px;color:#64748b;">{{ $branding['tagline'] }}</p></td></tr>
    <tr><td style="padding:28px;"><h1 style="font-size:21px;line-height:1.8;margin:0 0 20px;">{{ $notification['subject'] }}</h1>
        @foreach (explode("\n", $notification['body']) as $line)
            <p style="font-size:14px;line-height:2;margin:0 0 12px;">{{ $line }}</p>
        @endforeach
        @if ($code)
            <p dir="ltr" style="padding:18px;background:#ebf3ff;border-radius:10px;text-align:center;color:#0069ff;font-size:34px;font-weight:bold;letter-spacing:8px;">{{ $code }}</p>
        @endif
        @if ($actionUrl)
            <p style="margin:24px 0;"><a href="{{ $actionUrl }}" style="display:inline-block;background:#0069ff;color:#fff;padding:13px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">مشاهده در پنل آویاتو</a></p>
        @endif
    </td></tr>
    <tr><td style="padding:22px 28px;background:#f8faff;border-top:1px solid #dce5f2;font-size:12px;line-height:2;color:#64748b;">با مهر، تیم آویاتو<br><a dir="ltr" href="{{ $branding['website'] }}" style="color:#0069ff;">{{ $branding['website'] }}</a><br>این پیام به‌صورت خودکار ارسال شده است؛ برای ارتباط با ما از بخش پشتیبانی پنل استفاده کنید.</td></tr>
</table>
</body></html>
