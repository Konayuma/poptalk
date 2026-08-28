<?php

return [

    'min_frequency' => 1,

    'max_frequency' => 99,

    'heartbeat_interval_seconds' => (int) env('POPTALK_HEARTBEAT_INTERVAL', 10),

    /*
    | Maximum seconds a single push-to-talk lock may be held.
    */
    'ptt_timeout_seconds' => (int) env('POPTALK_PTT_TIMEOUT', 30),

    /*
    | Operators who miss a heartbeat for this long are dropped from a frequency.
    */
    'presence_ttl_seconds' => (int) env('POPTALK_PRESENCE_TTL', 45),

    /*
    | WebRTC / walkie signaling payloads older than this are discarded.
    */
    'signal_ttl_seconds' => (int) env('POPTALK_SIGNAL_TTL', 30),

    'callsign' => [
        'min' => 2,
        'max' => 16,
        'pattern' => '/^[A-Z0-9][A-Z0-9\- ]{0,15}$/',
    ],

    'signal_types' => [
        'offer',
        'answer',
        'ice-candidate',
        'hangup',
    ],

    'max_signal_payload_bytes' => 32_768,

];
