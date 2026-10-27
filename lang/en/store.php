<?php

return [
    'hero_title' => 'Your workspace, ready in minutes',
    'hero_subtitle' => 'Secure desktops, GPU power and ready-to-use apps, reached from any browser.',
    'all' => 'All',
    'from' => 'from',
    'configure' => 'Choose',
    'empty_kind' => 'Nothing here yet.',
    'spec' => ['cores' => ':n vCPU'],
    'admin' => ['kind' => 'Store section', 'auto' => 'Automatic (from the persistent setting)', 'kind_hint' => 'Where this product appears on the store page.', 'gpu' => 'GPU shown on the card (optional)'],
    'kind' => [
        'vdi-persistent' => [
            'name' => 'Persistent desktops',
            'blurb' => 'Your own Windows or Linux desktop. Files, apps and settings are kept between sessions.',
        ],
        'vdi-ephemeral' => [
            'name' => 'Non-persistent desktops',
            'blurb' => 'A clean desktop every session. Ideal for shared labs, training and contractors.',
        ],
        'gpu' => [
            'name' => 'GPU and high performance',
            'blurb' => 'Dedicated graphics and compute for design, 3D, video and heavy processing.',
        ],
        'app-oneclick' => [
            'name' => 'One-click apps',
            'blurb' => 'Software installed on demand into your desktop, ready to use.',
        ],
        'app-docker' => [
            'name' => 'Containerised apps',
            'blurb' => 'Desktop applications delivered from a container, with nothing to install.',
        ],
        'app-browser' => [
            'name' => 'Isolated browser',
            'blurb' => 'A sealed browser session for risky sites and web apps. Nothing stays on the device.',
        ],
        'other' => [
            'name' => 'Other services',
            'blurb' => '',
        ],
    ],
];
