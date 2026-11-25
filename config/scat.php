<?php

/*
|--------------------------------------------------------------------------
| SCAT6 partner integration
|--------------------------------------------------------------------------
| scat-server (api SCAT6) calls /api/partner/scat/* with the X-Partner-Key header.
| The OrthoMaster app calls /api/scat/handoff to open SCAT6 already signed in.
*/
return [

    // Shared secret with scat-server (ORTHOMASTER_PARTNER_KEY over there). Long random string.
    'partner_key' => env('SCAT_PARTNER_KEY'),

    // Link the OrthoMaster app opens. Universal link once the SCAT domain is set up,
    // custom scheme until then.
    'handoff_url' => env('SCAT_HANDOFF_URL', 'orthomasterscat://auth/handoff'),

    // Lifetime of a handoff code, in seconds.
    'handoff_ttl' => (int) env('SCAT_HANDOFF_TTL', 60),
];
