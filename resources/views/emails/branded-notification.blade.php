<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification['subject'] }}</title>
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            font-style: normal;
            font-weight: 400;
            src: url('{{ asset('assets/fonts/webfonts/Vazirmatn-Regular.woff2') }}') format('woff2');
        }
        @font-face {
            font-family: 'Vazirmatn';
            font-style: normal;
            font-weight: 700;
            src: url('{{ asset('assets/fonts/webfonts/Vazirmatn-Bold.woff2') }}') format('woff2');
        }
    </style>
</head>
<body dir="rtl" style="margin:0;padding:24px 12px;background:#f3f6fb;color:#031b4e;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;direction:rtl;text-align:right;">
<table dir="rtl" align="center" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;max-width:600px;margin:auto;background:#fff;border:1px solid #dce5f2;border-radius:16px;">
    <tr><td dir="rtl" align="right" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;padding:28px;border-bottom:3px solid #0069ff;"><a href="{{ $branding['website'] }}"><img src="{{ $branding['logo'] }}" width="150" alt="آویاتو | Aviato" style="display:block;max-width:150px;height:auto;border:0;"></a><p dir="rtl" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;font-size:12px;color:#64748b;">{{ $branding['tagline'] }}</p></td></tr>
    <tr><td dir="rtl" align="right" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;padding:28px;"><h1 dir="rtl" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;font-size:21px;line-height:1.8;margin:0 0 20px;">{{ $notification['subject'] }}</h1>
        @foreach (explode("\n", $notification['body']) as $line)
            <p dir="rtl" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;font-size:14px;line-height:2;margin:0 0 12px;">{{ $line }}</p>
        @endforeach
        @if ($code)
            <p dir="ltr" style="direction:ltr;unicode-bidi:isolate;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;padding:18px;background:#ebf3ff;border-radius:10px;text-align:center;color:#0069ff;font-size:34px;font-weight:bold;letter-spacing:8px;">{{ $code }}</p>
        @endif
        @if ($actionUrl)
            <p dir="rtl" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;margin:24px 0;"><a href="{{ $actionUrl }}" style="direction:rtl;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;display:inline-block;background:#0069ff;color:#fff;padding:13px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">مشاهده در پنل آویاتو</a></p>
        @endif
    </td></tr>
    <tr><td dir="rtl" align="right" style="direction:rtl;text-align:right;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;padding:22px 28px;background:#f8faff;border-top:1px solid #dce5f2;font-size:12px;line-height:2;color:#64748b;">با مهر، تیم آویاتو<br><a dir="ltr" href="{{ $branding['website'] }}" style="display:inline-block;direction:ltr;unicode-bidi:isolate;font-family:'Vazirmatn',Tahoma,Arial,sans-serif;color:#0069ff;">{{ $branding['website'] }}</a><br>این پیام به‌صورت خودکار ارسال شده است؛ برای ارتباط با ما از بخش پشتیبانی پنل استفاده کنید.</td></tr>
</table>
</body></html>
