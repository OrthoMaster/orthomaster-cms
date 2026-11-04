@extends('voyager::master')

@section('page_title', ' Table ronde')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-chat"></i> {{ $board->exists ? 'Modifier la table ronde' : 'Nouvelle table ronde' }}
        </h1>
    </div>
@stop

@section('css')
    <style>
        /* --- Thèmes --- */
        .cg-theme { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
        .cg-theme .form-control { max-width: 420px; }
        .cg-dot { flex: none; width: 18px; height: 18px; border-radius: 50%; background: #E3E6EC; }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ $board->exists ? route('dashboard.congres.update', $board) : route('dashboard.congres.store') }}">
            @csrf
            @if ($board->exists)
                @method('PUT')
            @endif

            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="form-group">
                        <label for="title">Titre de la table ronde</label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="150" required
                            value="{{ old('title', $board->title) }}">
                    </div>
                    <div class="form-group">
                        <label for="past_event_id">Édition (facultatif)</label>
                        <select class="form-control select2" id="past_event_id" name="past_event_id">
                            <option value="">Aucune</option>
                            @foreach ($editions as $edition)
                                <option value="{{ $edition->id }}" @selected((int) old('past_event_id', $board->past_event_id) === (int) $edition->id)>{{ $edition->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="congress_name">Nom du congrès affiché</label>
                        <input type="text" class="form-control" id="congress_name" name="congress_name" maxlength="150"
                            value="{{ old('congress_name', $board->congress_name) }}" placeholder="Vide : nom de l'édition choisie">
                    </div>
                </div>
            </div>

            <div class="panel panel-bordered">
                <div class="panel-body">
                    <label>Thèmes (6 au maximum)</label>
                    <p class="text-muted">La couleur de chaque thème est attribuée automatiquement. Moins il y a de thèmes, plus l'écran affiche de questions par thème.</p>

                    <div id="themes">
                        @foreach (old('themes', $themes->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->all()) as $i => $theme)
                            <div class="cg-theme">
                                <span class="cg-dot" @if (!empty($theme['color'])) style="background: {{ $theme['color'] }}" @endif></span>
                                <input type="hidden" name="themes[{{ $i }}][id]" value="{{ $theme['id'] ?? '' }}">
                                <input type="text" class="form-control" name="themes[{{ $i }}][name]" maxlength="60" value="{{ $theme['name'] ?? '' }}">
                                <button type="button" class="btn btn-sm btn-default cg-remove">Retirer</button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn btn-sm btn-default" id="add-theme">
                        <i class="voyager-plus"></i> Ajouter un thème
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="{{ $board->exists ? route('dashboard.congres.live', $board) : route('dashboard.congres') }}" class="btn btn-default">Annuler</a>
        </form>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            const MAX_THEMES = 6;
            const list = $('#themes');
            // Compteur et non longueur : les index doivent rester uniques après un retrait.
            let nextIndex = list.children().length + 100;

            function refresh() {
                $('#add-theme').toggle(list.children().length < MAX_THEMES);
            }

            function addTheme() {
                const i = nextIndex++;
                const row = $('<div class="cg-theme"><span class="cg-dot"></span></div>');
                row.append($('<input type="hidden">').attr('name', 'themes[' + i + '][id]'));
                row.append($('<input type="text" class="form-control" maxlength="60">').attr('name', 'themes[' + i + '][name]'));
                row.append('<button type="button" class="btn btn-sm btn-default cg-remove">Retirer</button>');
                list.append(row);
                refresh();
            }

            $('#add-theme').on('click', addTheme);
            list.on('click', '.cg-remove', function () {
                $(this).closest('.cg-theme').remove();
                refresh();
            });

            if (list.children().length === 0) {
                addTheme();
                addTheme();
            }
            refresh();
        });
    </script>
@stop
