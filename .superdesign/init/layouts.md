# Shared layouts

## Marketing layout

Source: `resources/views/layouts/marketing.blade.php`

The public marketing shell is a Persian RTL Laravel Blade layout. It loads `assets/fonts.css`, Vite assets, and `resources/css/app.css`; renders a fixed, shrinking header with the Aviato logo, navigation from `config('marketing.navigation')`, a solutions mega-menu, login/register actions, and a responsive mobile drawer. It yields page content inside `<main class="marketing-main">`, then renders a light footer with logo, navigation, and homepage-only trust seal.

Important visual classes: max-width `max-w-7xl`, blue `#0069FF`, pale page background `#F5F8FD`, rounded-full desktop nav, rounded-xl controls, slate borders, backdrop blur, and subtle shadows.

The complete source is the repository file referenced above; pass it directly to Superdesign for exact shell context.
