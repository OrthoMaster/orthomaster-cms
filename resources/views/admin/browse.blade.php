@extends('voyager::master')

@section('page_title', 'Administrateurs')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-pirate-hat"></i> Administrateurs
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th class="actions text-right dt-not-orderable">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($admins as $admin)
                                        <tr>
                                            <td>{{ $admin->name }}</td>
                                            <td>{{ $admin->email }}</td>
                                            <td>{{ $admin->role->display_name }}</td>
                                            <td class="no-sort no-click bread-actions">
                                                <a class="btn btn-sm btn-warning pull-right"
                                                    href={{ route('voyager.users.edit', $admin->id) }}>
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
