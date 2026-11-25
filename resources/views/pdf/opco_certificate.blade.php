<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Certificat de réalisation</title>
    <style>
        @page {
            margin: 18mm 18mm 16mm 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10.5pt;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        .page {
            padding-bottom: 22mm; /* place de la mention en bas de page */
        }

        .page-break {
            page-break-after: always;
        }

        /* ---------- En-tête ---------- */
        .brand {
            width: 100%;
            text-align: center;
            margin-top: 4mm;
        }

        .brand img {
            vertical-align: middle;
        }

        .brand .logo {
            height: 64px;
            margin-right: 10px;
        }

        .brand .brand-name {
            height: 46px;
        }

        .title {
            margin: 9mm 0 2mm;
            text-align: center;
            font-size: 24pt;
            font-weight: bold;
            color: #111827;
            letter-spacing: 0.3px;
        }

        .title-rule {
            width: 60px;
            height: 3px;
            margin: 0 auto 9mm;
            background: #eb7b0f;
        }

        /* ---------- Corps ---------- */
        .intro {
            text-align: center;
            line-height: 1.5;
        }

        .learner-card {
            margin: 6mm 0 7mm;
            padding: 5mm 6mm;
            border: 1px solid #f3d3b3;
            border-left: 4px solid #eb7b0f;
            background: #fdf6ee;
            text-align: center;
        }

        .learner-name {
            font-size: 17pt;
            font-weight: bold;
            color: #111827;
        }

        .label {
            margin-top: 3mm;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #9a5a12;
        }

        .workplace {
            margin-top: 1mm;
            font-size: 10.5pt;
            color: #374151;
        }

        .section {
            margin-bottom: 5mm;
            line-height: 1.55;
        }

        .theme {
            font-weight: bold;
        }

        .training {
            margin-top: 2mm;
            padding: 3.5mm 5mm;
            background: #f5f6f8;
            border-radius: 3px;
            font-weight: bold;
            color: #111827;
        }

        .hl {
            font-weight: bold;
            color: #111827;
        }

        /* ---------- Signature ---------- */
        .sign {
            width: 100%;
            margin-top: 9mm;
            border-collapse: collapse;
        }

        .sign td {
            vertical-align: top;
            width: 50%;
        }

        .sign .muted {
            color: #6b7280;
        }

        .stamp {
            margin-top: 2mm;
            height: 110px;
        }

        /* ---------- Mention bas de page ---------- */
        /* Fixe : répétée en bas de chaque page (une page par apprenant) */
        .legal {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            padding-top: 3mm;
            border-top: 1px solid #e5e7eb;
            font-size: 7pt;
            line-height: 1.5;
            color: #9ca3af;
            text-align: justify;
        }
    </style>
</head>

<body>
    <div class="legal">
        Sans préjudice des délais imposés par les règles fiscales, comptables ou commerciales, je m'engage à
        conserver l'ensemble des pièces justificatives qui ont permis d'établir le présent certificat pendant
        une durée de 3 ans à compter de la fin de l'année du dernier paiement. En cas de cofinancement des
        fonds européens la durée de conservation est étendue conformément aux obligations conventionnelles
        spécifiques.
    </div>

    @foreach ($learners as $learner)
        <div class="page {{ $loop->last ? '' : 'page-break' }}">

            <div class="brand">
                <img class="logo" src="{{ $logo }}" alt="OrthoMaster">
                <img class="brand-name" src="{{ $brand }}" alt="OrthoMaster">
            </div>

            <div class="title">Certificat de réalisation</div>
            <div class="title-rule"></div>

            <div class="intro">
                Je soussigné <span class="hl">{{ $signatory }}</span> agissant en tant que responsable
                de l'organisme de formation <span class="hl">ORTHOMASTER</span> atteste que :
            </div>

            <div class="learner-card">
                <div class="learner-name">{{ $learner }}</div>
                <div class="label">travaillant au sein de</div>
                <div class="workplace">{{ $workplace }}</div>
            </div>

            <div class="section">
                <div class="theme">a suivi la formation selon la thématique S10-007 - Orthopédie :</div>
                <div class="training">
                    ORTHOMASTER : Une formation pour bien délivrer les Dispositifs Médicaux de petit appareillage
                    orthopédique en pharmacie d'officine.
                </div>
            </div>

            <div class="section">
                Cette formation s'est déroulée du <span class="hl">{{ $from }}</span>
                au <span class="hl">{{ $to }}</span> pour une durée de
                <span class="hl">{{ $hours }} {{ $hoursLabel }}</span> effectuées au sein de l'officine
                et sur le temps de travail.
            </div>

            <table class="sign">
                <tr>
                    <td>
                        <div><span class="hl">Fait à</span> {{ $place }}</div>
                        <div><span class="hl">Le</span> {{ $signedOn }}</div>
                    </td>
                    <td>
                        <div class="hl">Cachet et signature de l'organisme :</div>
                        @if (!empty($company['signature']))
                            <img class="stamp" src="{{ $company['signature'] }}" alt="Cachet ORTHOMASTER">
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>

</html>
