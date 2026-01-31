@extends('voyager::master')

@section('page_title', 'Sociétés')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-company"></i> Sociétés
        </h1>
        <a href="{{ route('voyager.companies.create') }}" class="btn btn-success btn-add-new">
            <i class="voyager-plus"></i> <span>Ajouter</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-8">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <form class="row" action="{{ route('voyager.companies.index') }}" method="GET">
                            <div class="col-md-3">
                                <label for="display_name">Email</label>
                                <input type="text" class="form-control" name="email" value="{{ request('email') }}"
                                    placeholder="Email">
                            </div>
                            <div class="col-md-3">
                                <label for="display_name">Nom</label>
                                <input type="text" class="form-control" name="name" value="{{ request('name') }}"
                                    placeholder="Nom">
                            </div>
                            <div class="col-md-3">
                                <label for="asdfd">Statut</label>
                                <select class="form-control select2" name="status">
                                    <option value="all" @selected(request('status') === 'all')>---</option>
                                    <option value="2" @selected(request('status') === '2')>En cours de remplir</option>
                                    <option value="3" @selected(request('status') === '3')>Erreur</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="asdfd">Créé par</label>
                                <select class="form-control select2" name="createdBy">
                                    <option value="all" @selected(request('createdBy') === 'all')>---</option>
                                    <option value="me" @selected(request('createdBy') === 'me')>moi</option>
                                </select>
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



        @if (isset($companies) && !$companies->count() > 0)
            <div class="alert alert-warning">
                <strong>Aucune information n'est disponible !</strong>
                <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
            </div>
        @endif
        @if (isset($companies) && $companies->count() > 0)
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div>Trouvez <b>{{ $companies->total() }}</b>
                                {{ $companies->total() > 1 ? 'résultats' : 'résultat' }}
                            </div>
                            <span>Page <b>{{ $companies->currentPage() }}/{{ $companies->lastPage() }}</b></span>
                        </div>
                        <div class="col-md-6">
                            <div class="table-navigation">
                                {{ $companies->links() }}
                            </div>
                        </div>
                    </div>
                    <table class="table table-striped database-tables">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Société</th>
                                <th>Address</th>
                                <th>Établissement</th>
                                <th>Statut</th>
                                <th>Créé le</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($companies as $company)
                                <tr>
                                    <th>{{ $company->id }}</th>
                                    <td>
                                        <div><strong>{{ $company->name }}</strong></div>
                                        <div>{{ $company->email }}</div>
                                        <div>{{ $company->phone }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $company->address }}</div>
                                        <div>{{ $company->postal_code . ' ' . $company->city }}</div>
                                        <div>{{ $company->country . ' ' . $company->country_code }}</div>
                                    </td>
                                    <td>{{ $company->establishment
                                        ? $company->establishment->name
                                        : ($company->other_establishment
                                            ? $company->other_establishment
                                            : '-') }}
                                    </td>
                                    <td>
                                        @if (!$company->status && $company->invite_code)
                                            @if ($company->users_temp)
                                                <span class="label label-warning">En cours de remplir</span>
                                            @else
                                                <span class="label label-danger">Aucune donnée fournie</span>
                                            @endif
                                        @endif
                                        @if ($company->status == App\Status::PENDING->value && $company->invite_code && $company->users_temp)
                                            <span class="label label-success">Formulaire complété</span>
                                        @endif

                                        @if (!$company->id_odoo && sizeof($company->users) > 0)
                                            <span class="label label-warning">Jamais connecté Odoo</span>
                                        @endif

                                        @if ($company->id_odoo && sizeof($company->users) > 0 && !$company->invite_code)
                                            <span class="label label-primary">Actif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $company->created_at }}</div>
                                        @if ($company->createdBy)
                                            <p>{{ $company->createdBy->name . ' ' . $company->createdBy->first_name }}</p>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('voyager.companies.show', $company->id) }}"
                                            class="btn btn-sm btn-warning">
                                            <i class="voyager-people"></i>
                                            <span class="hidden-xs hidden-sm">Membres</span>
                                        </a>

                                        <a href="{{ route('voyager.companies.edit', $company->id) }}"
                                            class="btn btn-sm btn-primary">
                                            <i class="voyager-edit"></i>
                                            <span class="hidden-xs hidden-sm">Modifier</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="table-navigation" style="margin-bottom: 2em">
                        {{ $companies->links() }}
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
        $(document).ready(function() {});
    </script>
@stop
