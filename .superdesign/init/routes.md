# Route map

- `/` -> closure in `routes/web.php` -> `resources/views/home.blade.php` -> `layouts.marketing`
- `/pricing` -> closure -> `resources/views/pricing.blade.php` -> `layouts.marketing`
- `/solutions` -> closure -> `resources/views/solutions.blade.php` -> `layouts.marketing`
- `/solutions/co-location` -> closure -> `resources/views/solutions-colocation.blade.php` -> `layouts.marketing`
- `/blog` -> `BlogController@index` -> `resources/views/blog/index.blade.php`
- `/contact` -> contact page and form flow
- `/api-docs` -> API documentation page

The homepage receives active marketing VM bundles, wallet formatting, and the three latest Markdown blog posts.
