<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class QrCodeController extends Controller
{
    /**
     * Affiche le générateur de QR code.
     * Tout est généré côté client : aucun lien n'est envoyé au serveur.
     */
    public function index()
    {
        $logoSourceUrl = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/logo.png';

        $logoDataUri = Cache::remember('qrcode_logo_datauri', now()->addDay(), function () use ($logoSourceUrl) {
            $body = Http::get($logoSourceUrl)->body();
            return 'data:image/webp;base64,' . base64_encode($body);
        });

        return view('qrcode.index', [
            'logoUrl'           => $logoDataUri,
            'defaultForeground' => '#ff9600',
        ]);
    }
}
