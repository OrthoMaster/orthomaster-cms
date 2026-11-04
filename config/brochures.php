<?php

/*
 * Pages partenaires des experts (« landing pages »).
 *
 *  - domain          : sous-domaine dédié. Sur ce domaine, seules les pages partenaires
 *                      répondent (voir RestrictExpertDomain) ; les autres domaines ne changent pas.
 *  - register_url    : bouton « Rejoindre OrthoMaster » (bloc OrthoMaster).
 *  - orthomaster_url : bouton « Découvrir la plateforme ».
 */
return [
    'domain'          => env('BROCHURES_DOMAIN', 'expert.orthomaster.fr'),
    'register_url'    => env('BROCHURES_REGISTER_URL', 'https://auth.orthomaster.fr/register'),
    'orthomaster_url' => env('BROCHURES_ORTHOMASTER_URL', 'https://orthomaster.fr'),
];
