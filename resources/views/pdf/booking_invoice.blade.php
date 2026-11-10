<!DOCTYPE html>
<html lang="fr">

{{--
    Facture (VE/…) ou avoir (RVE/…) d'un rendez-vous expert — numéro donné par Odoo.
    Facture : bleu, « Acquittée » (débitée à l'acceptation de l'expert).
    Avoir : rouge, montants négatifs, « Remboursé » une fois le remboursement fait.
    Variables : voir BookingInvoiceService::pdfData().
--}}
@php
    $avoir = $kind === 'credit_note';
    $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    $neg = fn ($v) => '- ' . number_format(abs((float) $v), 2, ',', ' ') . ' €';
@endphp

<head>
    <meta charset="UTF-8">
    <title>{{ $avoir ? 'Avoir' : 'Facture' }} #{{ $no }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* A4 Page Setup - 210mm x 297mm */
        @page {
            size: A4;
            margin: 15mm 12mm 20mm 12mm;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.3;
            padding: 10px 30px;
        }

        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 80pt;
            font-weight: bold;
            color: rgba(220, 50, 50, 0.12);
            white-space: nowrap;
            pointer-events: none;
            z-index: 1000;
            letter-spacing: 8px;
            text-transform: uppercase;
            border: 8px solid rgba(220, 50, 50, 0.12);
            padding: 10px 30px;
            border-radius: 10px;
            user-select: none;
        }

        /* Header Section */
        .header {
            width: 100%;
            margin-bottom: 10px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: top;
            width: 50%;
            padding: 5px;
        }

        .company-info {
            font-size: 8pt;
            line-height: 1.4;
        }

        .company-name {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 3px;
        }

        .client-info {
            margin-bottom: 8px;
        }

        .client-name {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 3px;
        }

        /* Contract Number */
        .contract-title {
            font-size: 28pt;
            color: #2196F3;
            text-align: right;
            margin-bottom: 10px;
        }

        /* Info Box */
        .info-box {
            border: 2px solid #FFA726;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #FFF9E6;
        }

        .info-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-box td {
            padding: 3px 5px;
            font-size: 9pt;
        }

        .info-label {
            font-weight: bold;
            color: #FF9800;
            width: 25%;
        }

        /* Formation Section */
        .formation-section {
            width: 100%;
            margin-bottom: 20px;
        }

        .formation-section table {
            width: 100%;
            border-collapse: collapse;
        }

        .formation-section td {
            vertical-align: top;
            width: 50%;
            padding: 5px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 10pt;
        }

        .learners-box {
            border: 1px solid #333;
            padding: 8px;
            border-radius: 8px;
        }

        .learner-item {
            padding: 4px 0;
            border-bottom: 1px solid #ddd;
            font-size: 9pt;
        }

        .learner-item:last-child {
            border-bottom: none;
        }

        /* Items Table - Optimized for A4 width */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            table-layout: fixed;
        }

        .items-table th {
            background-color: #1976D2;
            color: white;
            padding: 8px 4px;
            font-weight: bold;
            font-size: 8pt;
            border: none;
        }

        .items-table td {
            padding: 6px 4px;
            border-bottom: 1px solid #ddd;
            font-size: 9pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left !important;
        }

        .text-center {
            text-align: center;
        }

        /* Total Section */
        .total-row {
            background-color: #f5f5f5;
        }

        .total-row td {
            font-weight: bold;
            font-size: 9pt;
        }

        .grand-total {
            background-color: #1976D2 !important;
            color: white !important;
        }

        .grand-total td {
            font-size: 10pt;
            font-weight: bold;
        }

        /* Prevent page breaks */
        .no-break {
            page-break-inside: avoid;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 15px;
        }

        .terms-link {
            font-size: 8pt;
            margin-top: 10px;
            margin-bottom: 15px;
            color: #1976D2;
        }

        .terms-link span {
            color: #333 !important
        }

        .signature-section {
            margin-top: 20px;
            padding-left: 10px;
        }

        .signature-image {
            height: 100px;
        }

        .signature-placeholder {
            margin-top: 15px;
            height: 50px;
            border: 3px dashed #ddd;
            background-color: #f5f5f5;
            border-radius: 16px;
            color: #ddd;
            text-align: center;
            padding: 20px;
        }

        .footer-info {
            border-top: 1px solid #333;
            padding-top: 15px;
            text-align: center;
            font-size: 8pt;
            line-height: 1.4;
            color: #666;
            margin-top: 20px;
        }

        /* Rendez-vous experts : avoir en rouge, montants négatifs */
        .contract-title.avoir { color: #C62828; }
        .items-table th.avoir { background-color: #C62828; }
        .grand-total.avoir { background-color: #C62828 !important; }
        .neg { color: #C62828; }
        .watermark.refund { color: rgba(25, 118, 210, 0.12); border-color: rgba(25, 118, 210, 0.12); }

        /* Utility Classes */
        .small {
            font-size: 8pt;
        }

        .mt-10 {
            margin-top: 10px;
        }
    </style>
</head>

<body>

    @if ($settled)
        <div class="watermark{{ $avoir ? ' refund' : '' }}">{{ $avoir ? 'Remboursé' : 'Acquittée' }}</div>
    @endif

    <!-- Header -->
    <div class="header no-break">
        <table>
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="client-info">
                        <div class="client-name">{{ $client['name'] }}</div>
                        @if (!empty($client['company']))
                            <div class="small">{{ $client['company'] }}</div>
                        @endif
                        @if (!empty($client['address']))
                            <div class="small">{!! nl2br(e($client['address'])) !!}</div>
                        @endif
                        @if (!empty($client['email']))
                            <div class="small">{{ $client['email'] }}</div>
                        @endif
                    </div>
                </td>
                <td style="text-align: right;">
                    <div class="company-info">
                        <div class="company-name">{{ $company['name'] }}</div>
                        <div>{{ $company['address'] }}</div>
                        <div>{{ $company['postal_code'] }} {{ $company['city'] }}</div>

                        <div class="mt-10">
                            <div><strong>N° SIRET :</strong> {{ $company['siret'] }}</div>
                            <div><strong>Organisme de Formation :</strong> {{ $company['formation_number'] }}</div>
                            <div><strong>Certification Qualiopi :</strong> {{ $company['qualiopi'] }}</div>
                            <div><strong>N° TVA intracommunautaire :</strong> {{ $company['tva'] }}</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Title -->
    <div class="contract-title{{ $avoir ? ' avoir' : '' }}">
        {{ $avoir ? 'Avoir' : 'Facture' }} #{{ $no }}
    </div>

    <!-- Info Box -->
    <div class="info-box no-break">
        <table>
            <tr>
                <td class="info-label">{{ $avoir ? "Date de l'avoir :" : 'Date de la facture :' }}</td>
                <td class="info-label">{{ $avoir ? "Facture d'origine :" : 'Date de paiement :' }}</td>
                <td class="info-label">Référence :</td>
            </tr>
            <tr>
                <td class="text-center">{{ $date }}</td>
                <td class="text-center">{{ $avoir ? $parentNo . ' du ' . $parentDate : $paidDate }}</td>
                <td class="text-center">{{ $reference }}</td>
            </tr>
        </table>
    </div>

    <!-- Rendez-vous / Motif + Participant -->
    <div class="formation-section no-break">
        <table>
            <tr>
                <td>
                    <div class="learners-box">
                        @if ($avoir)
                            <div class="section-title">Motif</div>
                            <div class="learner-item">{{ $motif }}</div>
                        @else
                            <div class="section-title">Rendez-vous expert</div>
                            <div class="learner-item"><strong>Expert :</strong> {{ $expertName }}</div>
                            <div class="learner-item"><strong>Format :</strong> {{ $format }}</div>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="learners-box">
                        <div class="section-title">Participant</div>
                        <div class="learner-item">{{ $participant }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <br />

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-left{{ $avoir ? ' avoir' : '' }}" style="width: 55%;">DESCRIPTION</th>
                <th class="text-right{{ $avoir ? ' avoir' : '' }}" style="width: 20%;">QUANTITÉ</th>
                <th class="text-right{{ $avoir ? ' avoir' : '' }}" style="width: 25%;">MONTANT HT</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{{ $lineTitle }}</strong><br><span class="small">{{ $lineSub }}</span></td>
                <td class="text-right">{{ $quantity }}</td>
                <td class="text-right{{ $avoir ? ' neg' : '' }}">{{ $avoir ? $neg($ht) : $eur($ht) }}</td>
            </tr>

            <tr class="total-row">
                <td colspan="2" class="text-right">Montant hors taxes</td>
                <td class="text-right{{ $avoir ? ' neg' : '' }}">{{ $avoir ? $neg($ht) : $eur($ht) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2" class="text-right">TVA {{ rtrim(rtrim(number_format($tvaRate, 2, ',', ''), '0'), ',') }}%</td>
                <td class="text-right{{ $avoir ? ' neg' : '' }}">{{ $avoir ? $neg($tva) : $eur($tva) }}</td>
            </tr>
            <tr class="grand-total{{ $avoir ? ' avoir' : '' }}">
                <td colspan="2" class="text-right"><strong>{{ $avoir ? "Total TTC de l'avoir" : 'Total TTC' }}</strong></td>
                <td class="text-right"><strong>{{ $avoir ? $neg($ttc) : $eur($ttc) }}</strong></td>
            </tr>

            @if ($settled)
                <tr>
                    <td colspan="2" class="text-right">{{ $settledLine }}</td>
                    <td class="text-right">{{ $eur($ttc) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="2" class="text-right"><strong>{{ $avoir ? 'Reste à rembourser' : 'Montant dû' }}</strong></td>
                    <td class="text-right">{{ $eur(0) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <div>{!! $paymentNote !!}</div>
        @if (!$avoir)
            <div>Option pour le paiement de la taxe d'après les débits</div>
        @endif
        <div class="terms-link">
            <span>Conditions générales de vente :</span> {{ $company['terms_url'] }}
        </div>

        <table style="width: 100%">
            <tbody>
                <tr>
                    <td style="width: 50%">
                        <div class="signature-section">
                            @if (isset($company['signature']))
                                <img src="{{ $company['signature'] }}" alt="Signature" height="100" class="signature-image">
                            @endif
                        </div>
                    </td>
                    <td style="width: 50%;"></td>
                </tr>
            </tbody>
        </table>

        <div class="footer-info">
            {{ $company['accounting'] }} | {{ $company['email'] }} | {{ $company['website'] }}
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Arial", "normal");
            $size = 8;
            $color = array(0.4, 0.4, 0.4);
            $text = "Page {PAGE_NUM} / {PAGE_COUNT}";
            $pdf->page_text(275, 805, $text, $font, $size, $color);
        }
    </script>
</body>

</html>
