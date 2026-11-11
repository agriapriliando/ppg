<?php

return [
    'supported_types' => [
        'mp4' => [
            'label' => 'Video MP4',
            'mime_types' => ['video/mp4'],
        ],
        'mov' => [
            'label' => 'Video MOV',
            'mime_types' => ['video/quicktime'],
        ],
        'webm' => [
            'label' => 'Video WebM',
            'mime_types' => ['video/webm'],
        ],
        'pdf' => [
            'label' => 'PDF',
            'mime_types' => ['application/pdf'],
        ],
        'jpg' => [
            'label' => 'JPG',
            'mime_types' => ['image/jpeg'],
        ],
        'jpeg' => [
            'label' => 'JPEG',
            'mime_types' => ['image/jpeg'],
        ],
        'png' => [
            'label' => 'PNG',
            'mime_types' => ['image/png'],
        ],
        'docx' => [
            'label' => 'DOCX',
            'mime_types' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ],
        'doc' => [
            'label' => 'DOC',
            'mime_types' => ['application/msword'],
        ],
        'xlsx' => [
            'label' => 'XLSX',
            'mime_types' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ],
        'xls' => [
            'label' => 'XLS',
            'mime_types' => ['application/vnd.ms-excel'],
        ],
        'txt' => [
            'label' => 'TXT',
            'mime_types' => ['text/plain'],
        ],
    ],

    'previewable_mimes' => [
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ],

    'video_extensions' => ['mp4', 'mov', 'webm'],

    'defaults' => [
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_file_size_bytes' => 10 * 1024 * 1024, // 10 MB
        'workspace_quota_bytes' => null, // null = unlimited by application
        'general_instructions' => 'Unggah berkas kelengkapan pendaftaran Anda. Pastikan dokumen terbaca dengan jelas dan tidak melebihi batas ukuran yang ditentukan.',
    ],
];
