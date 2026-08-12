@php
    $canEdit     = $canEdit ?? true;
    $showViewers = $showViewers ?? true;
    $showWatched = $showWatched ?? true;

    // Secondes -> mm:ss ou h:mm:ss
    $hms = function ($seconds) {
        $s = (int) $seconds;
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $r = $s % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $r)
            : sprintf('%d:%02d', $m, $r);
    };
@endphp

@if (!$medias->count() > 0)
    <div class="alert alert-warning">
        <strong>Aucune information n'est disponible !</strong>
        <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
    </div>
@else
    <div class="panel panel-bordered">
        <div class="panel-body">
            <div class="table-responsive">
                <div class="row">
                    <div class="col-md-6">
                        <div>Trouvez <b>{{ $medias->total() }}</b>
                            {{ $medias->total() > 1 ? 'résultats' : 'résultat' }}
                        </div>
                        <span>Page <b>{{ $medias->currentPage() }}/{{ $medias->lastPage() }}</b></span>
                    </div>
                    <div class="col-md-6">
                        <div class="table-navigation">
                            {{ $medias->links() }}
                        </div>
                    </div>
                </div>
                <table class="table table-striped database-tables">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Titre</th>
                            <th>Auteur</th>
                            <th>Événement</th>
                            <th>Vues (période)</th>
                            @if ($showWatched)
                                <th>Temps moyen (période)</th>
                            @endif
                            @if ($showViewers)
                                <th>Utilisateurs (période)</th>
                            @endif
                            <th>Vues (total)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($medias as $key => $media)
                            <tr>
                                <td>{{ $medias->firstItem() + $key }}</td>
                                <td><b>{{ $media->title }}</b></td>
                                <td>{{ $media->author ?: '—' }}</td>
                                <td>{{ $media->edition_year ? $media->edition_year . ' — ' . $media->event_name : '—' }}</td>
                                <td>{{ number_format($media->period_views, 0, ',', ' ') }}</td>
                                @if ($showWatched)
                                    <td>
                                        @if (!is_null($media->avg_watched))
                                            {{ $hms($media->avg_watched) }}
                                            @if (!empty($media->avg_duration) && $media->avg_duration > 0)
                                                <small class="text-muted">
                                                    ({{ round($media->avg_watched / $media->avg_duration * 100) }} %)
                                                </small>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                                @if ($showViewers)
                                    <td>{{ number_format($media->period_viewers, 0, ',', ' ') }}</td>
                                @endif
                                <td>{{ number_format($media->views, 0, ',', ' ') }}</td>
                                @if ($canEdit)
                                    <td class="no-sort no-click text-right">
                                        <a href="{{ route('medias.edit', $media->id_content) }}"
                                           class="btn btn-sm btn-primary edit">
                                            <i class="voyager-edit"></i> <span class="hidden-xs hidden-sm">Modifier</span>
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="table-navigation" style="margin-bottom: 2em">
                    {{ $medias->links() }}
                </div>
            </div>
        </div>
    </div>
@endif