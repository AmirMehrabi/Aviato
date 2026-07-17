<?php

return [
    's3_domain' => env('AVIATO_S3_DOMAIN', 's3.aviato.ir'),
    'aviato_endpoint' => env('AVIATO_S3_ENDPOINT', 'https://s3.aviato.ir'),
    'aviato_region' => env('AVIATO_S3_REGION', 'aviato-1'),
    'max_object_bytes' => (int) env('AVIATO_S3_MAX_OBJECT_BYTES', 10737418240),
    'max_bucket_bytes' => (int) env('AVIATO_S3_MAX_BUCKET_BYTES', 107374182400),
    'multipart_expiry_hours' => (int) env('AVIATO_S3_MULTIPART_EXPIRY_HOURS', 24),
    'object_root' => env('AVIATO_S3_OBJECT_ROOT', storage_path('app/s3-data')),
];
