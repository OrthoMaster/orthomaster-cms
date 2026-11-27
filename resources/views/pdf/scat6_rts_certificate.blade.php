<!DOCTYPE html>
<html lang="fr">

{{--
    Certificat médical de non contre-indication à la reprise après commotion cérébrale.
    Variables : voir Scat6RtsController::certificate().

    Imprimé, puis signé et tamponné à la main (un certificat médical doit porter la signature et
    l'identification du médecin). L'application ne garde qu'un nom libre et l'année de naissance :
    l'identité du patient, la date d'examen et le lieu restent à remplir à la main.
--}}
@use('App\Support\Scat6\Scat6Content')
@php
    $tz = 'Europe/Paris';
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->timezone($tz)->format('d/m/Y') : '';
    $sport = Scat6Content::SPORTS[$patient->sport] ?? $patient->sport;
    // « la pratique du judo », « du hockey sur glace » ; sport général : discipline non nommée.
    $sportName = ['football' => 'du football', 'judo' => 'du judo', 'hockey_glace' => 'du hockey sur glace'][$patient->sport] ?? 'de la discipline pratiquée';
    $contact = $type === 'contact';
    $earliest = $minDays !== null ? \Illuminate\Support\Carbon::parse($rts->injury_at)->addDays($minDays) : null;
@endphp

<head>
    <meta charset="UTF-8">
    <title>Certificat de reprise après commotion cérébrale</title>
    <style>
        @page { size: A4; margin: 16mm 16mm 18mm 16mm; } /* bas : place du pied de page (Scat6PdfFooter) */

        /* Pas « * » : avec dompdf, « * { margin: 0 } » touche aussi la racine et annule les marges de @page. */
        body, body * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.6pt;
            color: #1f2433;
            line-height: 1.45;
        }

        /* --- Médecin et cachet --- */
        .top { width: 100%; border-collapse: collapse; }
        .top td { vertical-align: top; }
        .doctor .name { font-size: 11pt; font-weight: bold; }
        .doctor .line { margin-top: 5px; color: #4a5066; }
        .stamp {
            width: 62mm;
            height: 32mm;
            border: 1px dashed #9aa2b5;
            border-radius: 4px;
            text-align: center;
            color: #9aa2b5;
            font-size: 8pt;
            padding-top: 2mm;
        }

        /* --- Titre --- */
        .title {
            margin: 9mm 0 2mm;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.3px;
            color: #1d3470;
        }
        .subtitle {
            text-align: center;
            font-size: 10.5pt;
            font-weight: bold;
            color: #3b72b9;
            margin-bottom: 7mm;
        }

        /* --- Corps --- */
        p { margin-bottom: 3.2mm; text-align: justify; }
        .fill { border-bottom: 1px dotted #4a5066; display: inline-block; min-width: 40mm; }
        .fill.short { min-width: 26mm; }
        .fill.long { min-width: 70mm; }
        .ident { width: 100%; border-collapse: collapse; margin: 1mm 0 4mm; }
        .ident td { padding: 2.2mm 0; }
        .statement {
            border-left: 3px solid #5696df;
            background: #f0f5ff;
            padding: 3mm 4mm;
            margin: 2mm 0 5mm;
            font-size: 10.2pt;
        }

        /* --- Protocole --- */
        h2 { font-size: 9.6pt; color: #1d3470; margin: 0 0 2mm; }
        table.steps { width: 100%; border-collapse: collapse; font-size: 8.6pt; }
        table.steps th { text-align: left; background: #e8f1fc; color: #2d55af; padding: 1.6mm 2mm; }
        table.steps td { padding: 1.5mm 2mm; border-bottom: 1px solid #e1e8f0; }
        table.steps .n { width: 9mm; text-align: center; }
        table.steps .d { width: 28mm; text-align: right; white-space: nowrap; }

        .routing {
            margin-top: 4mm;
            padding: 2.5mm 3.5mm;
            border: 1px solid #c9dcf4;
            border-radius: 4px;
            font-size: 8.6pt;
            color: #33394d;
        }

        /* --- Signature --- */
        .sign { width: 100%; border-collapse: collapse; margin-top: 7mm; }
        .sign td { vertical-align: top; }
        .signbox {
            height: 30mm;
            border: 1px solid #9aa2b5;
            border-radius: 4px;
            padding: 2mm 3mm;
            color: #9aa2b5;
            font-size: 8pt;
        }
        .footer { margin-top: 6mm; font-size: 7pt; color: #9aa2b5; text-align: center; }
    </style>
</head>

<body>
    {{-- --- Médecin (à compléter ou tamponner) --- --}}
    <table class="top">
        <tr>
            <td class="doctor">
                <div class="name">Dr {!! $doctor ? e($doctor) : '<span class="fill long"></span>' !!}</div>
                <div class="line">N° RPPS : <span class="fill"></span></div>
                <div class="line">Adresse : <span class="fill long"></span></div>
                <div class="line">Téléphone : <span class="fill"></span></div>
            </td>
            <td style="width: 64mm;">
                <div class="stamp">Cachet du médecin</div>
            </td>
        </tr>
    </table>

    <div class="title">CERTIFICAT MÉDICAL DE NON CONTRE-INDICATION<br>À LA REPRISE APRÈS COMMOTION CÉRÉBRALE</div>
    <div class="subtitle">{{ $contact ? 'Reprise de l’entraînement avec contact' : 'Reprise de la compétition' }}</div>

    <p>Je soussigné(e), Dr {!! $doctor ? e($doctor) : '<span class="fill long"></span>' !!}, docteur en médecine, certifie avoir examiné le <span class="fill short"></span> :</p>

    <table class="ident">
        <tr>
            <td>Nom : <span class="fill long"></span></td>
            <td>Prénom : <span class="fill"></span></td>
        </tr>
        <tr>
            <td>Né(e) le : <span class="fill short"></span></td>
            <td>N° de licence : <span class="fill"></span></td>
        </tr>
        <tr>
            <td>Sport : <b>{{ $sport }}</b></td>
            <td>Club : <span class="fill"></span></td>
        </tr>
    </table>

    <p>victime d’une commotion cérébrale le <b>{{ $fmt($rts->injury_at) }}</b>, qui a suivi le protocole de reprise progressive ci-dessous.</p>

    <div class="statement">
        @if ($contact)
            À ce jour, l’intéressé(e) ne présente pas de signe clinique de commotion cérébrale, y compris à l’effort,
            et ne présente pas de contre-indication à la <b>reprise de l’entraînement avec contact</b>
            (étape 5 du protocole). Le retour à la compétition reste soumis à un nouvel avis médical.
        @else
            À ce jour, l’intéressé(e) ne présente pas de signe clinique de commotion cérébrale, y compris à l’effort,
            et ne présente pas de contre-indication à la <b>pratique {{ $sportName }} en compétition</b>.
        @endif
    </div>

    {{-- --- Protocole suivi --- --}}
    <h2>Protocole de reprise progressive (consensus d’Amsterdam 2022)</h2>
    <table class="steps">
        <tr>
            <th class="n">Étape</th>
            <th>Activité</th>
            <th class="d">Début</th>
        </tr>
        @foreach ($steps as $n => $step)
            @php $started = $rts->steps[(string) $n]['started_at'] ?? null; @endphp
            @if (! $contact || $n <= 5)
                <tr>
                    <td class="n">{{ $n }}</td>
                    <td>{{ $step['title'] }}</td>
                    <td class="d">{{ $started ? $fmt($started) : '—' }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <div class="routing">
        <b>Fédération — {{ $sport }}.</b> {{ $routing }}
        @if ($earliest)
            <br>Délai minimal avant la compétition : {{ $minDays }} jours après le choc (au plus tôt le {{ $earliest->format('d/m/Y') }}).
        @endif
    </div>

    <p style="margin-top: 5mm;">Certificat établi à la demande de l’intéressé(e) et remis en main propre pour servir et valoir ce que de droit.</p>

    {{-- --- Lieu, date, signature --- --}}
    <table class="sign">
        <tr>
            <td style="width: 50%; padding-top: 3mm;">
                Fait à <span class="fill"></span><br><br>
                le <span class="fill short"></span>
            </td>
            <td>
                <div class="signbox">Signature du médecin</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Réf. dossier : {{ $patient->name }} · suivi n° {{ $rts->id }} · document édité le {{ now()->timezone($tz)->format('d/m/Y') }} via OrthoMaster (SCAT6)
    </div>
</body>

</html>
