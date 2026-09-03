@extends('voyager::master')

@section('page_title', ' Podcasts')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-music"></i> Podcasts
        </h1>
        <a href="{{ route('voyager.podcasts.create') }}" class="btn btn-success">
            <i class="voyager-plus"></i> <span>Ajouter un podcast</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                @if (!$podcasts->count() > 0)
                    <div class="alert alert-warning">
                        <strong>Aucune information n'est disponible !</strong>
                    </div>
                @else
                    <div class="panel panel-bordered">
                        <div class="panel-body">
                            <div class="table-responsive">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div>Trouvez <b>{{ $podcasts->total() }}</b>
                                            {{ $podcasts->total() > 1 ? 'résultats' : 'résultat' }}
                                        </div>
                                        <span>Page <b>{{ $podcasts->currentPage() }}/{{ $podcasts->lastPage() }}</b></span>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="table-navigation">
                                            {{ $podcasts->links() }}
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-striped database-tables">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Sujet</th>
                                            <th>Titre</th>
                                            <th>Auteur</th>
                                            <th>Écoutes</th>
                                            <th>Auditeurs</th>
                                            <th>Sauvegardes</th>
                                            <th>Publication</th>
                                            <th class="actions text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($podcasts as $p)
                                            <tr>
                                                <td>{{ $p->id }}</td>
                                                <td>{{ $p->topic_name ?: '—' }}</td>
                                                <td><b>{{ $p->title }}</b></td>
                                                <td>{{ $p->author ?: '—' }}</td>
                                                <td>{{ number_format($p->count ?? 0, 0, ',', ' ') }}</td>
                                                <td>{{ number_format($p->listeners ?? 0, 0, ',', ' ') }}</td>
                                                <td>{{ number_format($p->favourites ?? 0, 0, ',', ' ') }}</td>
                                                <td>
                                                    {{ $p->published_at
                                                        ? \Illuminate\Support\Carbon::parse($p->published_at)->format('d/m/Y')
                                                        : '—' }}
                                                </td>
                                                <td class="no-sort no-click text-right">
                                                    <button type="button" class="btn btn-success"
                                                        onclick="const b=this; navigator.clipboard.writeText('https://go.orthomaster.fr/open/podcast/{{ $p->id }}'); b.textContent='✓ Copié !'; setTimeout(() => b.textContent='Partager', 1500);">
                                                        Partager
                                                    </button>
                                                    <a href="{{ route('voyager.podcasts.edit', $p->id) }}"
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
                                    {{ $podcasts->links() }}
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
@stop