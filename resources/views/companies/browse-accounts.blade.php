@extends('voyager::master')

@section('page_title', 'Société')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-company"></i> {{ $company->name }}
        </h1>
        <a target="_blank" href="{{ route('account.create', ['company' => $company->id]) }}" class="btn btn-success btn-add-new"
            style="margin-right: 5px">
            <i class="voyager-plus"></i> <span>Ajouter compte</span>
        </a>
        <a href="#" class="btn btn-warning">
            <i class="voyager-check"></i> <span>Activer toute</span>
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
                        <h3 class="panel-title">Liste des comptes</h3>

                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom - Prénom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Étudiant</th>
                                        <th>Fonction</th>
                                        <th>Organisme</th>
                                        <th>Statut</th>
                                        <th class="actions text-right dt-not-orderable">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($company->users as $user)
                                        <tr>
                                            <td>
                                                <div><strong>{{ $user->name }}</strong>
                                                    {{ $user->first_name }} @if ($user->role->name === 'user-manager')
                                                        <label class="label label-danger">Responsable</label>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $user->email }}</td>
                                            <td>{{ $user->role->display_name }}</td>
                                            <td class="text-center">
                                                @if ($user->student_flg)
                                                    <span>Étudiant</span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $user->professtion_id ? $user->profession->name : $user->other_profession }}
                                            </td>
                                            <td>
                                                {{ $user->funding_organization_id ? $user->organization->name : ' ' }}
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
                                            <td class="no-sort no-click bread-actions text-right">
                                                @if ($user->role->name == 'user-manager')
                                                    <button class="btn btn-sm btn-danger"
                                                        onclick="updateManager('{{ $user->id }}')"
                                                        style="margin-right: 5px">
                                                        <i class="voyager-x"></i>
                                                        <span class="hidden-xs hidden-sm">Retirer Responsable</span>
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-sm btn-primary"
                                                        onclick="updateManager('{{ $user->id }}');"
                                                        style="margin-right: 5px">
                                                        <i class="voyager-pirate-hat"></i>
                                                        <span class="hidden-xs hidden-sm">Définir Responsable</span>
                                                    </button>
                                                @endif


                                                <a class="btn btn-sm btn-warning" target="_blank"
                                                    href="{{ route('account.edit', ['id' => $user->id, 'linkFrom' => 'company']) }}">
                                                    <i class="voyager-edit"></i>
                                                    <span class="hidden-xs hidden-sm">Modifier</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="update-manager-form" method="post"
        action="{{ route('companies.manager.update', ['id' => $company->id, 'userId' => 'userId']) }}">@csrf</form>
@stop

@section('css')
    @if (config('dashboard.data_tables.responsive'))
        <link rel="stylesheet" href="{{ voyager_asset('lib/css/responsive.dataTables.min.css') }}">
    @endif
@stop

@section('javascript')
    <!-- DataTables -->
    @if (config('dashboard.data_tables.responsive'))
        <script src="{{ voyager_asset('lib/js/dataTables.responsive.min.js') }}"></script>
    @endif
    <script>
        function updateManager($userId) {
            var url = "{{ route('companies.manager.update', ['id' => $company->id, 'userId' => 'userId']) }}";
            url = url.replace('userId', $userId);

            let form = document.getElementById('update-manager-form');
            form.action = url;
            form.submit();
        }

        $(document).ready(function() {
            var table = $('#dataTable').DataTable({!! json_encode(
                array_merge(
                    [
                        'order' => 0,
                        'language' => __('voyager::datatable'),
                        'columnDefs' => [['targets' => 'dt-not-orderable', 'searchable' => false, 'orderable' => false]],
                    ],
                    config('voyager.dashboard.data_tables', []),
                ),
                true,
            ) !!});
        });
    </script>
@stop
