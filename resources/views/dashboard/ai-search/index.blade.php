@extends('voyager::master')

@section('page_title', 'Assistant IA · Tableau de bord')

@php
    $nf = fn ($n, $d = 0) => number_format((float) $n, $d, ',', ' ');
    // Petits montants : 4 décimales, sinon 0,0012 € s'afficherait 0,00 €.
    $eur = fn ($n) => number_format((float) $n, $n < 1 ? 4 : 2, ',', ' ') . ' €';

    $actionLabels = [
        'ask'               => 'Question de précision',
        'ask_edition'       => 'Choix de l\'édition',
        'edition_not_found' => 'Édition inexistante',
        'show'              => 'Vidéos affichées',
        'show_now'          => '« Voir les vidéos »',
        'no_candidates'     => 'Aucune vidéo trouvée',
    ];
    $inputLabels = [
        'message' => 'Question saisie',
        'option'  => 'Pastille choisie',
        'show'    => '« Voir les N vidéos »',
    ];
    $errorLabels = [
        'rate_limited'    => 'Quota horaire atteint',
        'ai_unavailable'  => 'Assistant indisponible',
        'session_expired' => 'Conversation expirée',
        'invalid_request' => 'Requête invalide',
    ];

    $query = fn (array $params) => url()->current() . '?' . http_build_query(array_merge([
        'from'  => $from->toDateString(),
        'to'    => $to->toDateString(),
        'staff' => $includeStaff ? 1 : 0,
    ], $params));

    $presets = [
        '7 jours'   => [now()->subDays(6)->toDateString(), now()->toDateString()],
        '30 jours'  => [now()->subDays(29)->toDateString(), now()->toDateString()],
        '90 jours'  => [now()->subDays(89)->toDateString(), now()->toDateString()],
        'Ce mois'   => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        'Mois dernier' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
    ];
@endphp

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-bubble-hear"></i> Assistant IA · Tableau de bord
    </h1>
@stop

