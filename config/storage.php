<?php

return [
    'aviato_endpoint' => env('AVIATO_S3_ENDPOINT', env('APP_URL')),
    'aviato_region' => env('AVIATO_S3_REGION', 'aviato-1'),
    'max_object_bytes' => (int) env('AVIATO_S3_MAX_OBJECT_BYTES', 10737418240),
    'max_bucket_bytes' => (int) env('AVIATO_S3_MAX_BUCKET_BYTES', 107374182400),
    'multipart_expiry_hours' => (int) env('AVIATO_S3_MULTIPART_EXPIRY_HOURS', 24),
];
