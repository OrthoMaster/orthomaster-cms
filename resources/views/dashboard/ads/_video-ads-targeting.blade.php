{{-- Onglet Ciblage : points attribués et retombées, par publicité et par mois --}}
@php
    $pct = fn ($value) => is_null($value) ? '—' : number_format($value, 1, ',', ' ') . ' %';
    $num = fn ($value) => number_format($value, 0, ',', ' ');
@endphp

@if (empty($targeting))
    <div class="alert alert-warning">
        <strong>Aucune répartition sur cette sélection.</strong>
        <p>Les mois sans points attribués ne figurent pas dans cet onglet.</p>
    </div>
@else
    @foreach ($targeting as $ad)
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title">{{ $ad['title'] }}</h3>
            </div>
            <div class="panel-body">
                @foreach ($ad['periods'] as $period)
                    <div class="vad-period">
                        <div class="vad-period-title">
                            <span>
                                {{ ucfirst($period['label']) }}
                                <small class="vad-points-total">{{ $period['points_total'] }} points</small>
                            </span>

                            @if ($isAdmin)
                                <a href="{{ route('dashboard.video-ads.targets', $ad['id']) }}"
                                    class="btn btn-sm btn-primary">
                                    <i class="voyager-edit"></i> Répartir les points
                                </a>
                            @endif
                        </div>

                        <div class="row vad-axes">
                            @php $axisIndex = 0; @endphp
                            @foreach ($period['axes'] as $axis)
                                {{-- Les professions ont des libellés plus longs : deux tiers de la largeur --}}
                                <div class="{{ $axisIndex === 0 ? 'col-md-7' : 'col-md-5' }}">
                                    <div class="vad-axis">
                                        <div class="vad-axis-head">{{ $axis['label'] }}</div>

                                        @if (empty($axis['targeted']) && empty($axis['others']))
                                            <p class="vad-axis-empty">
                                                Aucun point attribué, aucune diffusion enregistrée.
                                            </p>
                                        @else
                                            <div class="vad-axis-scroll">
                                                <table class="table table-bordered vad-target-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Cible</th>
                                                            <th class="text-right">Points</th>
                                                            <th class="text-right">Prévue</th>
                                                            <th class="text-right">Impr.</th>
                                                            <th class="text-right">Réelle</th>
                                                            <th class="text-right">Vues</th>
                                                            <th class="text-right">CTR</th>
                                                            <th class="text-right">Contacts</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($axis['targeted'] as $row)
                                                            <tr>
                                                                <td>{{ $row['name'] }}</td>
                                                                <td class="text-right">{{ $row['points'] }}</td>
                                                                <td class="text-right">{{ $pct($row['expected']) }}</td>
                                                                <td class="text-right">{{ $num($row['impressions']) }}</td>
                                                                <td class="text-right">{{ $pct($row['actual']) }}</td>
                                                                <td class="text-right">{{ $num($row['views']) }}</td>
                                                                <td class="text-right">{{ $pct($row['ctr']) }}</td>
                                                                <td class="text-right">{{ $num($row['contacted']) }}</td>
                                                            </tr>
                                                        @endforeach

                                                        @if (!empty($axis['others']))
                                                            <tr class="vad-others-head">
                                                                <td colspan="8">
                                                                    Hors ciblage &mdash; audience captée sans points attribués
                                                                </td>
                                                            </tr>

                                                            @foreach ($axis['others'] as $row)
                                                                <tr class="vad-others">
                                                                    <td>{{ $row['name'] }}</td>
                                                                    <td class="text-right">0</td>
                                                                    <td class="text-right">—</td>
                                                                    <td class="text-right">{{ $num($row['impressions']) }}</td>
                                                                    <td class="text-right">{{ $pct($row['actual']) }}</td>
                                                                    <td class="text-right">{{ $num($row['views']) }}</td>
                                                                    <td class="text-right">{{ $pct($row['ctr']) }}</td>
                                                                    <td class="text-right">{{ $num($row['contacted']) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td>Total</td>
                                                            <td class="text-right">{{ $axis['totals']['points'] }}</td>
                                                            <td class="text-right">—</td>
                                                            <td class="text-right">{{ $num($axis['totals']['impressions']) }}</td>
                                                            <td class="text-right">—</td>
                                                            <td class="text-right">{{ $num($axis['totals']['views']) }}</td>
                                                            <td class="text-right">
                                                                {{ $axis['totals']['impressions'] > 0
                                                                    ? $pct($axis['totals']['views'] / $axis['totals']['impressions'] * 100)
                                                                    : '—' }}
                                                            </td>
                                                            <td class="text-right">{{ $num($axis['totals']['contacted']) }}</td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @php $axisIndex++; @endphp
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif