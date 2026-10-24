<?php

return [
    'name' => 'Reports',

    /*
    | Template used when a request names none. Must be a key of "templates".
    */
    'default_template' => env('REPORTS_DEFAULT_TEMPLATE', 'clinical'),

    /*
    | Registered PDF templates. A template is this entry plus the view folder
    | reports::templates.<key> (layout, header, footer, styles). Only keys listed
    | here can be requested. Margins are millimetres: [top, right, bottom, left].
    */
    'templates' => [
        'clinical' => [
            'label' => 'Clinical',
            'format' => 'a4',
            'margins' => [22, 16, 22, 16],
            'orientation' => 'portrait',
        ],
        'modern' => [
            'label' => 'Modern',
            'format' => 'letter',
            'margins' => [20, 18, 20, 18],
            'orientation' => 'portrait',
        ],
    ],
];
