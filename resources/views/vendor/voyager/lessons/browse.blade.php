@extends('voyager::master')

@section('page_title', 'Leçons')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-documentation"></i> Leçons
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
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Titre</th>
                                        <th class="actions text-right dt-not-orderable">
                                            {{ __('voyager::generic.actions') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lessons as $lesson)
                                        <tr>
                                            <td>{{ $lesson->id }}</td>
                                            <td>{{ $lesson->title }}</td>

                                            <td class="no-sort no-click bread-actions">
                                                <a class="btn btn-sm btn-success pull-right" style="margin-right: 5px"
                                                    href={{ route('content.edit', $lesson->id) }}>
                                                    <i class="voyager-font"></i>
                                                    <span class="hidden-xs hidden-sm">Contenu</span>
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
@stop

@section('javascript')
    <script>
    </script>
@stop
