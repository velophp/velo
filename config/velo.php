<?php

return [

    /**
     * App config cache TTL
     * used for settings like: TrustProxies, etc
     */
    'cache_ttl' => env('CACHE_TTL', 3600),

    /**
     * Prefix for realtime channels
     */
    'realtime_channel_prefix' => 'velo.realtime.',

    /**
     * How long a realtime connection is considered stale
     * default: 5 minutes
     */
    'realtime_connection_threshold' => 5,

    /**
     * Session defer threshold
     * How long a session is updated on every request
     * default: 150 Seconds
     */
    'session_defer_threshold' => 150,

    /**
     * Session sliding expiration
     * How long a session is extended on every request
     * default: 0 Second
     */
    'session_sliding_expiration' => 0,

    /**
     * SQL generated column strategy, can be STORED or VIRTUAL
     * Virtual will recalculate every read on database
     * STORED will recalculate once every write on database
     * use STORED when you need read performance
     * use VIRTUAL when you need write performance
     * see https://gemini.google.com/share/3438ef7444c8
     */
    'sql_generated_column_strategy' => 'VIRTUAL',

    /**
     * Used in realtion picker to automatically get fields for the display, used the first one found in the list
     */
    'relation_display_fields' => [
        'name',
        'title',
        'email',
        'fullname',
        'username',
        'firstname',
        'first_name',
        'firstName',
    ],

    /**
     * Used in collection configuration to display the available mime types
     */
    'available_mime_types' => [
        ['id' => 'application/pdf', 'name' => 'application/pdf', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/337/337946.png'],
        ['id' => 'application/json', 'name' => 'application/json', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136525.png'],
        ['id' => 'application/xml', 'name' => 'application/xml', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136526.png'],
        ['id' => 'application/zip', 'name' => 'application/zip', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136544.png'],
        ['id' => 'audio/mpeg', 'name' => 'audio/mpeg', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136548.png'],
        ['id' => 'audio/wav', 'name' => 'audio/wav', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136548.png'],
        ['id' => 'image/gif', 'name' => 'image/gif', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136521.png'],
        ['id' => 'image/jpeg', 'name' => 'image/jpeg', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136524.png'],
        ['id' => 'image/png', 'name' => 'image/png', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136523.png'],
        ['id' => 'image/svg+xml', 'name' => 'image/svg+xml', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136530.png'],
        ['id' => 'image/webp', 'name' => 'image/webp', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/8263/8263118.png'],
        ['id' => 'text/css', 'name' => 'text/css', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136527.png'],
        ['id' => 'text/csv', 'name' => 'text/csv', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136533.png'],
        ['id' => 'text/html', 'name' => 'text/html', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136528.png'],
        ['id' => 'text/plain', 'name' => 'text/plain', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136538.png'],
        ['id' => 'video/mp4', 'name' => 'video/mp4', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136545.png'],
        ['id' => 'video/mpeg', 'name' => 'video/mpeg', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136545.png'],
        ['id' => 'video/quicktime', 'name' => 'video/quicktime', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/136/136545.png'],
        ['id' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'name' => '.docx', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/888/888883.png'],
        ['id' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'name' => '.xlsx', 'avatar' => 'https://cdn-icons-png.flaticon.com/512/888/888850.png'],
    ],

    /**
     * Used in collection configuration to quickly set mime types for a field
     */
    'mime_types_presets' => [
        'image' => [
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/svg+xml',
            'image/webp',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/wav',
        ],
        'video' => [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
        ],
        'documents' => [
            'application/pdf',
            'text/csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'archive' => [
            'application/zip',
        ],
    ],

    /**
     * Used in collection configuration to configure the tinymce editor
     * see tinymce documentation for more options
     */
    'tinymce_config' => [
        'plugins'    => 'autoresize lists link image table code quickbars',
        'min_height' => 250,
        'max_height' => 500,
        'statusbar'  => false,

        'toolbar' => 'undo redo blocks fontfamily fontsize | '
            . 'bold italic underline strikethrough | '
            . 'forecolor backcolor | '
            . 'alignleft aligncenter alignright alignjustify | '
            . 'bullist numlist outdent indent | '
            . 'link image table | '
            . 'removeformat | '
            . 'code |',

        'quickbars_selection_toolbar' => 'bold italic underline | link',
        'quickbars_insert_toolbar'    => 'quickimage quicktable',
    ],

    'tinymce_key' => env('TINYMCE_KEY'),
];
