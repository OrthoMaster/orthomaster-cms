@extends('voyager::master')

@section('page_title', ' Médias')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-play"></i> Médias
        </h1>
        <a href="{{ route('medias.create') }}" class="btn btn-success">
            <i class="voyager-plus"></i> <span>Ajouter un média</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ FILTRES ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
                    </div>
                    <div class="panel-body">
                        <form class="row" action="{{ route('medias.list') }}" method="GET">
                            <div class="col-md-3">
                                <label for="event_id">Événement</label>
                                <select class="form-control" name="event_id" id="event_id">
                                    <option value="">Tous les événements</option>
                                    @foreach ($events as $e)
                                        <option value="{{ $e->id }}" @selected($filters['event_id'] == $e->id)>
                                            {{ $e->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="past_event_id">Édition</label>
                                <select class="form-control" name="past_event_id" id="past_event_id">
                                    <option value="">Toutes les éditions</option>
                                    @foreach ($pastEvents as $pe)
                                        <option value="{{ $pe->id }}" data-event="{{ $pe->event_id }}"
                                            @selected($filters['past_event_id'] == $pe->id)>
                                            {{ $pe->text }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="expert_id">Auteur</label>
                                <select class="form-control" name="expert_id" id="expert_id">
                                    <option value=""></option>
                                    @if ($filters['expert_id'])
                                        <option value="{{ $filters['expert_id'] }}" selected>{{ $selectedExpert }}</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="content_id">Vidéo</label>
                                <select class="form-control" name="content_id" id="content_id">
                                    <option value=""></option>
                                    @if ($filters['content_id'])
                                        <option value="{{ $filters['content_id'] }}" selected>{{ $selectedVideo }}</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-12" style="margin-top: 15px; margin-bottom: 0">
                                <button type="submit" class="btn btn-primary pull-right">
                                    Appliquer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ============ TABLE ============ --}}
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
                                            <th>Cont ID</th>
                                            <th>Titre</th>
                                            <th>Auteur</th>
                                            <th>Événement</th>
                                            <th class="actions text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($medias as $media)
                                            <tr>
                                                <td>{{ $media->id_content }}</td>
                                                <td>
                                                    <b>{{ $media->title }}</b>
                                                    @if ($media->video_code)
                                                        <div style="padding-top: 4px">
                                                            <span class="media-code">{{ $media->video_code }}</span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $media->author ?: '—' }}</td>
                                                <td>
                                                    {{ $media->edition_year ? $media->edition_year . ' — ' . $media->event_name : '—' }}
                                                </td>
                                                <td class="no-sort no-click text-right">
                                                    <a href="{{ route('medias.edit', $media->id_content) }}"
                                                        class="btn btn-sm btn-primary edit">
                                                        <i class="voyager-edit"></i>
                                                        <span class="hidden-xs hidden-sm">Modifier</span>
                                                    </a>
                                                </td>
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

            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    <style>
        .media-code {
            padding: 2px 9px;
            border: 1px solid #dfe3e9;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: .04em;
            color: #8a9199;
            background: #fafbfc;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function() {

            /* ---------- Événement + édition (cascade) ---------- */
            var $event = $('#event_id'),
                $pastEvent = $('#past_event_id'),
                allEditions = $pastEvent.find('option[data-event]').clone();

            function refreshEditions() {
                var eid = $event.val(),
                    current = $pastEvent.val();

                $pastEvent.find('option[data-event]').remove();

                allEditions.each(function() {
                    if (!eid || $(this).data('event') == eid) {
                        $pastEvent.append($(this).clone());
                    }
                });

                $pastEvent.val($pastEvent.find('option[value="' + current + '"]').length ? current : '');
                $pastEvent.trigger('change.select2');
            }

            $event.select2({
                width: '100%'
            });
            $pastEvent.select2({
                width: '100%'
            });

            function eventParams() {
                return {
                    event_id: $event.val() || '',
                    past_event_id: $pastEvent.val() || ''
                };
            }

            /* ---------- Auteur ---------- */
            $('#expert_id').select2({
                width: '100%',
                placeholder: 'Tous les auteurs',
                allowClear: true,
                minimumInputLength: 3,
                language: {
                    inputTooShort: function() {
                        return 'Saisissez au moins 3 caractères';
                    },
                    noResults: function() {
                        return 'Aucun résultat';
                    },
                    searching: function() {
                        return 'Recherche…';
                    }
                },
                ajax: {
                    url: '{{ route('medias.search-experts') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return $.extend({
                            q: params.term
                        }, eventParams());
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                }
            });

            /* ---------- Vidéo ---------- */
            function videoPlaceholder() {
                var d = $('#expert_id').select2('data');
                return (d.length && d[0].id) ? 'Toutes les vidéos de ' + d[0].text : 'Toutes les vidéos';
            }

            function initVideoSelect() {
                if ($('#content_id').hasClass('select2-hidden-accessible')) {
                    $('#content_id').select2('destroy');
                }

                $('#content_id').select2({
                    width: '100%',
                    placeholder: videoPlaceholder(),
                    allowClear: true,
                    minimumInputLength: 3,
                    language: {
                        inputTooShort: function() {
                            return 'Saisissez au moins 3 caractères';
                        },
                        noResults: function() {
                            return 'Aucun résultat';
                        },
                        searching: function() {
                            return 'Recherche…';
                        }
                    },
                    ajax: {
                        url: '{{ route('medias.search-videos') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return $.extend({
                                q: params.term,
                                expert_id: $('#expert_id').val()
                            }, eventParams());
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });
            }

            function resetDependents() {
                $('#expert_id').val(null).find('option[value!=""]').remove();
                $('#expert_id').trigger('change.select2');
                $('#content_id').val(null).find('option[value!=""]').remove();
                initVideoSelect();
            }

            $event.on('change', function() {
                refreshEditions();
                resetDependents();
            });

            $pastEvent.on('change', resetDependents);

            $('#expert_id').on('change', function() {
                $('#content_id').val(null).find('option[value!=""]').remove();
                initVideoSelect();
            });

            initVideoSelect();
            refreshEditions();
        });
    </script>
@stop
