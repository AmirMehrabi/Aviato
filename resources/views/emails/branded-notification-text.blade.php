{{ $notification['subject'] }}

{{ $notification['body'] }}
@if ($event === 'server_ready')
نام سرور: {{ $variables['server_name'] ?? '' }}
نشانی IP: {{ $variables['server_ip'] ?? '' }}
نام کاربری: {{ $variables['username'] ?? '' }}
رمز عبور اولیه: {{ $variables['initial_password'] ?? 'در دسترس نیست' }}
{{ $variables['password_notice'] ?? '' }}
لطفاً پس از اولین ورود به سرور، رمز عبور را تغییر دهید. اطلاعات ورود را با دیگران به اشتراک نگذارید.
@endif
@if ($code)

{{ $code }}
@endif
@if ($actionUrl)

{{ $actionUrl }}
@endif

با مهر، تیم آویاتو
{{ $branding['tagline'] }}
{{ $branding['website'] }}
