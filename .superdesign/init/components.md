# Shared UI components

This Laravel Blade application uses Tailwind CSS 4 utility classes and inline SVG icons rather than a React/Vue component library. Shared visual primitives are currently authored in Blade layouts and views.

## Marketing controls

Buttons and links are inline Blade anchors using rounded-xl borders, #0069FF primary fills, white secondary fills, and Tailwind transition utilities. Their source appears in `resources/views/layouts/marketing.blade.php` and `resources/views/home.blade.php`.

## Logo

`public/assets/images/aviato_logo_full_color.webp` is the primary brand asset. White and black logo variants also exist in the same directory.
