<?php

return [
    'name' => 'Genetics',

    /*
    | Largest genetic upload accepted, in kilobytes. Livewire's temporary-upload
    | rule in config/livewire.php reads the same env value.
    */
    'max_upload_kb' => (int) env('GENETICS_MAX_UPLOAD_KB', 25600),

    /*
    | Most rows a marker list (or extracted PDF) may hold. Every row is stored and
    | an unknown rsID creates a shared pending reference row, so a longer list
    | fails before anything is written. Raw DNA exports have no such cap.
    */
    'max_marker_rows' => (int) env('GENETICS_MAX_MARKER_ROWS', 1000),
];
