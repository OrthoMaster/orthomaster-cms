@extends('voyager::master')

@section('page_title', 'Les professionnels de santé')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-people"></i> Les professionnels de santé
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
                    </div>
                    <div class="panel-body">
                        <form class="row" action="{{ route('healthcare.index') }}" method="GET">
                            <div class="col-md-4">
                                <label for="display_name">RPPS</label>
                                <input type="text" class="form-control" name="pp" value="{{ request('pp') }}"
                                    placeholder="Au moins 3 chiffres">
                            </div>
                            <div class="col-md-4">
                                <label for="display_name">Nom</label>
                                <input type="text" class="form-control" name="name" value="{{ request('name') }}"
                                    placeholder="Nom">
                            </div>
                            <div class="col-md-4">
                                <label for="display_name">Prénom</label>
                                <input type="text" class="form-control" name="first_name"
                                    value="{{ request('first_name') }}" placeholder="Prénom">
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
        </div>



        @if (isset($hcps) && !$hcps->count() > 0)
            <div class="alert alert-warning">
                <strong>Aucune information n'est disponible !</strong>
                <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
            </div>
        @endif
        @if (isset($hcps) && $hcps->count() > 0)
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div>Trouvez <b>{{ $hcps->total() }}</b> {{ $hcps->total() > 1 ? 'résultats' : 'résultat' }}
                            </div>
                            <span>Page <b>{{ $hcps->currentPage() }}/{{ $hcps->lastPage() }}</b></span>
                        </div>
                        <div class="col-md-6">
                            <div class="table-navigation">
                                {{ $hcps->links() }}
                            </div>
                        </div>
                    </div>
                    <table class="table table-striped database-tables">
                        <thead>
                            <tr>
                                <th>RPPS</th>
                                <th>Professionnel de la santé</th>
                                <th>Profession</th>
                                <th>Système ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hcps as $hcp)
                                <tr>
                                    <th style="padding-top: 12px">{{ $hcp->pp }}</th>
                                    <td>
                                        @if ($hcp->activities->isNotEmpty())
                                            @if ($hcp->activities->isNotEmpty())
                                                <small>{{ $hcp->activities->first()->civility_label }} </small>
                                            @endif
                                            @if ($hcp->activities->first()->civility_pro_code !== 'nan')
                                                @if ($hcp->activities->first()->civility_pro_code == 'PR')
                                                    <small
                                                        class="label label-info">{{ $hcp->activities->first()->civility_pro_label }}</small>
                                                @else
                                                    <small
                                                        class="label label-info">{{ $hcp->activities->first()->civility_pro_label }}</small>
                                                @endif
                                            @endif
                                        @endif
                                        <p>{{ $hcp->name . ' ' . $hcp->first_name }}</p>
                                    </td>
                                    <td>
                                        @if ($hcp->activities->isNotEmpty())
                                            <p>{{ $hcp->activities->first()->label }}</p>
                                        @endif
                                    </td>
                                    <td>{{ $hcp->id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="table-navigation" style="margin-bottom: 2em">
                        {{ $hcps->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
@stop

@section('javascript')
    <!-- DataTables -->
    <script>
        $(document).ready(function() {

        });
    </script>
@stop
