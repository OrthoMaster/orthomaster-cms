<!DOCTYPE html>
<html lang="fr">

{{--
    Rapport d'un examen SCAT6 signé (Outils › SCAT6).
    Variables : voir Scat6Controller::pdf().
    Aucun seuil, aucun verdict automatique : les scores sont posés à côté de la baseline.
--}}
@use('App\Support\Scat6\Scat6Content')
@php
    $data = $assessment->data ?? [];
    $answers = $data['answers'] ?? [];
    $tests = $data['tests'] ?? [];
    $skipped = $data['skipped'] ?? [];
    $summary = $assessment->summary ?? [];
    $tz = 'Europe/Paris';
    $date = fn ($d, $f = 'd/m/Y à H:i') => $d ? \Illuminate\Support\Carbon::parse($d)->timezone($tz)->format($f) : '—';
    // Écarts : « +3 » / « −2 », vert si meilleur, rouge si moins bon (aucun seuil).
    $deltaText = fn ($d) => $d === null ? '—' : ($d > 0 ? '+' . $d : (string) $d);
    $deltaClass = fn ($d, $row) => ($d === null || $d == 0) ? '' : ((($d > 0) === (bool) ($row['worse_if_higher'] ?? false)) ? 'worse' : 'better');
    $previous = $previous ?? null;
    $previousLabel = $previousLabel ?? null;
    // Choc : à minuit pile (heure de Paris) = heure inconnue, on n'affiche que le jour ; sinon jour, heure et délai (H+5, J+2).
    $injury = '—';
    if ($assessment->injury_at) {
        $choc = \Illuminate\Support\Carbon::parse($assessment->injury_at)->timezone($tz);
        $at = \Illuminate\Support\Carbon::parse($assessment->started_at ?? $assessment->completed_at ?? now())->timezone($tz);
        if ($choc->format('H:i:s') === '00:00:00') {
            $injury = $choc->format('d/m/Y').' · examen à J+'.max(0, (int) round($choc->copy()->startOfDay()->diffInDays($at->copy()->startOfDay())));
        } else {
            $hours = (int) floor(max(0, $choc->diffInMinutes($at)) / 60);
            $injury = $choc->format('d/m/Y à H:i').' · examen à '.($hours < 48 ? 'H+'.$hours : 'J+'.intdiv($hours, 24));
        }
    }
    $val = fn ($v, $suffix = '') => $v === null ? '—' : $v . $suffix;
    $answer = function ($code, $yes = 'Oui', $no = 'Non') use ($answers) {
        $a = $answers[$code] ?? null;
        if (! $a || ($a['status'] ?? null) !== 'done') {
            return '<span class="muted">—</span>';
        }
        if ($a['value'] === true) {
            return '<b>' . e($yes) . '</b>';
        }
        if ($a['value'] === false) {
            return e($no);
        }
        return e($a['value']);
    };
    $memory = $tests['memory'] ?? [];
    $words = Scat6Content::WORD_LISTS[$memory['list'] ?? 'A'] ?? [];
    $digits = $tests['digits'] ?? [];
    $months = $tests['months'] ?? [];
    $mbess = $tests['mbess'] ?? [];
    $tandem = $tests['tandem'] ?? [];
    $delayed = $tests['delayed'] ?? [];
    $isBaseline = $assessment->exam_kind === 'baseline';
    $showConsignes = in_array($assessment->diagnosis, ['oui', 'differe'], true);
@endphp