@section('css')
<style>
    .ai-dash { padding: 0 15px 40px; }
    .ai-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .ai-filters label { display: block; font-size: 12px; color: #76838f; margin-bottom: 4px; font-weight: 500; }
    .ai-filters input[type=date] { height: 34px; border: 1px solid #e4eaec; border-radius: 4px; padding: 0 8px; }
    .ai-filters .ai-check { display: flex; align-items: center; gap: 6px; height: 34px; margin: 0; color: #37474f; font-size: 13px; }
    .ai-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-left: auto; }
    .ai-presets a { font-size: 12px; padding: 6px 10px; border-radius: 999px; background: #f3f5f6; color: #37474f; text-decoration: none; }
    .ai-presets a.active, .ai-presets a:hover { background: #c81e2d; color: #fff; }

    .ai-section-title { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #76838f; margin: 24px 0 10px; font-weight: 600; }
    .ai-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 12px; }
    .ai-kpi { background: #fff; border-radius: 8px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); border-top: 3px solid #e4eaec; }
    .ai-kpi.accent { border-top-color: #c81e2d; }
    .ai-kpi .label { font-size: 12px; color: #76838f; font-weight: 500; }
    .ai-kpi .value { font-size: 26px; font-weight: 700; color: #263238; line-height: 1.25; margin: 4px 0 2px; font-variant-numeric: tabular-nums; }
    .ai-kpi .value small { font-size: 14px; font-weight: 500; color: #76838f; }
    .ai-kpi .hint { font-size: 12px; color: #a3afb7; line-height: 1.4; }

    .ai-grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 12px; }
    .ai-grid-even { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 1100px) { .ai-grid-2, .ai-grid-even { grid-template-columns: 1fr; } }
    .ai-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .ai-card h4 { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: #263238; }
    .ai-card .sub { font-size: 12px; color: #a3afb7; margin-bottom: 12px; }
    .ai-chart { position: relative; height: 280px; }

    .ai-table { width: 100%; font-size: 13px; }
    .ai-table th { color: #76838f; font-weight: 500; font-size: 12px; border-bottom: 1px solid #e4eaec; padding: 6px 8px; text-align: left; }
    .ai-table td { padding: 7px 8px; border-bottom: 1px solid #f3f5f6; color: #37474f; }
    .ai-table td.num, .ai-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .ai-table .bar { height: 6px; background: #f3f5f6; border-radius: 3px; overflow: hidden; margin-top: 4px; }
    .ai-table .bar span { display: block; height: 100%; background: #c81e2d; }
    .ai-badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; background: #fdecee; color: #a3121f; }
    .ai-empty { color: #a3afb7; font-size: 13px; padding: 12px 0; }
    .ai-note { font-size: 12px; color: #a3afb7; margin-top: 24px; line-height: 1.6; }
</style>
@stop

@section('content')
<div class="page-content ai-dash">

    {{-- ===================== Filtres ===================== --}}
    <form method="GET" class="ai-filters">
        <div>
            <label for="from">Du</label>
            <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}">
        </div>
        <div>
            <label for="to">Au</label>
            <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}">
        </div>
        <label class="ai-check">
            <input type="checkbox" name="staff" value="1" {{ $includeStaff ? 'checked' : '' }}>
            Inclure l'équipe interne
        </label>
        <button type="submit" class="btn btn-primary" style="margin:0">Appliquer</button>

        <div class="ai-presets">
            @foreach ($presets as $label => [$pFrom, $pTo])
                <a href="{{ $query(['from' => $pFrom, 'to' => $pTo]) }}"
                   class="{{ $from->toDateString() === $pFrom && $to->toDateString() === $pTo ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    {{-- ===================== Utilisation ===================== --}}
    <div class="ai-section-title">Utilisation</div>
    <div class="ai-kpis">
        <div class="ai-kpi accent">
            <div class="label">Recherches (tours)</div>
            <div class="value">{{ $nf($summary['turns']) }}</div>
            <div class="hint">Questions, choix de pastille et « Voir les vidéos »</div>
        </div>
        <div class="ai-kpi accent">
            <div class="label">Conversations</div>
            <div class="value">{{ $nf($summary['conversations']) }}</div>
            <div class="hint">Une conversation = de la 1re question au bouton ↻ ou à la sortie</div>
        </div>
        <div class="ai-kpi accent">
            <div class="label">Utilisateurs</div>
            <div class="value">{{ $nf($summary['users']) }}</div>
            <div class="hint">Utilisateurs distincts{{ $includeStaff ? '' : ', hors équipe interne' }}</div>
        </div>
        <div class="ai-kpi">
            <div class="label">Tours par utilisateur</div>
            <div class="value">{{ $nf($summary['turns_per_user'], 1) }}</div>
            <div class="hint">Moyenne sur la période</div>
        </div>
    </div>

    {{-- ===================== Consommation ===================== --}}
    <div class="ai-section-title">Consommation et coût</div>
    <div class="ai-kpis">
        <div class="ai-kpi accent">
            <div class="label">Tokens au total</div>
            <div class="value">{{ $nf($summary['tokens_total']) }}</div>
            <div class="hint">
                Entrée {{ $nf($summary['tokens_input']) }} · Sortie {{ $nf($summary['tokens_output']) }} · Embeddings ≈ {{ $nf($summary['tokens_embedding']) }}
            </div>
        </div>
        <div class="ai-kpi">
            <div class="label">Tokens moyens par tour</div>
            <div class="value">{{ $nf($summary['tokens_per_turn']) }}</div>
            <div class="hint">Tous les tours, y compris ceux sans appel à l'IA</div>
        </div>
        <div class="ai-kpi">
            <div class="label">Tokens moyens par appel IA</div>
            <div class="value">{{ $nf($summary['tokens_per_llm_call']) }}</div>
            <div class="hint">Taille réelle d'un échange avec {{ $model }}</div>
        </div>
        <div class="ai-kpi accent">
            <div class="label">Coût estimé</div>
            <div class="value">{{ $eur($summary['cost_total']) }}</div>
            <div class="hint">Soit {{ $eur($summary['cost_per_conversation']) }} par conversation</div>
        </div>
    </div>

    {{-- ===================== Qualité ===================== --}}
    <div class="ai-section-title">Qualité des réponses</div>
    <div class="ai-kpis">
        <div class="ai-kpi accent">
            <div class="label">Conversations avec résultats</div>
            <div class="value">{{ $nf($summary['success_rate'], 1) }} <small>%</small></div>
            <div class="hint">Au moins une liste de vidéos affichée</div>
        </div>
        <div class="ai-kpi">
            <div class="label">Tours sans résultat</div>
            <div class="value">{{ $nf($summary['no_result_rate'], 1) }} <small>%</small></div>
            <div class="hint">Aucune vidéo trouvée ou liste vide : à surveiller</div>
        </div>
        <div class="ai-kpi">
            <div class="label">Tours par conversation</div>
            <div class="value">{{ $nf($summary['avg_rounds'], 1) }}</div>
            <div class="hint">Plus c'est élevé, plus l'utilisateur doit insister</div>
        </div>
        <div class="ai-kpi">
            <div class="label">Questions de précision</div>
            <div class="value">{{ $nf($summary['ask_rate'], 1) }} <small>%</small></div>
            <div class="hint">Part des tours où l'assistant demande d'affiner</div>
        </div>
    </div>

    {{-- ===================== Technique ===================== --}}
    <div class="ai-section-title">Technique</div>
    <div class="ai-kpis">
        <div class="ai-kpi">
            <div class="label">Appels à l'IA</div>
            <div class="value">{{ $nf($summary['llm_calls']) }}</div>
            <div class="hint">{{ $nf($summary['llm_call_rate'], 1) }} % des tours ; les autres sont traités sans frais</div>
        </div>
        <div class="ai-kpi">
            <div class="label">Temps de réponse</div>
            <div class="value">{{ $nf($summary['avg_latency'] / 1000, 1) }} <small>s en moyenne</small></div>
            <div class="hint">95 % des tours en moins de {{ $nf($summary['p95_latency'] / 1000, 1) }} s</div>
        </div>
        <div class="ai-kpi">
            <div class="label">Refus et erreurs</div>
            <div class="value">{{ $nf($summary['errors']) }}</div>
            <div class="hint">{{ $nf($summary['error_rate'], 1) }} % des demandes (détail plus bas)</div>
        </div>
    </div>

    {{-- ===================== Graphiques ===================== --}}
    <div class="ai-section-title">Évolution</div>
    <div class="ai-grid-2">
        <div class="ai-card">
            <h4>Activité par jour</h4>
            <div class="sub">Tours, conversations et utilisateurs distincts</div>
            <div class="ai-chart"><canvas id="chartActivity"></canvas></div>
        </div>
        <div class="ai-card">
            <h4>Comment les utilisateurs avancent</h4>
            <div class="sub">Type de chaque tour</div>
            <div class="ai-chart"><canvas id="chartInputs"></canvas></div>
        </div>
    </div>
    <div class="ai-grid-2" style="margin-top:12px">
        <div class="ai-card">
            <h4>Tokens et coût par jour</h4>
            <div class="sub">Tokens (barres) et coût estimé en euros (ligne)</div>
            <div class="ai-chart"><canvas id="chartTokens"></canvas></div>
        </div>
        <div class="ai-card">
            <h4>Longueur des conversations</h4>
            <div class="sub">Nombre de conversations selon leur nombre de tours</div>
            <div class="ai-chart"><canvas id="chartRounds"></canvas></div>
        </div>
    </div>

    {{-- ===================== Détails ===================== --}}
    <div class="ai-section-title">Détails</div>
    <div class="ai-grid-even">
        <div class="ai-card">
            <h4>Réponses de l'assistant</h4>
            <div class="sub">Ce que l'assistant a fait à chaque tour réussi</div>
            @php $actionsTotal = array_sum($actions); @endphp
            @if ($actionsTotal === 0)
                <div class="ai-empty">Aucune donnée sur la période.</div>
            @else
                <table class="ai-table">
                    <thead><tr><th>Réponse</th><th class="num">Tours</th><th class="num">Part</th></tr></thead>
                    <tbody>
                    @foreach ($actions as $action => $count)
                        <tr>
                            <td>
                                {{ $actionLabels[$action] ?? $action }}
                                <div class="bar"><span style="width: {{ $count / $actionsTotal * 100 }}%"></span></div>
                            </td>
                            <td class="num">{{ $nf($count) }}</td>
                            <td class="num">{{ $nf($count / $actionsTotal * 100, 1) }} %</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="ai-card">
            <h4>Refus et erreurs</h4>
            <div class="sub">Demandes qui n'ont pas abouti</div>
            @if (empty($errorCodes))
                <div class="ai-empty">Aucune erreur sur la période.</div>
            @else
                <table class="ai-table">
                    <thead><tr><th>Cause</th><th class="num">Nombre</th></tr></thead>
                    <tbody>
                    @foreach ($errorCodes as $code => $count)
                        <tr>
                            <td>{{ $errorLabels[$code] ?? $code }}</td>
                            <td class="num">{{ $nf($count) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="sub" style="margin-top:10px">
                    « Quota horaire atteint » : limite de {{ $rateLimit }} recherches par heure et par utilisateur.
                </div>
            @endif
        </div>
    </div>

    <div class="ai-card" style="margin-top:12px">
        <h4>Utilisateurs les plus actifs</h4>
        <div class="sub">Repère les gros consommateurs et les éventuels abus</div>
        @if (empty($topUsers))
            <div class="ai-empty">Aucune donnée sur la période.</div>
        @else
            <table class="ai-table">
                <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th class="num">Tours</th>
                    <th class="num">Conversations</th>
                    <th class="num">Tokens</th>
                    <th class="num">Coût estimé</th>
                    <th class="num">Quota atteint</th>
                    <th class="num">Dernière utilisation</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($topUsers as $u)
                    <tr>
                        <td>
                            {{ $u['name'] ?: '—' }}
                            <div style="font-size:11px;color:#a3afb7">{{ $u['email'] ?: '#' . $u['user_id'] }}</div>
                        </td>
                        <td class="num">{{ $nf($u['turns']) }}</td>
                        <td class="num">{{ $nf($u['conversations']) }}</td>
                        <td class="num">{{ $nf($u['tokens']) }}</td>
                        <td class="num">{{ $eur($u['cost']) }}</td>
                        <td class="num">
                            @if ($u['rate_limited'] > 0)
                                <span class="ai-badge">{{ $u['rate_limited'] }}×</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">{{ \Carbon\Carbon::parse($u['last_used'])->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="ai-card" style="margin-top:12px">
        <h4>Dernières recherches sans résultat</h4>
        <div class="sub">Pistes d'amélioration : alias de congrès, synonymes, vidéos manquantes</div>
        @if (! $logQueries)
            <div class="ai-empty">
                Le texte des questions n'est pas enregistré. Pour l'activer : <code>AI_SEARCH_LOG_QUERIES=true</code>
                dans le <code>.env</code>, puis <code>php artisan config:cache</code>.
            </div>
        @elseif (empty($noResults))
            <div class="ai-empty">Aucune recherche sans résultat sur la période.</div>
        @else
            <table class="ai-table">
                <thead><tr><th>Question</th><th>Réponse</th><th class="num">Date</th></tr></thead>
                <tbody>
                @foreach ($noResults as $row)
                    <tr>
                        <td>{{ $row->query_text }}</td>
                        <td>{{ $actionLabels[$row->action] ?? $row->action }}</td>
                        <td class="num">{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="ai-note">
        Coût estimé en euros à partir des tarifs Google (USD par million de tokens : entrée {{ $pricing['chat_input'] }},
        sortie et réflexion {{ $pricing['chat_output'] }}, embeddings {{ $pricing['embedding'] }}),
        convertis au taux de 1 $ = {{ $pricing['usd_to_eur'] }} € (<code>AI_SEARCH_USD_TO_EUR</code>).
        Les tokens d'embeddings sont estimés (≈ 4 caractères par token) et l'indexation des vidéos n'est pas comptée.
        Le montant réel fait foi dans Google AI Studio.
    </div>
</div>
@stop

@section('javascript')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    var daily = @json($daily);
    var inputTypes = @json($inputTypes);
    var rounds = @json($rounds);
    var inputLabels = @json($inputLabels);

    var RED = '#c81e2d', RED_LIGHT = 'rgba(200,30,45,.15)', SLATE = '#37474f', BLUE = '#2f80ed', GREY = '#a3afb7';

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#76838f';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.maintainAspectRatio = false;
    // Pas de points sur les courbes : un point n'apparaît qu'au survol d'un jour.
    Chart.defaults.elements.point.radius = 0;
    Chart.defaults.elements.point.hoverRadius = 4;
    Chart.defaults.elements.line.borderWidth = 2;

    var days = daily.map(function (d) {
        var p = d.day.split('-');
        return p[2] + '/' + p[1];
    });

    new Chart(document.getElementById('chartActivity'), {
        type: 'line',
        data: {
            labels: days,
            datasets: [
                { label: 'Tours', data: daily.map(function (d) { return d.turns; }), borderColor: RED, backgroundColor: RED_LIGHT, fill: true, tension: .3 },
                { label: 'Conversations', data: daily.map(function (d) { return d.conversations; }), borderColor: SLATE, tension: .3 },
                { label: 'Utilisateurs', data: daily.map(function (d) { return d.users; }), borderColor: BLUE, tension: .3 }
            ]
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }
        }
    });

    var inputKeys = Object.keys(inputTypes);
    new Chart(document.getElementById('chartInputs'), {
        type: 'doughnut',
        data: {
            labels: inputKeys.map(function (k) { return inputLabels[k] || k; }),
            datasets: [{ data: inputKeys.map(function (k) { return inputTypes[k]; }), backgroundColor: [RED, SLATE, GREY], borderWidth: 0 }]
        },
        options: { cutout: '62%', plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('chartTokens'), {
        data: {
            labels: days,
            datasets: [
                { type: 'bar', label: 'Tokens', data: daily.map(function (d) { return d.tokens; }), backgroundColor: RED_LIGHT, borderColor: RED, borderWidth: 1, yAxisID: 'y' },
                { type: 'line', label: 'Coût (€)', data: daily.map(function (d) { return d.cost; }), borderColor: SLATE, tension: .3, yAxisID: 'y1' }
            ]
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, position: 'left' },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: function (v) { return v + ' €'; } } },
                x: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('chartRounds'), {
        type: 'bar',
        data: {
            labels: Object.keys(rounds).map(function (k) { return k + (k === '1' ? ' tour' : ' tours'); }),
            datasets: [{ label: 'Conversations', data: Object.values(rounds), backgroundColor: [SLATE, SLATE, SLATE, RED, RED], borderRadius: 4 }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }
        }
    });
})();
</script>
@stop