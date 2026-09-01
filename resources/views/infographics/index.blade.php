@extends('voyager::master')

@section('page_title', ' Infographies')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-images"></i> Infographies
        </h1>
        <a href="{{ route('infographics.create') }}" class="btn btn-success">
            <i class="voyager-plus"></i> <span>Ajouter une infographie</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">
                
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <form action="{{ route('infographics.index') }}" method="GET" class="search-form">
                            <input type="text" class="form-control" name="q" id="q"
                                   value="{{ $search }}" placeholder="Rechercher par titre">
                            <button type="submit" class="btn btn-primary">Rechercher</button>
                            @if ($search !== '')
                                <a href="{{ route('infographics.index') }}" class="btn btn-default">
                                    Réinitialiser
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                @if (!$infographics->count() > 0)
                    <div class="alert alert-warning">
                        <strong>Aucune information n'est disponible !</strong>
                        @if ($search !== '')
                            <p>Aucune infographie ne correspond à cette recherche.</p>
                        @endif
                    </div>
                @else
                    <div class="panel panel-bordered">
                        <div class="panel-body">
                            <div class="table-responsive">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div>Trouvez <b>{{ $infographics->total() }}</b>
                                            {{ $infographics->total() > 1 ? 'résultats' : 'résultat' }}
                                        </div>
                                        <span>Page <b>{{ $infographics->currentPage() }}/{{ $infographics->lastPage() }}</b></span>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="table-navigation">
                                            {{ $infographics->links() }}
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-striped database-tables">
                                    <thead>
                                        <tr>
                                            <th style="width:80px">Visuel</th>
                                            <th>Titre</th>
                                            <th>Code</th>
                                            <th>Vues</th>
                                            <th>Spectateurs</th>
                                            <th>Publication</th>
                                            <th class="actions text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($infographics as $item)
                                            <tr>
                                                <td>
                                                    @if ($item->thumbnail)
                                                        <img src="{{ Storage::disk('s3')->url($item->thumbnail) }}"
                                                             alt="" class="table-thumbnail">
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td><b>{{ $item->title }}</b></td>
                                                <td><code>{{ $item->code }}</code></td>
                                                <td>{{ number_format($item->views ?? 0, 0, ',', ' ') }}</td>
                                                <td>{{ number_format($item->viewers ?? 0, 0, ',', ' ') }}</td>
                                                <td>
                                                    {{ $item->published_at
                                                        ? \Illuminate\Support\Carbon::parse($item->published_at)->format('d/m/Y')
                                                        : '—' }}
                                                </td>
                                                <td class="no-sort no-click text-right">
                                                    <button type="button" class="btn btn-success"
                                                        onclick="const b=this; navigator.clipboard.writeText('https://go.orthomaster.fr/open/infographic/{{ $item->id }}'); b.textContent='✓ Copié !'; setTimeout(() => b.textContent='Partager', 1500);">
                                                        Partager
                                                    </button>
                                                    <a href="{{ route('infographics.edit', $item->id) }}"
                                                       class="btn btn-sm btn-primary edit">
                                                        <i class="voyager-edit"></i>
                                                        <span class="hidden-xs hidden-sm">Modifier</span>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete"
                                                            data-id="{{ $item->id }}"
                                                            data-title="{{ $item->title }}">
                                                        <i class="voyager-trash"></i>
                                                        <span class="hidden-xs hidden-sm">Supprimer</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="table-navigation" style="margin-bottom: 2em">
                                    {{ $infographics->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Formulaire de suppression, hors du tableau pour éviter tout imbrication --}}
    <form method="POST" id="delete-form" style="display:none">
        @csrf
        @method('DELETE')
    </form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    <style>
        .table-thumbnail {
            width: 64px;
            height: 44px;
            object-fit: cover;
            border: 1px solid #e3e6ec;
            border-radius: 3px;
            background: #f7f9fc;
        }
        .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 520px;
        }

        .search-form .form-control { flex: 1 1 auto; }
        .search-form .btn { flex-shrink: 0; }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {

            var DELETE_URL = '{{ route('infographics.destroy', ['id' => '__ID__']) }}';

            $('.delete').on('click', function () {
                var id    = $(this).data('id'),
                    title = $(this).data('title');

                var ok = confirm(
                    'Supprimer « ' + title + ' » ?\n\n' +
                    'La fiche et ses visuels seront définitivement effacés.'
                );

                if (!ok) return;

                $('#delete-form')
                    .attr('action', DELETE_URL.replace('__ID__', id))
                    .trigger('submit');
            });
        });
    </script>
@stop