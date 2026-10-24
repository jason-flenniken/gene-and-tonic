<?php

return [
    'name' => 'Genetics',

    /*
    | Largest genetic upload accepted, in kilobytes. Livewire's temporary-upload
    | rule in config/livewire.php reads the same env value.
    */
    'max_upload_kb' => (int) env('GENETICS_MAX_UPLOAD_KB', 25600),
];
