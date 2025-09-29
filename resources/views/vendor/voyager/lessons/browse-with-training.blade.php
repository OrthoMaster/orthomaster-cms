@extends('voyager::master')

@section('page_title', 'Leçons')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-documentation"></i> Leçons
        </h1>

        <a href="{{ route('lessons.create', $training->id) }}" class="btn btn-success btn-add-new">
            <i class="voyager-plus"></i> <span>Ajouter leçon</span>
        </a>

        <a href="{{ route('trainings.lessons.order', $training->id) }}" class="btn btn-primary">
            <i class="voyager-list"></i> <span>Ordre</span>
        </a>
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
                                        <th>Ordre</th>
                                        <th>Titre</th>
                                        <th>Statut</th>
                                        <th class="actions text-right dt-not-orderable">
                                            {{ __('voyager::generic.actions') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lessons as $lesson)
                                        <tr>
                                            <td>{{ $lesson->order_dsp }}</td>
                                            <td>{{ $lesson->title }}</td>
                                            <td>{{ $lesson->published_flg ? 'Publié' : 'Désactivé' }}</td>
                                            <td class="no-sort no-click bread-actions text-right">
                                                <a class="btn btn-sm btn-success" style="margin-right: 5px"
                                                    href={{ route('content.edit', $lesson->id) }}>
                                                    <i class="voyager-font"></i>
                                                    <span class="hidden-xs hidden-sm">Contenu</span>
                                                </a>
                                                @can('delete', app('App\Models\Training'))
                                                <a href="{{ route('voyager.lessons.edit', $lesson->id) }}"
                                                    class="btn btn-sm btn-warning">
                                                    <i class="voyager-edit"></i>
                                                    <span class="hidden-xs hidden-sm">Modifier</span>
                                                </a>
                                                @endcan
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
    <script></script>
@stop