<head>
    <meta charset="UTF-8">
    <title>SCAT6 — {{ $patient->name }}</title>
    <style>
        @page { size: A4; margin: 14mm 12mm 18mm 12mm; } /* bas : place du pied de page (Scat6PdfFooter) */

        /* Pas « * » : avec dompdf, « * { margin: 0 } » touche aussi la racine et annule les marges de @page. */
        body, body * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.6pt;
            color: #32343E;
            line-height: 1.35;
        }

        /* --- En-tête --- */
        .banner { background: #5696df; color: #fff; padding: 10px 14px; border-radius: 6px; }
        .banner table { width: 100%; border-collapse: collapse; }
        .banner .title { font-size: 16pt; font-weight: bold; }
        .banner .subtitle { font-size: 9pt; opacity: 0.9; }
        .banner .right { text-align: right; font-size: 8.5pt; }

        /* --- Blocs --- */
        h2 {
            font-size: 10.5pt;
            color: #2D55AF;
            margin: 12px 0 5px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #BCD1FF;
        }
        .muted { color: #8a8fa3; }
        .info { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .info td { padding: 3px 6px; vertical-align: top; width: 25%; }
        .info .label { font-size: 7.5pt; color: #8a8fa3; text-transform: uppercase; }
        .info .value { font-weight: bold; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th {
            background: #F0F5FF;
            color: #2D55AF;
            font-size: 7.8pt;
            text-align: left;
            padding: 4px 6px;
            border-bottom: 1px solid #BCD1FF;
        }
        table.grid td { padding: 3.5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.grid .num { text-align: right; white-space: nowrap; }
        table.grid tr.total td { font-weight: bold; background: #F7F9FF; }

        .cols { width: 100%; border-collapse: collapse; }
        .cols > tbody > tr > td { width: 50%; vertical-align: top; }
        .cols > tbody > tr > td:first-child { padding-right: 8px; }
        .cols > tbody > tr > td:last-child { padding-left: 8px; }

        .notice { padding: 6px 9px; border-radius: 4px; margin-top: 6px; background: #F0F5FF; border-left: 3px solid #5696df; }
        .notice.warning { background: #FFF8E1; border-left-color: #F9A825; }
        .notice.danger { background: #FDECEA; border-left-color: #E53935; }
        .notice.success { background: #E8F5E9; border-left-color: #43A047; }

        .decision { font-size: 11pt; font-weight: bold; }
        ul { margin: 2px 0 0 14px; }
        li { margin-bottom: 2px; }

        .signature { margin-top: 10px; padding: 8px 10px; border: 1px solid #BCD1FF; border-radius: 4px; }
        td.better { color: #2e7d32; font-weight: bold; }
        td.worse { color: #c62828; font-weight: bold; }
        .footer { margin-top: 12px; font-size: 7pt; color: #8a8fa3; text-align: center; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>

<body>
    {{-- --- En-tête --- --}}
    <div class="banner">
        <table>
            <tr>
                <td>
                    <div class="title">SCAT6 — Rapport d’examen</div>
                    <div class="subtitle">{{ Scat6Content::EXAM_KINDS[$assessment->exam_kind] ?? $assessment->exam_kind }}</div>
                </td>
                <td class="right">
                    OrthoMaster · Outils<br>
                    Signé le {{ $date($assessment->completed_at) }}
                </td>
            </tr>
        </table>
    </div>

    <table class="info">
        <tr>
            <td><div class="label">Patient</div><div class="value">{{ $patient->name }}</div></td>
            <td><div class="label">Année de naissance</div><div class="value">{{ $patient->birth_year }}@if($age !== null) <span class="muted">(≈ {{ $age }} ans)</span>@endif</div></td>
            <td><div class="label">Sport</div><div class="value">{{ Scat6Content::SPORTS[$patient->sport] ?? $patient->sport }}</div></td>
            <td><div class="label">{{ $isBaseline ? 'Examen' : 'Date du choc' }}</div><div class="value">{{ $isBaseline ? $date($assessment->started_at, 'd/m/Y') : $injury }}</div></td>
        </tr>
    </table>

    {{-- --- Résumé --- --}}
    <h2>Résumé {{ $isBaseline ? '' : ($previous ? '— évolution et comparaison avec la baseline' : '— comparaison avec la baseline') }}</h2>
    <table class="grid">
        <tr>
            <th>Domaine</th>
            <th class="num">Actuel</th>
            @if ($previous)
                <th class="num">Précédent ({{ $previousLabel }})</th>
                <th class="num">Évolution</th>
            @endif
            @unless($isBaseline)
                <th class="num">Baseline</th>
                <th class="num">Écart</th>
            @endunless
        </tr>
        @foreach ($rows as $row)
            <tr @if($row['key'] === 'cognitive_total') class="total" @endif>
                <td>{{ $row['label'] }}</td>
                <td class="num">
                    @if ($row['current'] === null)
                        <span class="muted">non réalisé</span>
                    @else
                        {{ $row['current'] }}{{ $row['unit'] ? ' ' . $row['unit'] : ($row['max'] ? ' / ' . $row['max'] : '') }}
                    @endif
                </td>
                @if ($previous)
                    <td class="num">{{ $val($row['previous']) }}</td>
                    <td class="num {{ $deltaClass($row['prevDelta'], $row) }}">{{ $deltaText($row['prevDelta']) }}</td>
                @endif
                @unless($isBaseline)
                    <td class="num">{{ $val($row['baseline']) }}</td>
                    <td class="num {{ $deltaClass($row['delta'], $row) }}">{{ $deltaText($row['delta']) }}</td>
                @endunless
            </tr>
        @endforeach
    </table>
    @if (! $isBaseline && ! $baseline)
        <div class="notice warning">Aucune baseline signée pour ce patient : interprétation clinique sans comparaison.</div>
    @elseif ($baseline && $baseline->word_list !== $assessment->word_list)
        <div class="notice">Liste de mots différente de la baseline ({{ $baseline->word_list }}) : les listes SCAT6 sont équivalentes.</div>
    @endif
    @if ($previous)
        <div class="muted" style="margin-top: 4px;">Évolution : depuis l’examen précédent du même choc ({{ $previousLabel }}, {{ $date($previous->started_at ?? $previous->completed_at) }}).</div>
    @endif
    @if ($baseline)
        <div class="muted" style="margin-top: 4px;">Baseline du {{ $date($baseline->completed_at, 'd/m/Y') }} · liste {{ $baseline->word_list }}</div>
    @endif
    @if (! empty($skipped))
        <div class="notice warning">
            Non réalisé :
            @foreach ($skipped as $step => $reason)
                {{ Scat6Content::STEPS[$step] ?? $step }} ({{ $reason }})@if(! $loop->last), @endif
            @endforeach
        </div>
    @endif

    {{-- --- Étape 1 et 2 --- --}}
    <table class="cols">
        <tr>
            <td>
                <h2>1 · Renseignements</h2>
                <table class="grid">
                    @foreach (Scat6Content::BACKGROUND as $code => $label)
                        <tr><td>{{ $label }}</td><td class="num">{!! $answer($code) !!}</td></tr>
                    @endforeach
                </table>
                @if (! empty($data['background_notes']))
                    <div class="notice">{{ $data['background_notes'] }}</div>
                @endif

                <h2>3 · Orientation</h2>
                <table class="grid">
                    @foreach (Scat6Content::ORIENTATION as $code => $label)
                        <tr><td>{{ $label }}</td><td class="num">{!! $answer($code, 'Correct', 'Incorrect') !!}</td></tr>
                    @endforeach
                    <tr class="total"><td>Orientation</td><td class="num">{{ $val($summary['orientation'] ?? null, ' / 5') }}</td></tr>
                </table>
            </td>
            <td>
                <h2>2 · Symptômes {{ $isBaseline ? '(habituellement)' : '(maintenant)' }}</h2>
                <table class="grid">
                    @foreach (Scat6Content::SYMPTOMS as $code => $label)
                        @php $s = $answers[$code] ?? null; @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="num">{!! $s && ($s['status'] ?? null) === 'done' ? ((int) $s['value'] > 0 ? '<b>' . (int) $s['value'] . '</b>' : '0') : '<span class="muted">—</span>' !!}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Nombre · sévérité</td>
                        <td class="num">{{ $val($summary['symptom_count'] ?? null, '/22') }} · {{ $val($summary['symptom_severity'] ?? null, '/132') }}</td>
                    </tr>
                </table>
                @unless ($isBaseline)
                    <table class="grid" style="margin-top: 4px;">
                        <tr><td>Aggravés par l’activité physique ?</td><td class="num">{!! $answer('S.phys') !!}</td></tr>
                        <tr><td>Aggravés par l’activité mentale ?</td><td class="num">{!! $answer('S.ment') !!}</td></tr>
                        <tr><td>Se sent normal à</td><td class="num">{{ isset($data['percent_normal']) && $data['percent_normal'] !== null ? $data['percent_normal'] . ' %' : '—' }}</td></tr>
                    </table>
                @endunless
            </td>
        </tr>
    </table>

    {{-- --- Étape 3 : mémoire et concentration --- --}}
    <div class="avoid-break">
        <h2>3 · Mémoire immédiate et concentration</h2>
        <table class="grid">
            <tr>
                <th>Liste {{ $memory['list'] ?? '—' }}</th>
                <th class="num">Essai 1</th>
                <th class="num">Essai 2</th>
                <th class="num">Essai 3</th>
                <th class="num">Différée</th>
            </tr>
            @foreach ($words as $word)
                <tr>
                    <td>{{ $word }}</td>
                    @foreach ([0, 1, 2] as $i)
                        <td class="num">{{ in_array($word, $memory['trials'][$i] ?? [], true) ? '✓' : '' }}</td>
                    @endforeach
                    <td class="num">{{ in_array($word, $delayed['recalled'] ?? [], true) ? '✓' : '' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                @foreach ([0, 1, 2] as $i)
                    <td class="num">{{ count($memory['trials'][$i] ?? []) }}/10</td>
                @endforeach
                <td class="num">{{ $val($summary['delayed'] ?? null, '/10') }}</td>
            </tr>
        </table>
        <div class="muted" style="margin-top: 3px;">
            Mémoire immédiate : {{ $val($summary['immediate'] ?? null, '/30') }}
            @if (! empty($memory['endedAt'])) · fin du 3e essai à {{ $date($memory['endedAt'], 'H:i') }} @endif
            @if (! empty($delayed['early']['remainingS']))
                · mémoire différée commencée avant 5 min (il restait {{ intdiv((int) $delayed['early']['remainingS'], 60) }}:{{ str_pad((string) ((int) $delayed['early']['remainingS'] % 60), 2, '0', STR_PAD_LEFT) }}), décision du médecin
            @endif
        </div>

        <table class="grid" style="margin-top: 6px;">
            <tr>
                <td>Chiffres à l’envers (liste {{ $digits['list'] ?? '—' }})</td>
                <td class="num">
                    @php
                        $passed = 0;
                        foreach (array_slice($digits['results'] ?? [], 0, 4) as $r) {
                            if (in_array(true, $r, true)) { $passed++; } else { break; }
                        }
                    @endphp
                    {{ empty($digits['results']) ? '—' : $passed . '/4' }}
                </td>
            </tr>
            <tr>
                <td>Mois à l’envers</td>
                <td class="num">
                    @if (! empty($months['done']))
                        {{ $months['seconds'] }} s · {{ (int) ($months['errors'] ?? 0) }} erreur(s) → {{ ((int) ($months['errors'] ?? 1) === 0 && (float) $months['seconds'] < 30) ? 1 : 0 }}/1
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr class="total"><td>Concentration</td><td class="num">{{ $val($summary['concentration'] ?? null, '/5') }}</td></tr>
        </table>
    </div>

    {{-- --- Étape 4 : équilibre --- --}}
    <div class="avoid-break">
        <h2>4 · Équilibre</h2>
        <table class="grid">
            <tr>
                <th>mBESS (erreurs) — pied testé : {{ ['gauche' => 'gauche', 'droite' => 'droit'][$mbess['foot'] ?? ''] ?? '—' }}</th>
                <th class="num">Sol ferme</th>
                <th class="num">Sol instable</th>
            </tr>
            @foreach (Scat6Content::MBESS_STANCES as $i => $stance)
                <tr>
                    <td>{{ $stance }}</td>
                    <td class="num">{{ $val($mbess['firm'][$i] ?? null) }}</td>
                    <td class="num">{{ is_array($mbess['foam'] ?? null) ? $val($mbess['foam'][$i] ?? null) : '—' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="num">{{ $val($summary['mbess'] ?? null, '/30') }}</td>
                <td class="num">{{ $val($summary['mbess_foam'] ?? null, '/30') }}</td>
            </tr>
        </table>
        <table class="grid" style="margin-top: 6px;">
            <tr>
                <td>Marche sur une ligne (3 m)</td>
                <td class="num">
                    @if (! empty($tandem['done']))
                        @foreach (($tandem['trials'] ?? []) as $t)
                            {{ $t === null ? '—' : ($t < 0 ? 'échec' : $t . ' s') }}@if(! $loop->last) · @endif
                        @endforeach
                    @else
                        <span class="muted">{{ ($mbess['normal'] ?? null) === false ? 'non réalisée (équilibre anormal)' : 'non réalisée' }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Double tâche (essai retenu : moins d'erreurs, puis plus rapide)</td>
                <td class="num">
                    {{ $val($tandem['dualBest'] ?? null, ' s') }} · {{ $val($tandem['dualErrors'] ?? null) }} erreur(s)
                    @if (isset($tandem['dualBestTrial']) && $tandem['dualBestTrial'] !== null) (essai {{ (int) $tandem['dualBestTrial'] + 1 }}) @endif
                </td>
            </tr>
            @if (! empty($tandem['dualTrials']))
                <tr>
                    <td class="muted">Double tâche, essai par essai</td>
                    <td class="num muted">
                        @foreach ($tandem['dualTrials'] as $i => $t)
                            @if ($t !== null)
                                {{ $i + 1 }} : {{ $t < 0 ? 'échec' : $t.' s' }}, {{ count($tandem['dualWrongByTrial'][$i] ?? []) }} err.@if (! $loop->last) · @endif
                            @endif
                        @endforeach
                    </td>
                </tr>
            @endif
        </table>
    </div>

    {{-- --- Étape 6 : décision --- --}}
    <div class="avoid-break">
        <h2>6 · Décision</h2>
        @if ($isBaseline)
            <div class="notice success decision">Baseline enregistrée</div>
        @else
            <table class="grid">
                @if ($assessment->neuro)
                    <tr><td>Examen neurologique</td><td class="num">{{ Scat6Content::NEURO[$assessment->neuro] ?? $assessment->neuro }}</td></tr>
                @endif
                <tr>
                    <td>Commotion cérébrale diagnostiquée</td>
                    <td class="num decision">{{ Scat6Content::DIAGNOSIS[$assessment->diagnosis] ?? '—' }}</td>
                </tr>
            </table>
        @endif
        @if ($assessment->notes)
            <div class="notice"><b>Notes cliniques :</b> {!! nl2br(e($assessment->notes)) !!}</div>
        @endif
        <div class="notice">{{ Scat6Content::DISCLAIMER }}</div>
    </div>

    @if ($showConsignes)
        <div class="avoid-break">
            <h2>Consignes après une commotion</h2>
            <table class="cols">
                <tr>
                    <td>
                        <b>Les 48 prochaines heures</b>
                        <ul>@foreach (Scat6Content::CONSIGNES['surveillance'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                        <div class="notice warning">
                            <b>Interdit sans avis médical</b>
                            <ul>@foreach (Scat6Content::CONSIGNES['interdits'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                        </div>
                        <div class="notice danger">
                            <b>Appelez le 15 / 112 si</b>
                            <ul>@foreach (Scat6Content::CONSIGNES['appeler_si'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                        </div>
                    </td>
                    <td>
                        <b>Reprise — {{ Scat6Content::SPORTS[$patient->sport] ?? $patient->sport }}</b>
                        <ul>@foreach ($returnLines as $line)<li>{{ $line }}</li>@endforeach</ul>
                        @if ($assessment->diagnosis === 'differe')
                            <div class="notice">En cas de doute : re-tester le lendemain ou avant 72 h.</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="signature avoid-break">
        <div>{{ Scat6Content::ATTESTATION }}</div>
        <div style="margin-top: 4px;"><b>Dr {{ $assessment->signed_by }}</b> · {{ $date($assessment->completed_at) }}</div>
    </div>

    <div class="footer">
        SCAT6 © Concussion in Sport Group 2023 · Version du contenu {{ $assessment->content_version }} · Examen {{ $assessment->uuid }}
    </div>
</body>

</html>
