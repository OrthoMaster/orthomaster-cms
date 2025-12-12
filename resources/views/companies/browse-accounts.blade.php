@extends('voyager::master')

@section('page_title', 'Société')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-company"></i> {{ $company->name }}
            @if ($company->id_odoo)
                <span class="label label-success">Connecté Odoo</span>
            @else
                <span class="label label-warning">Jamais connecté Odoo</span>
            @endif
        </h1>

        @if (Auth::user()->role->name == 'admin')
            @if (!$company->id_odoo)
                <a class="btn btn-warning" style="margin-right: 5px" href="{{ route('companies.odoo.connect', $company->id) }}"
                    onclick="return confirm('Etes-vous sûr(e) ? Cette action est irréversible.')">
                    <i class="voyager-warning"></i> <span>Créer connexion Odoo</span>
                </a>
            @endif

            @if ($company->id_odoo)
                <a class="btn btn-primary" style="margin-right: 5px"
                    href="{{ route('companies.emails.send', $company->id) }}"
                    onclick="return confirm('Etes-vous sûr(e) ?')">
                    <span>Envoyer emails d'invitation</span>
                </a>

                <a class="btn btn-danger" style="margin-right: 5px"
                    href="{{ route('companies.integrity-check', $company->id) }}"
                    onclick="return confirm('Etes-vous sûr(e) ?')">
                    <span>Vérification d'intégrité</span>
                </a>
            @endif
        @endif

        {{-- <a href="{{ route('companies.training-permissions', $company->id) }}" class="btn btn-primary"
            style="margin-right: 5px">
            <i class="voyager-book"></i> <span>Gérer Formations</span>
        </a> --}}

        @if (!$company->invite_code)
            <a href="{{ route('companies.invite-code.generate', $company->id) }}" class="btn btn-warning"
                style="margin-right: 5px">
                <i class="voyager-external"></i> <span>Générer un code de partage</span>
            </a>

            <a target="_blank" href="{{ route('account.create', ['company' => $company->id]) }}" class="btn btn-success"
                style="margin-right: 5px">
                <i class="voyager-plus"></i> <span>Ajouter utilisateur</span>
            </a>
        @endif

    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        @if ($company->invite_code)
            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Invitation</h3>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <label class="col-md-3 control-label" for="code">Code d'invitation</label>
                                <div class="col-md-5">
                                    <input id="code" type="text" class="form-control" disabled
                                        value="{{ $company->invite_code }}">
                                </div>
                                <div class="col-md-4">
                                    <button id="copy-btn" type="button" class="btn btn-warning"
                                        style="margin-top: 0;">Copier
                                        le lien</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($company->users_temp)
                    <div class="col-md-8">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title">Liste provisoire
                                    @if ($company->status == App\Status::PENDING->value)
                                        <span class="label label-success">Soumis</span>
                                    @else
                                        <span class="label label-warning">En cours de remplir</span>
                                    @endif
                                </h3>
                            </div>
                            <div class="panel-body">
                                <table class="table">
                                    <thead>
                                        <th>Utilisateur</th>
                                        <th>Profession</th>
                                        <th>Organisme</th>
                                        <th>Statut</th>
                                    </thead>
                                    <tbody>
                                        @foreach ($company->users_temp as $user)
                                            <tr>
                                                <td>
                                                    <div><strong class="text-uppercase">{{ $user['name'] }}</strong>
                                                        {{ $user['firstname'] }}
                                                    </div>
                                                    <div>{{ $user['email'] }}</div>
                                                    @if ($user['isLeader'])
                                                        <span class="label label-primary">Responsable</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($user['isStudent'])
                                                        <div><span class="label label-success">Étudiant</span></div>
                                                    @endif
                                                    <div><small>Fonction: </small>
                                                        <strong>{{ $user['job'] ? $user['jobLabel'] : '-' }}</strong>
                                                    </div>
                                                    <div><small>N° pro: </small>{{ $user['rppsTypeLabel'] }}
                                                        <strong>{{ $user['rpps'] }}</strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><strong>{{ $user['organizationLabel'] }}</strong></div>
                                                    @if ($user['organizationLabel'] == 'OPCO-EP')
                                                        <div><small>N° SECU: </small>
                                                            <strong>{{ $user['secu'] ?? '-' }}</strong>
                                                        </div>
                                                        <div><small>Nom jeune fille: </small>
                                                            <strong>{{ $user['firstnameBirth'] }}</strong>
                                                        </div>
                                                        <div><small>Date naissance: </small>
                                                            <strong>{{ $user['dob'] ?? '' }}</strong>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($user['hasError'])
                                                        <div><span class="label label-warning">Manquant</span></div>
                                                        <i
                                                            class="text-warning"><small>{{ $user['errorMessage'] }}</small></i>
                                                    @else
                                                        <div><span class="label label-success">Valide</span></div>
                                                    @endif
                                                </td>
                                            <tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($company->status == App\Status::PENDING->value)
                                <div class="panel-footer">
                                    <a class="btn btn-warning" href="{{ route('companies.back-editable', $company->id) }}">
                                        <i class="voyager-edit"></i> <span>Continuer à remplir</span>
                                    </a>
                                    <button class="btn btn-danger" onclick="fillData()">
                                        <i class="voyager-external"></i> <span>Remplir la base de données & Supprimer code
                                            d'invitation</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if (sizeof($company->users) > 0)
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
                                            <th>Date de naissance</th>
                                            <th>Fonction</th>
                                            <th>Organisme</th>
                                            <th>SECU</th>
                                            <th>Statut</th>
                                            <th class="actions text-right dt-not-orderable">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($company->users as $user)
                                            <tr>
                                                <td>
                                                    <div><strong>{{ $user->name }}</strong>
                                                        {{ $user->first_name }} @if ($user->pivot->manager_flg)
                                                            <label class="label label-primary">Responsable</label>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ $user->email }}</td>
                                                <td>
                                                    {{ $user->date_of_birth ? $user->date_of_birth->format('d/m/Y') : '' }}
                                                </td>
                                                <td>
                                                    {{ $user->profession_id ? $user->profession->name : $user->other_profession }}
                                                </td>
                                                <td>
                                                    {{ $user->funding_organization_id ? $user->organization->name : ' ' }}
                                                </td>
                                                <td>
                                                    {{ $user->no_secu }}
                                                </td>
                                                <td>
                                                    @if ($user->id_odoo_partner)
                                                        <label class="label label-success">Connecté Odoo</label>

                                                        @if (!$user->id_odoo_user)
                                                            <label class="label label-danger">Non compte Odoo</label>
                                                        @endif
                                                    @endif


                                                    @if (!$user->id_odoo_partner && !$user->id_odoo_user)
                                                        <label class="label label-warning">Jamais connecté Odoo</label>
                                                    @endif

                                                    @if ($user->status == 1 && $user->password_temp)
                                                        <label class="label label-info">Non connecté</label>
                                                    @endif
                                                </td>
                                                <td class="no-sort no-click bread-actions text-right">
                                                    @if (!$user->id_odoo_partner)
                                                        @if ($user->pivot->manager_flg)
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
                                                    @endif

                                                    @if ($company->id_odoo && (!$user->id_odoo_partner || !$user->id_odoo_user))
                                                        <a class="btn btn-success" style="margin-right: 5px"
                                                            href="{{ route('account.sync', $user->id) }}"
                                                            onclick="return confirm('Etes-vous sûr(e) ?')">
                                                            <i class="voyager-person"></i> <span>Réconnecter Odoo</span>
                                                        </a>
                                                    @endif

                                                    @if ($user->status == 1 && $user->password_temp)
                                                        <a class="btn btn-info" style="margin-right: 5px"
                                                            href="{{ route('account.resend', $user->id) }}"
                                                            onclick="return confirm('Etes-vous sûr(e) ?')">
                                                            <span>Réenvoyer email</span>
                                                        </a>
                                                    @endif

                                                    @if (!$user->id_odoo_partner)
                                                        <a class="btn btn-sm btn-warning" target="_blank"
                                                            href="{{ route('account.edit', ['id' => $user->id, 'linkFrom' => 'company']) }}">
                                                            <i class="voyager-edit"></i>
                                                            <span class="hidden-xs hidden-sm">Modifier</span>
                                                        </a>
                                                    @endif
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
        @endif
    </div>

    <form id="update-manager-form" method="post"
        action="{{ route('companies.manager.update', ['id' => $company->id, 'userId' => 'userId']) }}">@csrf</form>
    <form id="fill-data-form" method="post" action="{{ route('companies.fill-data', $company->id) }}">@csrf</form>
@stop

@section('css')
    <style>
        .btn i {
            position: relative;
            top: 2px;
        }
    </style>
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
        const copyBtn = document.getElementById('copy-btn');
        copyBtn.addEventListener('click', () => {
            const link = "{{ config('app.auth_url') . '/group/register/' . $company->invite_code }}";
            navigator.clipboard.writeText(link);
            copyBtn.innerText = "Lien copié";
            setTimeout(() => copyBtn.innerText = "Copier le lien", 1500);
        });

        function updateManager($userId) {
            var url = "{{ route('companies.manager.update', ['id' => $company->id, 'userId' => 'userId']) }}";
            url = url.replace('userId', $userId);

            let form = document.getElementById('update-manager-form');
            form.action = url;
            form.submit();
        }

        function fillData() {
            if (confirm("Etes-vous sûr(e) de remplir les données ? Cette action est irréversible.")) {
                let form = document.getElementById('fill-data-form');
                form.submit();
            }
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
