<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Facture #{{ $invNo }}</title>
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

        /* Utility Classes */
        .small {
            font-size: 8pt;
        }

        .mt-10 {
            margin-top: 10px;
        }

        .member-company {
            margin-top: 6px;
            font-size: 9pt;
            color: #555;
        }

        .subrogation {
            margin-top: 10px;
            padding: 8px 10px;
            border-left: 3px solid #eb7b0f;
            background: #fdf3e8;
            font-size: 9pt;
        }
    </style>
</head>


<body>

    {{-- Watermark Acquittée - affiché uniquement si l'OPCO a réglé la facture --}}
    @if ($paidDate)
        <div class="watermark">Acquittée</div>
    @endif

    <!-- Header : client = OPCO (subrogation), puis OrthoMaster -->
    <div class="header no-break">
        <table>
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="client-info">
                        <div class="client-name">{{ $opco['name'] }}</div>
                        @if ($opco['street'])
                            <div class="small">{{ $opco['street'] }}</div>
                        @endif
                        @if ($opco['zip'] || $opco['city'])
                            <div class="small">{{ $opco['zip'] }} {{ $opco['city'] }}</div>
                        @endif
                        @if ($opco['vat'])
                            <div class="small"><strong>N° TVA :</strong> {{ $opco['vat'] }}</div>
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
    <div class="contract-title">
        Facture #{{ $invNo }}
    </div>

    <!-- Info Box -->
    <div class="info-box no-break">
        <table>
            <tr>
                <td class="info-label">Date de la facture : </td>
                <td class="info-label">Échéance : </td>
                <td class="info-label">Convention : </td>
            </tr>
            <tr>
                <td class="text-center">{{ $date }}</td>
                <td class="text-center">{{ $exp_date }}</td>
                <td class="text-center">{{ $qNo }}</td>
            </tr>
        </table>
    </div>

    <!-- Entreprise, formation and learners -->
    <div class="formation-section no-break">
        <table>
            <tr>
                <td>
                    <div class="section-title">Entreprise adhérente</div>
                    @if ($client)
                        <div><strong>{{ $client->name }}</strong></div>
                        @if ($client->address)
                            <div class="small">{{ $client->address }}</div>
                        @endif
                        @if ($client->postal_code || $client->city)
                            <div class="small">{{ $client->postal_code }} {{ $client->city }}</div>
                        @endif
                        @if ($client->siret)
                            <div class="small"><strong>N° SIRET :</strong> {{ $client->siret }}</div>
                        @endif
                    @else
                        <div>—</div>
                    @endif

                    <div class="section-title" style="margin-top: 10px;">Dates de formation</div>
                    <div>{{ $rangeDate }}</div>
                </td>
                <td>
                    <div class="learners-box">
                        <div class="section-title">Apprenants</div>
                        @foreach ($members as $member)
                            <div class="learner-item">{{ $member->name . ' ' . $member->first_name }}</div>
                        @endforeach
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <br />

    <!-- Items Table (comme le devis signé) -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 35%;">DESCRIPTION</th>
                <th class="text-right" style="width: 10%;">HEURES</th>
                <th class="text-right" style="width: 20%;">PRIX UNITAIRE</th>
                <th class="text-right" style="width: 15%;">QUANTITÉ</th>
                <th class="text-right" style="width: 20%;">MONTANT HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->package->name }}</td>
                    <td class="text-right">{{ number_format($item->package->duration / 60, 2, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format(($item->package->duration / 60) * $unitPrice, 2, ',', ' ') }} €</td>
                    <td class="text-right">{{ number_format(count($members), 2, ',', ' ') }}
                        {{ count($members) > 1 ? 'Unité(s)' : 'Unité' }}</td>
                    <td class="text-right">{{ number_format(($item->package->duration / 60) * $unitPrice * count($members), 2, ',', ' ') }} €</td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4" class="text-right">Montant hors taxes</td>
                <td class="text-right">{{ number_format($amount_ht, 2, ',', ' ') }} €</td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="text-right">TVA {{ rtrim(rtrim(number_format($tva, 2, ',', ''), '0'), ',') }}%</td>
                <td class="text-right">{{ number_format($tva_amount, 2, ',', ' ') }} €</td>
            </tr>
            <tr class="grand-total">
                <td colspan="4" class="text-right"><strong>Total</strong></td>
                <td class="text-right"><strong>{{ number_format($amount, 2, ',', ' ') }} €</strong></td>
            </tr>

            @if ($paidDate)
                <tr>
                    <td colspan="4" class="text-right">Payé le {{ $paidDate }} par virement</td>
                    <td class="text-right">{{ number_format($amount, 2, ',', ' ') }} €</td>
                </tr>
                <tr class="total-row">
                    <td colspan="4" class="text-right"><strong>Montant dû</strong></td>
                    <td class="text-right">{{ number_format(0, 2, ',', ' ') }} €</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="subrogation no-break">
        Facture émise au nom de {{ $opco['name'] }} dans le cadre de la subrogation de paiement :
        l'opérateur de compétences règle directement {{ $company['name'] }} pour le compte de
        l'entreprise adhérente{{ $client ? ' ' . $client->name : '' }}.
    </div>

    <!-- Footer -->
    <div class="footer">
        <div>Communication de paiement : <strong>{{ $invNo }}</strong></div>
        <div>Option pour le paiement de la taxe d'après les débits</div>
        <div class="terms-link">
            <span>Conditions générales de vente :</span> {{ $company['terms_url'] }}
        </div>

        <!-- Signature Section -->
        <table style="width: 100%">
            <tbody>
                <tr>
                    <td style="width: 50%">
                        <div class="signature-section">
                            @if (isset($company['signature']))
                                <img src="{{ $company['signature'] }}" alt="Signature" height="100"
                                    class="signature-image">
                            @endif
                        </div>
                    </td>
                    <td style="width: 50%; font-size: 10pt;">
                        <div><strong>Coordonnées Bancaire</strong></div>
                        <div><strong>RIB :</strong> {{ $company['rib'] }}</div>
                        <div><strong>IBAN :</strong> {{ $company['iban'] }}</div>
                        <div><strong>BIC / SWIFT :</strong> {{ $company['bic'] }}</div>
                        <div><strong>Domiciliation :</strong> {{ $company['domi'] }}</div>
                        <div><strong>Agence :</strong> {{ $company['agent'] }}</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Footer Contact Info -->
        <div class="footer-info">
            {{ $company['accounting'] }} | {{ $company['email'] }} | {{ $company['website'] }}
        </div>
    </div>

    <!-- Automatic Page Numbering Script -->
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
