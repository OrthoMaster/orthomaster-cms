@extends('voyager::master')

@section('page_title', ' Utilisateurs')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-people"></i> Utilisateurs
        </h1>
        <a href="{{ route('account.create') }}" class="btn btn-success btn-add-new">
            <i class="voyager-plus"></i> <span>Ajouter compte</span>
        </a>
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
                        <form class="row" action="{{ route('accounts.index') }}" method="GET">
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
                                <label for="display_name">Prénom</label>
                                <input type="text" class="form-control" name="first_name"
                                    value="{{ request('first_name') }}" placeholder="Prénom">
                            </div>
                            <div class="col-md-3">
                                <label for="asdfd">Statut</label>
                                <select class="form-control select2" name="status">
                                    <option value="2" @selected(request('status') === '2')>{{ App\Status::PENDING->label() }}
                                    </option>
                                    <option value="1" @selected(request('status') === '1')>{{ App\Status::ACTIVE->label() }}
                                    </option>
                                    <option value="">---</option>
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



        @if (isset($users) && !$users->count() > 0)
            <div class="alert alert-warning">
                <strong>Aucune information n'est disponible !</strong>
                <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
            </div>
        @endif
        @if (isset($users) && $users->count() > 0)
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div>Trouvez <b>{{ $users->total() }}</b> {{ $users->total() > 1 ? 'résultats' : 'résultat' }}
                            </div>
                            <span>Page <b>{{ $users->currentPage() }}/{{ $users->lastPage() }}</b></span>
                        </div>
                        <div class="col-md-6">
                            <div class="table-navigation">
                                {{ $users->links() }}
                            </div>
                        </div>
                    </div>
                    <table class="table table-striped database-tables">
                        <thead>
                            <tr>
                                <th>Nom complet</th>
                                <th>Contact</th>
                                <th>Infos</th>
                                <th>Statut</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <p><strong>{{ $user->name . ' ' . $user->first_name }}</strong></p>
                                    </td>
                                    <td>
                                        <div>{{ $user->email }}</div>
                                        <p>{{ $user->phone }}</p>
                                    </td>
                                    <td>
                                        @if ($user->student_flg)
                                            <p>Étudiant</p>
                                        @endif

                                        @if ($user->company_id)
                                            <div><strong>{{ $user->company->name }}</strong></div>
                                            <small>{{ $user->company->address . ' ' . $user->company->postal_code . ' ' . $user->company->city }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->status == 2)
                                            <label class="label label-warning">En attend</label>
                                        @elseif ($user->status == 1)
                                            <label class="label label-primary">Actif</label>

                                            @if ($user->password_temp)
                                                <label class="label label-info">Non connecté</label>
                                            @endif
                                        @else
                                            <label class="label label-danger">Désactivé</label>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        {{-- @if (!$user->student_flg)
                                            <a href="{{ route('account.training-permissions', $user->id) }}"
                                                class="btn btn-sm btn-primary">
                                                <i class="voyager-book"></i>
                                                <span class="hidden-xs hidden-sm">Formations</span>
                                            </a>
                                        @endif --}}
                                        <a href="{{ route('account.edit', $user->id) }}" class="btn btn-sm btn-warning">
                                            <i class="voyager-edit"></i>
                                            <span class="hidden-xs hidden-sm">Modifier</span>
                                        </a>

                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="table-navigation" style="margin-bottom: 2em">
                        {{ $users->links() }}
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
