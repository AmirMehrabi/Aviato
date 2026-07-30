# Extractable components

## MarketingNav

- Source: `resources/views/layouts/marketing.blade.php`
- Category: layout
- Description: Fixed RTL public navigation with logo, desktop links, solutions mega-menu, auth actions, and mobile drawer.
- Extractable props: activePage, scrolled, menuOpen, solutionsOpen
- Hardcoded: Aviato logo path, configured nav labels, inline SVG icons, Tailwind classes

## MarketingFooter

- Source: `resources/views/layouts/marketing.blade.php`
- Category: layout
- Description: Public marketing footer with logo, navigation, and trust content.
- Extractable props: none
- Hardcoded: footer labels and brand assets

## PlanTable

- Source: `resources/views/home.blade.php`
- Category: basic
- Description: Responsive marketing plan comparison with mobile cards and desktop table.
- Extractable props: bundles, wallet formatter, recommendedIndex
- Hardcoded: plan labels, feature labels, and CTA copy
