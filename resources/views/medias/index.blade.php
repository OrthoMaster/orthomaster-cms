@extends('voyager::master')

@section('page_title', ' Médias')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-play"></i> Médias
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        @if (isset($medias) && !$medias->count() > 0)
            <div class="alert alert-warning">
                <strong>Aucune information n'est disponible !</strong>
                <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
            </div>
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="col-lg-4 col-md-5">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
                        </div>
                        <div class="panel-body">
                            <form class="row" action="{{ route('medias.index') }}" method="GET">
                                <div class="col-md-7">
                                    <label for="display_name">Titre</label>
                                    <input type="text" class="form-control" name="title" value="{{ request('title') }}"
                                        placeholder="Titre">
                                </div>
                                <div class="col-md-5">
                                    <label for="display_name">Auteur</label>
                                    <input type="text" class="form-control" name="author"
                                        value="{{ request('author') }}" placeholder="Auteur">
                                </div>
                                <div class="col-md-12" style="margin-bottom: 0">
                                    <button type="submit" class="btn btn-primary pull-right">
                                        Cherchez
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-md-7">
                    @if (isset($medias) && $medias->count() > 0)
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
                                                <th>Vues</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($medias as $media)
                                                <tr>
                                                    <td>{{ $media->id_content }}</td>
                                                    <td><b>{{ $media->title }}</b></td>
                                                    <td>{{ $media->author }}</td>
                                                    <td>{{ $media->views }}</td>
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
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
@stop

@section('javascript')
@stop
