<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Programme de formation</title>
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
            font-size: 60pt;
            font-weight: bold;
            color: rgba(255, 150, 0, 0.1);
            white-space: nowrap;
            pointer-events: none;
            z-index: 1000;
            letter-spacing: 8px;
            text-transform: uppercase;
            border: 8px solid rgba(255, 150, 0, 0.1);
            border-radius: 10px;
            user-select: none;
        }

        /* Header Section */
        .header {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 15px;
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

        /* Contract Number */
        .programme-title {
            font-size: 20pt;
            font-weight: bold;
            letter-spacing: 0.4;
            color: #ff9600;
            margin-bottom: 10px;
        }

        /* Formation Section */
        .section-title {
            font-weight: bold;
            margin-top: 12px;
            margin-bottom: 2px;
            font-size: 12pt;
        }

        .section-content {
            font-size: 10pt;
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
            border: 1px solid #1976D2;
            color: white;
            padding: 8px 4px;
            font-weight: bold;
            font-size: 8pt;
            border: none;
        }

        .items-table td {
            padding: 6px 6px 8px 6px;
            border-bottom: 1px solid #ddd;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            font-size: 9pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: top;
        }

        .items-table tbody tr:last-child td {
            /* border-bottom: none; */
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

        /* Prevent page breaks */
        .no-break {
            page-break-inside: avoid;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 15px;
        }

        .signature-section {
            margin-top: 20px;
            padding-left: 10px;
        }

        .signature-image {
            height: 100px;
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

        .signature-placeholder {
            margin-top: 15px;
            height: 30px;
            border: 3px dashed #ddd;
            background-color: #f5f5f5;
            border-radius: 16px;
            color: #ddd;
            text-align: center;
            padding: 20px;
        }
        .text-placeholder {
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="watermark">OrthoMaster</div>

    <!-- Header -->
    <div class="header no-break">
        <table>
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="client-info">
                        <img src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/logo.png"
                            alt="OrthoMaster" height="120" class="">
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
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="programme-title">
        PROGRAMME DE FORMATION
    </div>

    <!-- Content -->
    <div class="section-title">Intitulé de la formation</div>
    <div class="section-content">Formation FOAD ORTHOMASTER / {{ $group->pluck('package.name')->implode(' ; ') }}.
    </div>

    <div class="section-title">Formation de {{ number_format($duration / 60, 2, ',', ' ') }} heures
        Objectifs de la formation</div>
    <div class="section-content">Renforcer les connaissances des principes orthopédiques et améliorer le conseil et la
        prescription des
        Dispositifs Médicaux orthopédiques (orthèses et attelles de série) définis dans le Titre II de la Liste des
        Produits et des Prestations (LPP) de la caisse nationale d’assurance maladie (CNAM).
    </div>

    <div class="section-title">Public concerné</div>
    <div class="section-content">Pharmacien, préparateur en pharmacie, étudiant en contrat avec la pharmacie, magasin de
        matériel médical
    </div>

    <div class="section-title">Prérequis</div>
    <div class="section-content">Pas de prérequis orthopédique particulier si ce n’est la pratique de l’orthopédie au
        quotidien dans
        le lieu d’exercice.
    </div>

    <div class="section-title">Nom des rédacteurs</div>
    <div class="section-content">
        <ul style="padding-left: 30px">
            <li>LÊ Guillaume, <i>orthoprothésiste et formateur</i></li>
            <li>KOHL Sabrina, <i>kinésithérapeute ostéopathe</i></li>
            <li>Pr Jacques RODINEAU, <i>médecin de physique et de réadaptation, fondateur du diplôme
                    universitaire de médecine du sport et d’orthopédie</i></li>
            <li>Dr Paul TORNER, <i>pharmacien, directeur DU orthopédie de Dijon</i></li>
            <li>BATTISTINI Laetitia, <i>ergothérapeute, orthopédiste-orthésiste</i></li>
        </ul>
    </div>

    <div class="section-title">Moyens pédagogiques</div>
    <div class="section-content">ORTHOMASTER est créé pour un format digital : smartphone, tablette, ordinateur.</div>
    <div class="section-content">
        On a donc la formation à portée de main : c’est le principe de l’Embed Learning (HAS, E-learning Guide de
        conception de formation ouverte et à distance dans le monde de la santé).
        La formation de <b>{{ number_format($duration / 60, 2, ',', ' ') }}</b> heures est répartie en <b>{{ $totalModules }}</b> modules (principales thématiques de l’orthopédie en
        officine).
        Chaque module se divise en micro-leçons de 2 à 5 minutes chacune (microlearning).
        Un outil digital interactif inclut dans l’application de formation permet d’entrer directement avec l’un des
        accompagnants pédagogiques pour toute question médico-technique et toute évaluation d’un besoin
        complémentaire.
    </div>

    <div class="section-title">Type d’évaluation pratiquée</div>
    <div class="section-content" style="padding-left: 10px">ÉVALUATION DES CONNAISSANCES</div>
    <div class="section-content">
        <ul style="padding-left: 30px">
            <li>Évaluation initiale obligatoire.</li>
            <li>Contrôle continu au fil des leçons par un système de quiz interactif.</li>
            <li>Interactions avec les tuteurs pédagogiques - Contrôle final sur l’ensemble des sujets.</li>
        </ul>
    </div>
    <div class="section-content" style="padding-left: 10px; padding-top: 10px">ÉVALUATION DE LA QUALITÉ DE L’ACTION
    </div>
    <div class="section-content">
        <ul style="padding-left: 30px">
            <li>Un questionnaire est envoyé au participant en fin de période d’accès.</li>
            <li> Le tableau de bord de l’application fournit des détails objectifs sur le participant :
                Temps passé, Scores obtenus, Régularité d’accès...</li>
            <li>Les interactions avec les stagiaires permettent une amélioration continue des pratiques et des contenus
                dont certains peuvent s’ajouter en bonus selon les besoins estimés.</li>
        </ul>
    </div>

    <div class="section-title">Sanction</div>
    <div class="section-content">À la fin de la formation, le participant reçoit une attestation de formation valable
        pour le DPC.</div>

    <div style="height: 55px;"></div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 20%;">SÉQUENCE</th>
                <th style="max-width: 10%;">
                    <div>DURÉE</div>
                    <div>(minutes)</div>
                </th>
                <th style="width: 30%;">OBJECTIF PÉDAGOGIQUE</th>
                <th style="width: 40%;">CONTENU</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($group as $item)
                @if (sizeof($group) > 1)
                <tr>
                    <td colspan="4" style="background-color: #ddd;"><b>{{ $item->package->name }}</b></td>
                </tr>
                @endif
                @foreach ($item->package->modules as $module)
                    <tr>
                        <td>{{ $module->name }}</td>

                        <td class="text-center">{{ $module->duration }}</td>
                        <td>{{ $module->description }}</td>
                        <td>
                            @foreach ($module->lessons as $lesson)
                            <div>{{ $lesson->title }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            @endforeach

        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <div>Je soussigné @if ($companyName)<strong>{{ $companyName }}</strong>@else<b>[ ]</b>@endif</div>
        <div>Responsable légal de l'établissement : @if($companyName)<strong>{{ $name }}</strong>@else<b>[ ]</b>@endif</div>
        <br>
        <div>Certifie avoir pris connaissance du présent programme de formation.</div>

        <!-- Signature Section -->
        <table style="width: 100%">
            <tbody>
                <tr>
                    <td style="width: 20%">
                        <div>Fait à : <strong>{{ $location }}</strong></div>
                    </td>
                    <td style="width: 20%">
                        <div>Le : <strong>{{ $date }}</strong></div>
                    </td>
                    <td style="">
                        <div class="signature-section">
                            @if ($signatureLink)
                            <img src="{{ $signatureLink }}" alt="Client signature" height="100" />
                            @else
                            <div class="signature-placeholder">Votre signature</div>
                            @endif
                        </div>
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
