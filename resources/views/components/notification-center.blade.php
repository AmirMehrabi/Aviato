@props([
    'feedUrl',
    'markReadUrlTemplate',
    'markAllReadUrl',
    'unreadCount' => 0,
    'variant' => 'square',
])

<div
    class="notification-center relative"
    x-data="notificationCenter({
        feedUrl: @js($feedUrl),
        markReadUrlTemplate: @js($markReadUrlTemplate),
        markAllReadUrl: @js($markAllReadUrl),
        unreadCount: {{ (int) $unreadCount }},
        csrf: @js(csrf_token()),
    })"
    @keydown.escape.window="if (open) close(true)"
    @click.outside="close()"
>
    <button
        x-ref="trigger"
        type="button"
        @click="toggle()"
        class="relative grid size-11 place-items-center border border-slate-200 bg-white text-slate-600 shadow-sm shadow-slate-200/50 transition-colors duration-150 hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] active:scale-[0.96] {{ $variant === 'round' ? 'rounded-full' : 'rounded-xl' }}"
        aria-label="اعلان‌ها"
        :aria-expanded="open.toString()"
        aria-controls="notification-center-panel"
    >
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span
            x-cloak
            x-show="unreadCount > 0"
            x-text="unreadCount > 99 ? '۹۹+' : unreadCount.toLocaleString('fa-IR')"
            class="absolute -start-1.5 -top-1.5 grid min-h-5 min-w-5 place-items-center rounded-full bg-[#0069FF] px-1 text-[10px] font-black leading-none text-white ring-2 ring-white"
        ></span>
    </button>

    <section
        id="notification-center-panel"
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-out duration-100"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-1 opacity-0"
        class="absolute end-0 top-14 z-50 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white text-right shadow-2xl shadow-slate-950/15"
        aria-labelledby="notification-center-title"
        :aria-busy="loading.toString()"
    >
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5">
            <div>
                <h2 id="notification-center-title" class="text-sm font-black text-slate-950">اعلان‌ها</h2>
                <p class="mt-0.5 text-xs font-bold text-slate-400" x-text="unreadCount ? `${unreadCount.toLocaleString('fa-IR')} اعلان خوانده‌نشده` : 'همه اعلان‌ها خوانده شده‌اند'"></p>
            </div>
            <button
                type="button"
                x-show="unreadCount > 0"
                :disabled="markingAll"
                @click="markAllRead()"
                class="min-h-10 rounded-xl px-3 text-xs font-black text-[#0069FF] transition-colors duration-150 hover:bg-[#EBF3FF] disabled:cursor-wait disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
                x-text="markingAll ? 'در حال خواندن…' : 'خواندن همه'"
            ></button>
        </div>

        <div class="max-h-[min(28rem,70vh)] overflow-y-auto p-2">
            <template x-if="loading && items.length === 0">
                <div class="space-y-2 p-2" aria-hidden="true">
                    <template x-for="index in 3" :key="index">
                        <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
                    </template>
                </div>
            </template>

            <template x-if="!loading && items.length === 0 && !error">
                <div class="px-5 py-10 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <p class="mt-3 text-sm font-black text-slate-800">اعلانی ندارید</p>
                    <p class="mt-1 text-xs font-bold leading-6 text-slate-500">پاسخ‌های جدید پشتیبانی و تغییرات تیکت‌ها اینجا نمایش داده می‌شوند.</p>
                </div>
            </template>

            <template x-for="notification in items" :key="notification.id">
                <article class="group rounded-xl p-3 transition-colors duration-150 hover:bg-slate-50" :class="notification.read ? '' : 'bg-[#F5F9FF]'">
                    <div class="flex items-start gap-3">
                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :class="notification.read ? 'bg-slate-300' : 'bg-[#0069FF]'" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <a
                                :href="notification.url"
                                @click="openNotification($event, notification)"
                                class="block rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
                            >
                                <span class="block text-sm font-black leading-6 text-slate-900" x-text="notification.title"></span>
                                <span class="mt-0.5 block text-xs font-bold leading-6 text-slate-500" x-text="notification.body"></span>
                            </a>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <span class="text-[11px] font-bold text-slate-400" x-text="formatDate(notification.created_at)"></span>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        x-show="!notification.read"
                                        :disabled="Boolean(markingIds[notification.id])"
                                        @click="markRead(notification.id)"
                                        class="min-h-9 rounded-lg px-2.5 text-[11px] font-black text-slate-600 transition-colors duration-150 hover:bg-white hover:text-[#0069FF] disabled:cursor-wait disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]"
                                    >خواندن</button>
                                    <a :href="notification.url" class="inline-flex min-h-9 items-center rounded-lg px-2.5 text-[11px] font-black text-[#0069FF] transition-colors duration-150 hover:bg-[#EBF3FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده تیکت</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </template>
        </div>

        <div x-show="error" class="border-t border-red-100 bg-red-50 px-4 py-3 text-xs font-bold leading-6 text-red-700">
            <span x-text="error"></span>
            <button type="button" @click="load()" class="mr-2 font-black underline decoration-2 underline-offset-4">تلاش دوباره</button>
        </div>
        <p class="sr-only" role="status" aria-live="polite" x-text="announcement"></p>
    </section>
</div>
