<?php

return [
    // Match the 100 MiB ceiling in PublishDocumentPolicy; final uploads still use each period's rules.
    'temporary_file_upload' => ['rules' => ['required', 'file', 'max:102400']],
    'pagination_theme' => 'bootstrap',
    'component_layout' => 'layouts::app',
];
