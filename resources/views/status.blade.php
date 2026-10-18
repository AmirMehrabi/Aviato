@extends('layouts.status')

@section('content')
    <div class="page-intro">
        <div class="eyebrow">وضعیت سرویس‌ها </div>
        <h1>با خیال راحت، در جریان باشید.</h1>
        <p>وضعیت لحظه‌ای سرویس‌ها و تاریخچه پایداری زیرساخت آویاتو.</p>
    </div>

    <section class="overall-status overall-{{ $overall }}" aria-labelledby="overall-heading">
        <span class="status-symbol" aria-hidden="true">{{ $overall === 'healthy' ? '✓' : '!' }}</span>
        <div><h2 id="overall-heading">{{ match ($overall) { 'danger' => 'برخی سرویس‌ها با اختلال مواجه هستند', 'warning' => 'برخی سرویس‌ها با اختلال جزئی مواجه هستند', default => 'همه سرویس‌ها فعال هستند' } }}</h2><p>{{ $overall === 'healthy' ? 'در حال حاضر اختلالی در سرویس‌ها گزارش نشده است.' : 'تیم فنی در حال پیگیری است. جزئیات را در تاریخچه رخدادها بخوانید.' }}</p></div>
        <span class="live-label"><i class="dot {{ $overall }}"></i> به‌روزرسانی {{ jdf($now, 'H:i') }}</span>
    </section>

    <section class="availability" aria-labelledby="availability-heading">
        <div class="section-heading"><div><h2 id="availability-heading">پایداری سرویس‌ها</h2><p>سه ماه اخیر · {{ jdf($start, 'j F') }} تا {{ jdf($today, 'j F Y') }}</p></div><span class="period-label">۳ ماه اخیر</span></div>
        <div class="legend"><span><i class="dot healthy"></i> بدون اختلال</span><span><i class="dot warning"></i> اختلال جزئی</span><span><i class="dot danger"></i> اختلال جدی</span></div>
        <p class="timeline-hint">برای مشاهده جزئیات، روی هر روز مکث کنید یا آن را لمس کنید.</p>
        <div class="service-list">
            @foreach($services as $service)
                <article class="service-row">
                    <div class="service-heading"><div><h3>{{ $service['name'] }}</h3><p>{{ $service['description'] }}</p></div><div class="service-summary"><span class="operational state-{{ $service['severity'] }}"><i class="dot {{ $service['severity'] }}"></i> {{ match ($service['severity']) { 'danger' => 'اختلال جدی', 'warning' => 'اختلال جزئی', default => 'فعال' } }}</span><span class="uptime"><b dir="ltr">{{ $service['uptime'] }}%</b> پایداری</span></div></div>
                    <div class="month-grid">
                        @foreach($months as $month)
                            <div class="month" style="--days: {{ count($month['days']) }}">
                                <div class="day-bars">
                                    @foreach($month['days'] as $day)
                                        @php
                                            $events = $day['incidents']->where('service', $service['id']);
                                            $severity = $events->contains('severity', 'danger') ? 'danger' : ($events->isNotEmpty() ? 'warning' : 'healthy');
                                            $detail = $events->isEmpty() ? 'بدون رخداد؛ اختلالی برای این روز گزارش نشده است.' : $events->map(fn ($event) => $event['title'].' · مدت کل: '.$event['minutes'].' دقیقه · '.$event['status'])->implode(' / ');
                                        @endphp
                                        <button type="button" class="day-bar {{ $severity }}" data-date="{{ $day['date'] }}" data-tooltip-date="{{ $day['label'] }}" data-tooltip-detail="{{ $detail }}" aria-label="{{ $day['label'] }}؛ {{ $service['name'] }}؛ {{ $detail }}" aria-describedby="day-tooltip"></button>
                                    @endforeach
                                </div>
                                <span class="month-label">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
        <div class="timeline-caption"><span>هر نوار، یک روز</span><span>امروز: {{ jdf($today, 'j F Y') }}</span></div>
    </section>

    <section class="history" aria-labelledby="history-heading">
        <div class="section-heading"><div><h2 id="history-heading">تاریخچه رخدادها</h2><p>آنچه اتفاق افتاد و چگونه برطرف شد.</p></div><span class="history-count">{{ $incidents->count() }} رخداد</span></div>
        <div class="incident-timeline">
            @forelse($incidents as $incident)
                <article class="incident">
                    <span class="timeline-dot {{ $incident['severity'] }}" aria-hidden="true"></span>
                    <div class="incident-date">{{ jdf($incident['date'], 'j F Y') }} <span>· {{ jdf($incident['date'], 'H:i') }}</span></div>
                    <div class="incident-title"><h3><a href="{{ $incident['url'] }}">{{ $incident['title'] }}</a></h3><span class="{{ $incident['active'] ? 'unresolved' : 'resolved' }}">{{ $incident['status'] }}</span></div>
                    <p>{{ $incident['description'] }}</p>
                    <div class="incident-meta"><span>{{ \App\Models\Incident::CATEGORIES[$incident['service']] ?? $incident['service'] }}</span><span>{{ $incident['severity'] === 'danger' ? 'اختلال جدی' : 'اختلال جزئی' }}</span><span>{{ $incident['minutes'] }} دقیقه{{ $incident['active'] ? ' · ادامه دارد' : '' }}</span></div>
                </article>
            @empty
                <p class="empty-history">در این بازه هیچ رخدادی گزارش نشده است.</p>
            @endforelse
        </div>
    </section>
    <p class="availability-note">پایداری بر اساس مدت اختلال‌های منتشرشده در این بازه محاسبه می‌شود. اختلال‌های هم‌زمان یک بار شمرده می‌شوند. برای مشاهده گزارش کامل، عنوان رخداد را انتخاب کنید.</p>
    <div id="day-tooltip" class="day-tooltip" role="tooltip" hidden><strong></strong><span></span></div>
@endsection

@push('scripts')
<script>
    (() => {
        const tooltip = document.getElementById('day-tooltip');
        let active;
        const hide = () => { tooltip.hidden = true; active = null; };
        const show = (bar) => {
            active = bar;
            tooltip.querySelector('strong').textContent = bar.dataset.tooltipDate;
            tooltip.querySelector('span').textContent = bar.dataset.tooltipDetail;
            tooltip.hidden = false;
            const rect = bar.getBoundingClientRect();
            const width = tooltip.offsetWidth;
            const height = tooltip.offsetHeight;
            tooltip.style.left = `${Math.max(12, Math.min(window.innerWidth - width - 12, rect.left + rect.width / 2 - width / 2))}px`;
            tooltip.style.top = `${rect.top > height + 16 ? rect.top - height - 10 : rect.bottom + 10}px`;
        };
        document.querySelectorAll('.day-bar').forEach((bar) => {
            bar.addEventListener('mouseenter', () => show(bar));
            bar.addEventListener('mouseleave', hide);
            bar.addEventListener('focus', () => show(bar));
            bar.addEventListener('blur', hide);
            bar.addEventListener('click', () => show(bar));
        });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(); });
        document.addEventListener('pointerdown', (event) => { if (!event.target.closest('.day-bar')) hide(); });
        window.addEventListener('scroll', hide, { passive: true });
        window.addEventListener('resize', () => { if (active) show(active); });
        window.setTimeout(() => window.location.reload(), 60000);
    })();
</script>
@endpush
