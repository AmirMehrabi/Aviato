@adminRoute('admin.customers.sms-notifications.update')
<form method="POST" action="{{ route('admin.customers.sms-notifications.update', $customer) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="sms_notifications_enabled" value="{{ $customer->sms_notifications_enabled ? 0 : 1 }}">
                    <button class="rounded-lg bg-white px-5 py-3 text-sm font-semibold text-[#031B4E] transition hover:bg-slate-100">
                        {{ $customer->sms_notifications_enabled ? 'غیرفعال‌سازی پیامک' : 'فعال‌سازی پیامک' }}
                    </button>
                </form>
@endadminRoute
