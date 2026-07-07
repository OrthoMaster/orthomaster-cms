<?php

namespace App\Http\Controllers;

use App\Models\MktClickEvent;
use App\Models\MktShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MktToolsController extends Controller
{
    /**
     * Affiche la page d'outils marketing (QR Code + Short Link).
     */
    public function index()
    {
        $logoSourceUrl = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/logo.png';

        $logoDataUri = Cache::remember('qrcode_logo_datauri', now()->addDay(), function () use ($logoSourceUrl) {
            $body = Http::get($logoSourceUrl)->body();
            return 'data:image/webp;base64,' . base64_encode($body);
        });

        return view('mkt.tools', [
            'logoUrl'           => $logoDataUri,
            'defaultForeground' => '#ff9600',
        ]);
    }

    /**
     * Crée (ou réutilise) un short link (AJAX). Sert au tool Short Link
     * et au tool QR Code lorsque le tracking est activé.
     */
    public function storeShortLink(Request $request)
    {
        $data = $request->validate([
            'original_url'     => ['required', 'url', 'max:2000'],
            'title'            => ['nullable', 'string', 'max:255'],
            'source_type'      => ['nullable', 'in:shortlink,qrcode'],
            'utm_source'       => ['nullable', 'string', 'max:255'],
            'utm_medium'       => ['nullable', 'string', 'max:255'],
            'utm_campaign'     => ['nullable', 'string', 'max:255'],
            'utm_term'         => ['nullable', 'string', 'max:255'],
            'utm_content'      => ['nullable', 'string', 'max:255'],
        ]);

        $utm = array_filter([
            'utm_source'   => $data['utm_source']   ?? null,
            'utm_medium'   => $data['utm_medium']   ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term'     => $data['utm_term']     ?? null,
            'utm_content'  => $data['utm_content']  ?? null,
        ]);

        $destinationUrl = $this->buildUrlWithParams($data['original_url'], $utm);
        $sourceType     = $data['source_type'] ?? 'shortlink';

        // Réutiliser un short link existant si même destination + même type.
        // Un nouveau lien n'est créé que si la destination change (URL ou UTM différents).
        $link = MktShortLink::where('source_type', $sourceType)
            ->where('destination_url', $destinationUrl)
            ->first();

        $reused = (bool) $link;

        if (!$link) {
            $link = MktShortLink::create([
                'code'             => MktShortLink::generateUniqueCode(),
                'destination_url'  => $destinationUrl,
                'original_url'     => $data['original_url'],
                'title'            => $data['title'] ?? null,
                'source_type'      => $sourceType,
                'tracking_enabled' => true,
                ...$utm,
            ]);
        }

        return response()->json([
            'ok'          => true,
            'code'        => $link->code,
            'short_url'   => $link->short_url,
            'destination' => $link->destination_url,
            'reused'      => $reused,   // true = lien existant réutilisé
        ]);
    }

    /**
     * Redirection publique : go.orthomaster.fr/sh/XXXXX
     * Enregistre le clic puis redirige vers la destination.
     */
    public function redirect(Request $request, string $code)
    {
        $link = MktShortLink::where('code', $code)->firstOrFail();

        if ($link->tracking_enabled) {
            $this->recordClick($request, $link);
        }

        return redirect()->away($link->destination_url, 302);
    }

    /**
     * Ajoute des query params à une URL en préservant ceux déjà présents.
     */
    private function buildUrlWithParams(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $existing);
        $merged = array_merge($existing, $params);

        $scheme   = $parts['scheme'] ?? 'https';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "{$scheme}://{$host}{$port}{$path}?" . http_build_query($merged) . $fragment;
    }

    /**
     * Enregistre un événement de clic + met à jour les compteurs dénormalisés.
     */
    private function recordClick(Request $request, MktShortLink $link): void
    {
        $agent = $request->userAgent() ?? '';
        $ip    = $request->ip();

        MktClickEvent::create([
            'short_link_id' => $link->id,
            'source_type'   => $link->source_type,
            'ip_address'    => $ip,
            'ip_hash'       => hash('sha256', $ip . config('app.key')),
            'user_agent'    => $agent,
            'device_type'   => $this->detectDevice($agent),
            'browser'       => $this->detectBrowser($agent),
            'platform'      => $this->detectPlatform($agent),
            'referer'       => $request->header('referer'),
            'country'       => $request->header('CF-IPCountry') ?: null,
            'is_bot'        => $this->looksLikeBot($agent),
        ]);

        $link->increment('clicks_count');
        $link->forceFill(['last_clicked_at' => now()])->saveQuietly();
    }

    private function detectDevice(string $ua): string
    {
        $ua = strtolower($ua);
        if (preg_match('/mobile|iphone|android.*mobile/', $ua)) return 'mobile';
        if (preg_match('/ipad|tablet|android/', $ua))          return 'tablet';
        return 'desktop';
    }

    private function detectBrowser(string $ua): string
    {
        $ua = strtolower($ua);
        return match (true) {
            str_contains($ua, 'edg')                                    => 'Edge',
            str_contains($ua, 'opr') || str_contains($ua, 'opera')      => 'Opera',
            str_contains($ua, 'chrome') && !str_contains($ua, 'edg')    => 'Chrome',
            str_contains($ua, 'firefox')                                => 'Firefox',
            str_contains($ua, 'safari') && !str_contains($ua, 'chrome') => 'Safari',
            str_contains($ua, 'samsungbrowser')                         => 'Samsung Internet',
            default                                                     => 'Autre',
        };
    }

    private function detectPlatform(string $ua): string
    {
        $ua = strtolower($ua);
        return match (true) {
            str_contains($ua, 'android')                                  => 'Android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') ||
            str_contains($ua, 'ipod')                                     => 'iOS',
            str_contains($ua, 'windows')                                  => 'Windows',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'linux')                                    => 'Linux',
            default                                                       => 'Autre',
        };
    }

    private function looksLikeBot(string $ua): bool
    {
        return (bool) preg_match('/bot|crawler|spider|facebookexternalhit|whatsapp|slurp|preview/i', $ua);
    }
}