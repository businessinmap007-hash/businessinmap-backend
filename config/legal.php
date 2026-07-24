<?php

/**
 * Legal document versions the app records consent against. Bump a version when
 * the corresponding document materially changes — new signups then record the
 * new version, and you can tell who accepted which revision.
 */
return [
    'terms_version' => env('LEGAL_TERMS_VERSION', '2026-07-24'),
    'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-07-24'),
];
