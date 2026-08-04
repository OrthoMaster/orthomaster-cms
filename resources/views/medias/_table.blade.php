@php $canEdit = $canEdit ?? true; @endphp

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
                            <th>Spectateurs (période)</th>
                            <th>Vues (total)</th>
                            @if ($canEdit)
                                <th class="actions text-right">Actions</th>
                            @endif
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
                                <td>{{ number_format($media->period_viewers, 0, ',', ' ') }}</td>
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