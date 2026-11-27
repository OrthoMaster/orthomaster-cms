<?php

namespace App\Support\Scat6;

use Dompdf\Dompdf;

/**
 * Pied de page des PDF SCAT6 : sur chaque page, un filet, un libellé à gauche et « Page X / Y » à droite.
 * Dessiné sur le canevas après le rendu (dompdf ne sait pas compter les pages en CSS : counter(pages) = 0).
 */
final class Scat6PdfFooter
{
    private const MM = 72 / 25.4;

    /**
     * @param  \Barryvdh\DomPDF\PDF  $pdf  PDF déjà chargé (loadView), pas encore rendu
     * @param  string  $label  texte de gauche (patient, document, date)
     * @param  float  $sideMm  marge gauche / droite de la page, pour aligner le pied sur le texte
     */
    public static function apply($pdf, string $label, float $sideMm): void
    {
        $pdf->render();
        self::draw($pdf->getDomPDF(), $label, $sideMm);
    }

    public static function draw(Dompdf $dompdf, string $label, float $sideMm): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $size = 7.5;
        $color = [0.42, 0.45, 0.52];

        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $left = $sideMm * self::MM;
        $right = $w - $sideMm * self::MM;
        $lineY = $h - 10 * self::MM;
        $textY = $lineY + 2.2 * self::MM;

        $canvas->page_line($left, $lineY, $right, $lineY, [0.84, 0.86, 0.9], 0.5);
        $canvas->page_text($left, $textY, $label, $font, $size, $color);
        // Largeur prévue pour « Page 99 / 99 » : le numéro reste aligné à droite quel que soit le nombre de pages.
        $pageWidth = $metrics->getTextWidth('Page 99 / 99', $font, $size);
        $canvas->page_text($right - $pageWidth, $textY, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, $size, $color);
    }
}
