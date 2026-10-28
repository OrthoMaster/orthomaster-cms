<?php

namespace App\Support\Booking;

/**
 * Repère les coordonnées dans un message (téléphone, e-mail, lien) : la messagerie ne doit
 * pas servir à continuer la relation hors de la plateforme.
 *
 * Volontairement simple et un peu large : un faux positif se corrige en reformulant,
 * une fuite ne se rattrape pas. Les formes écrites en toutes lettres (« zéro six… »)
 * ne sont pas visées.
 */
class ChatContactFilter
{
    public const REASON_PHONE = 'phone';
    public const REASON_EMAIL = 'email';
    public const REASON_LINK  = 'link';

    public const MESSAGES = [
        self::REASON_PHONE => "Les numéros de téléphone ne peuvent pas être échangés dans la messagerie.",
        self::REASON_EMAIL => "Les adresses e-mail ne peuvent pas être échangées dans la messagerie.",
        self::REASON_LINK  => "Les liens ne peuvent pas être envoyés dans la messagerie.",
    ];

    /** 9 chiffres : un mobile français écrit sans le 0 initial (6 12 34 56 78). */
    private const PHONE_MIN_DIGITS = 9;

    /** Domaines nus repérés sans « http » ni « www » (orthomaster.fr, wa.me…). */
    private const TLDS = 'com|fr|net|org|io|be|ch|de|eu|info|me|co|app|ly|gl|link|site|online|pro|biz|xyz|page|to|gg|li';

    /**
     * Motif trouvé, ou null si le message peut partir.
     */
    public static function check(string $text): ?string
    {
        if (self::hasEmail($text)) {
            return self::REASON_EMAIL;
        }
        if (self::hasLink($text)) {
            return self::REASON_LINK;
        }
        if (self::hasPhone($text)) {
            return self::REASON_PHONE;
        }

        return null;
    }

    public static function message(string $reason): string
    {
        return self::MESSAGES[$reason] ?? 'Ce message contient des coordonnées et ne peut pas être envoyé.';
    }

    private static function hasEmail(string $text): bool
    {
        // Forme classique, puis les déguisements courants : « jean (at) gmail (point) com ».
        return preg_match('/[\p{L}0-9._%+-]+@[\p{L}0-9-]+(\.[\p{L}0-9-]+)*\.\p{L}{2,}/u', $text)
            || preg_match('/[\p{L}0-9._%+-]+\s*@\s*[\p{L}0-9-]+\s*(\.|\(dot\)|\[dot\]|\bpoint\b|\bdot\b)\s*\p{L}{2,}/iu', $text)
            || preg_match('/[\p{L}0-9._%+-]+\s*(\(at\)|\[at\]|\{at\}|\barobase\b)\s*[\p{L}0-9-]+/iu', $text);
    }

    private static function hasLink(string $text): bool
    {
        return preg_match('~\b(https?://|www\.)~i', $text)
            // Point entre deux mots sans espace : « mon-site.fr », pas la fin d'une phrase.
            || preg_match('~\b[\p{L}0-9-]+(\.[\p{L}0-9-]+)*\.(' . self::TLDS . ')\b(?!\.?[\p{L}0-9])~iu', $text);
    }

    /**
     * Suite d'au moins 9 chiffres où les chiffres ne sont séparés que par des espaces,
     * points, tirets ou parenthèses (au plus 2 de suite). Les dates (12/11/2026),
     * heures (14h30) et montants (1 250,00 €) n'atteignent pas le seuil ou utilisent
     * d'autres séparateurs.
     */
    private static function hasPhone(string $text): bool
    {
        // Espaces insécables et fines (souvent collés depuis un autre logiciel) : simples espaces.
        $text = preg_replace('/[\x{00A0}\x{202F}\x{2007}]/u', ' ', $text);

        if (!preg_match_all('/(?:\+|00)?\d(?:[\s.\-()]{0,2}\d)+/u', $text, $matches)) {
            return false;
        }

        foreach ($matches[0] as $candidate) {
            if (strlen(preg_replace('/\D/', '', $candidate)) >= self::PHONE_MIN_DIGITS) {
                return true;
            }
        }

        return false;
    }
}
