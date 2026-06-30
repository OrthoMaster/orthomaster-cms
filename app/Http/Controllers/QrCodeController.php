<?php

namespace App\Http\Controllers;

class QrCodeController extends Controller
{
    /**
     * Affiche le générateur de QR code.
     * Tout est généré côté client : aucun lien n'est envoyé au serveur.
     */
    public function index()
    {
        return view('qrcode.index', [
            // URL du logo inséré au centre du QR code (modifiable ici).
            // Vous pouvez aussi le brancher sur votre système de réglages :
            // 'logoUrl' => setting('system.web_icon'),
            'logoUrl'         => 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/web/logo_orange_no_bg_40.webp',
            'defaultForeground' => '#ff9600',
        ]);
    }
}