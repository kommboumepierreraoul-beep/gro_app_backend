<?php
// ============================================================
// config/missions.php — Configuration du module missions
// ============================================================

return [

    /*
    |--------------------------------------------------------------------------
    | Diffusion
    |--------------------------------------------------------------------------
    */
    'diffusion' => [
        // Délai avant expansion de la diffusion (si mission non pourvue)
        'expand_after_hours' => env('MISSION_EXPAND_AFTER_HOURS', 48),

        // Rayon par défaut (km) si l'auteur ne précise pas
        'default_radius_km'  => env('MISSION_DEFAULT_RADIUS_KM', 25),

        // Taille des chunks pour les notifications en masse
        'notification_chunk_size' => env('MISSION_NOTIF_CHUNK', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pièces jointes
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        // Disk de stockage (s3 en prod, public en dev)
        'disk'     => env('MISSION_ATTACHMENT_DISK', 'public'),

        // Taille max par fichier (Mo)
        'max_size_mb' => env('MISSION_MAX_ATTACHMENT_MB', 5),

        // Types MIME autorisés
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],

        // Nombre max de fichiers par candidature
        'max_files' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rappels
    |--------------------------------------------------------------------------
    */
    'reminders' => [
        // Heure supposée de début de mission si non précisée (format H)
        'default_start_hour' => 8,

        // Heure d'envoi des invitations à évaluer (J+1)
        'review_prompt_hour' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'missions_per_page'     => 15,
        'applications_per_page' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues utilisées
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'notifications' => env('MISSION_NOTIF_QUEUE', 'notifications'),
        'low_priority'  => env('MISSION_LOW_QUEUE', 'low'),
    ],
];






