@extends('voyager::master')

@section('page_title', ' Questions du public')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-chat"></i> Questions du public
        </h1>
        <a href="{{ route('dashboard.congres.create') }}" class="btn btn-success">
            <i class="voyager-plus"></i> <span>Nouvelle table ronde</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="panel panel-bordered">
            <div class="panel-body">
                @if ($boards->isEmpty())
                    <p>Aucune table ronde pour le moment.</p>
                @else
                    <table class="table table-striped database-tables">
                        <thead>
                            <tr>
                                <th>Table ronde</th>
                                <th>Congrès</th>
                                <th>Code public</th>
                                <th>Code intervenants</th>
                                <th>État</th>
                                <th>Questions</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($boards as $board)
                                <tr>
                                    <td>{{ $board->title }}</td>
                                    <td>{{ $board->congress_name }}</td>
                                    <td><code style="color: #A85F00; background: #FFF3E0">{{ $board->code }}</code></td>
                                    <td><code style="color: #1D3470; background: #E8EEFB">{{ $board->operator_code }}</code></td>
                                    <td>
                                        @if ($board->status === 'live')
                                            <span class="label label-success">En cours</span>
                                        @elseif ($board->status === 'closed')
                                            <span class="label label-default">Terminée</span>
                                        @else
                                            <span class="label label-warning">Brouillon</span>
                                        @endif
                                    </td>
                                    <td>{{ $board->questions_count }}</td>
                                    <td style="white-space: nowrap">
                                        <a href="{{ route('dashboard.congres.live', $board->id) }}" class="btn btn-sm btn-primary">Ouvrir</a>
                                        <a href="{{ route('dashboard.congres.edit', $board->id) }}" class="btn btn-sm btn-default">Modifier</a>
                                        <form action="{{ route('dashboard.congres.destroy', $board->id) }}" method="POST" style="display: inline"
                                            onsubmit="return confirm('Supprimer cette table ronde et toutes ses questions ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@stop
