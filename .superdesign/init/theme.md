# Theme token summary

- Framework: Laravel 13, Blade, Vite
- CSS: Tailwind CSS 4 via `resources/css/app.css`
- Direction: Persian RTL
- Font stack: IranSans, Dana, Estedad, Pelak, Vazirmatn
- Primary: #0069FF; hover #0050D0; soft #EEF5FF
- Text: #0F172A; secondary #475569; quiet #94A3B8
- Surface: #FFFFFF; page tint #F5F8FD; border #E2E8F0
- Controls: rounded-xl, compact px-5/py-3; cards rounded-[1.75rem]
- Layout: max-w-7xl, responsive px-4/md:px-8/lg:px-10

## Source files

- `resources/css/app.css` imports Tailwind and defines the global font stack and base rendering.
- `resources/views/layouts/marketing.blade.php` contains marketing-specific layout CSS and tokens.
- `tailwind.config.*` is not used; Tailwind 4 is configured through CSS and Vite.
