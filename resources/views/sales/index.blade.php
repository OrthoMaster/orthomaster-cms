@extends('voyager::master')

@section('page_title', 'Portefeuille clients')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-people"></i> Portefeuille clients
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        @if (isset($customers) && !$customers->count() > 0)
            <div class="alert alert-warning">
                <strong>Aucune information n'est disponible !</strong>
                <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
            </div>
        @endif

        <div class="row">
            <div class="col-md-4">
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title">Filtres</h3>
                    </div>
                    <div class="panel-body">
                        <form class="row" action="{{ route('sales.index') }}" method="GET">
                            @if (!$isSale)
                                <div class="col-md-12">
                                    <label for="sale">Employe</label>
                                    <select id="sale" class="form-control select2" name="sale_id">
                                        <option value="">---</option>

                                        @foreach ($sales as $sale)
                                            <option value="{{ $sale->id }}" @selected(request()->get('sale_id') == $sale->id)>
                                                {{ $sale->name . ' ' . $sale->first_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="col-md-12">
                                <label for="display_name">Client</label>
                                <input type="text" class="form-control" name="customer"
                                    value="{{ request()->get('customer') }}"
                                    placeholder="Laisser vide pour afficher tous les clients">
                            </div>
                            <div class="col-md-12" style="margin-bottom: 0">
                                <button type="submit" class="btn btn-primary pull-right">
                                    Voir clients
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                @if (isset($customers) && $customers->count() > 0)
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Clients</h3>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div>Total <b>{{ $customers->total() }}/{{ $totalCustomers }}</b>
                                            {{ $totalCustomers > 1 ? 'clients' : 'client' }}
                                        </div>
                                        <span>Page
                                            <b>{{ $customers->currentPage() }}/{{ $customers->lastPage() }}</b></span>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="table-navigation">
                                            {{ $customers->links() }}
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-striped database-tables">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom complet</th>
                                            <th>Email</th>
                                            <th class="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($customers as $user)
                                            <tr>
                                                <th class="text-center">{{ $user->id }}</th>
                                                <td>
                                                    <p><strong>{{ $user->name . ' ' . $user->first_name }}</strong></p>
                                                </td>
                                                <td>{{ $user->email }}</td>
                                                <td class="text-right">
                                                    <button class="btn btn-sm btn-danger"
                                                        onclick="removeClient('{{ $user->id }}')">
                                                        <i class="voyager-edit"></i>
                                                        <span class="hidden-xs hidden-sm">Retirer</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="table-navigation" style="margin-bottom: 2em">
                                    {{ $customers->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <form id="action-form" method="post" action="">@csrf</form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
@stop

@section('javascript')
    <!-- DataTables -->
    <script>
        function removeClient($cusId) {
            if (confirm("Etes-vous sûr(e) de retirer le client ?")) {
                var url = "{{ route('sales.remove', ['saleId' => 'saleId', 'userId' => 'userId']) }}";
                url = url.replace('saleId', '{{ $saleId }}');
                url = url.replace('userId', $cusId);

                let form = document.getElementById('action-form');
                form.action = url;
                form.submit();
            }
        }
    </script>
@stop
