@if($customer->status === 'suspended')

@adminRoute('admin.customers.activate')
<form method="POST" action="{{ route('admin.customers.activate', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-[#B8D6FF] px-5 py-3 text-sm font-semibold text-[#031B4E]">فعال‌سازی</button></form>
@endadminRoute

                @else

@adminRoute('admin.customers.suspend')
<form method="POST" action="{{ route('admin.customers.suspend', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-red-400 px-5 py-3 text-sm font-semibold text-red-950">تعلیق مشتری</button></form>
@endadminRoute

                @endif
