<?php

declare(strict_types=1);

return [
    'disk' => 'transaction_attachments',
    'connection' => 'database',
    'queue' => 'attachments',
    'max_files' => (int) env('TRANSACTION_ATTACHMENTS_MAX_FILES', 5),
    'max_file_bytes' => [
        'image' => (int) env('TRANSACTION_ATTACHMENTS_MAX_IMAGE_BYTES', 5242880),
        'pdf' => (int) env('TRANSACTION_ATTACHMENTS_MAX_PDF_BYTES', 5242880),
        'xml' => (int) env('TRANSACTION_ATTACHMENTS_MAX_XML_BYTES', 1048576),
        'zip' => (int) env('TRANSACTION_ATTACHMENTS_MAX_ZIP_BYTES', 5242880),
    ],
    'max_transaction_bytes' => (int) env('TRANSACTION_ATTACHMENTS_MAX_TRANSACTION_BYTES', 15728640),
    'max_storage_bytes' => (int) env('TRANSACTION_ATTACHMENTS_MAX_STORAGE_BYTES', 5368709120),
    'min_free_disk_bytes' => (int) env('TRANSACTION_ATTACHMENTS_MIN_FREE_DISK_BYTES', 1073741824),
    'max_image_pixels' => (int) env('TRANSACTION_ATTACHMENTS_MAX_IMAGE_PIXELS', 40000000),
    'image_max_dimension' => (int) env('TRANSACTION_ATTACHMENTS_IMAGE_MAX_DIMENSION', 2560),
    'webp_quality' => (int) env('TRANSACTION_ATTACHMENTS_WEBP_QUALITY', 85),
    'processing_memory_bytes' => (int) env('TRANSACTION_ATTACHMENTS_PROCESSING_MEMORY_BYTES', 268435456),
    'processing_disk_bytes' => (int) env('TRANSACTION_ATTACHMENTS_PROCESSING_DISK_BYTES', 268435456),
    'processing_timeout' => (int) env('TRANSACTION_ATTACHMENTS_PROCESSING_TIMEOUT', 60),
    'max_attempts' => (int) env('TRANSACTION_ATTACHMENTS_MAX_ATTEMPTS', 3),
    'source_retention_hours' => (int) env('TRANSACTION_ATTACHMENTS_SOURCE_RETENTION_HOURS', 24),
    'zip_max_entries' => (int) env('TRANSACTION_ATTACHMENTS_ZIP_MAX_ENTRIES', 20),
    'zip_max_expanded_bytes' => (int) env('TRANSACTION_ATTACHMENTS_ZIP_MAX_EXPANDED_BYTES', 20971520),
    'zip_max_ratio' => (int) env('TRANSACTION_ATTACHMENTS_ZIP_MAX_RATIO', 100),
    'accepted_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'xml', 'zip'],
    'poll_interval_ms' => 5000,
];
