<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>کد بازیابی رمز عبور</title>
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#eaf4ff 0%,#ffffff 100%);">
                <p style="margin:0 0 10px 0;font-size:12px;font-weight:700;color:#2563eb;">بازیابی رمز عبور</p>
                <h1 style="margin:0;font-size:24px;line-height:1.4;color:#0f172a;">کد OTP تغییر رمز پنل مشتری</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 16px 0;font-size:14px;line-height:1.9;color:#334155;">{{ $customer->name }} عزیز، برای انتخاب رمز عبور جدید این کد را وارد کنید:</p>
                <p dir="ltr" style="margin:0 0 16px 0;font-size:34px;letter-spacing:8px;font-weight:700;color:#2563eb;text-align:center;">{{ $code }}</p>
                <p style="margin:0;font-size:13px;line-height:1.9;color:#64748b;">این کد تا ۱۰ دقیقه معتبر است. اگر درخواست بازیابی رمز نداده اید، این ایمیل را نادیده بگیرید.</p>
            </td>
        </tr>
    </table>
</body>
</html>
