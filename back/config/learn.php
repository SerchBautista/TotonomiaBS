<?php

return [
    'driver' => env('LEARN_CONTENT_DRIVER', 'file'),

    'file' => [
        'path' => resource_path('content/learn/content.json'),
    ],
];
