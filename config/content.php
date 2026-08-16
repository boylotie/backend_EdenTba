<?php

return [

    /*
    | Chemin du binaire ffprobe utilisé pour extraire la durée (MOD-05-P1).
    | Absent ou indisponible : la durée reste nulle et peut être fournie
    | manuellement lors de la gestion du contenu (MOD-05-P2).
    */
    'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),

];
