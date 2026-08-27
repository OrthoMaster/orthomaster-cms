@extends('voyager::master')

@section('page_title', ' Questions du jour')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-question"></i> Questions du jour
        </h1>
        <a href="{{ route('voyager.daily-questions.create') }}" class="btn btn-success">
            <i class="voyager-plus"></i> <span>Ajouter une question</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                @if (!$questions->count() > 0)
                    <div class="alert alert-warning">
                        <strong>Aucune information n'est disponible !</strong>
                    </div>
                @else
                    <div class="panel panel-bordered">
                        <div class="panel-body">
                            <div class="table-responsive">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div>Trouvez <b>{{ $questions->total() }}</b>
                                            {{ $questions->total() > 1 ? 'résultats' : 'résultat' }}
                                        </div>
                                        <span>Page
                                            <b>{{ $questions->currentPage() }}/{{ $questions->lastPage() }}</b></span>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="table-navigation">
                                            {{ $questions->links() }}
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-striped database-tables">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Titre</th>
                                            <th>Question</th>
                                            <th>Sujet</th>
                                            <th class="text-right">Participants</th>
                                            <th>Publication</th>
                                            <th>Fin</th>
                                            <th class="actions text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($questions as $q)
                                            <tr>
                                                <td>{{ $q->id }}</td>
                                                <td><b>{{ $q->title }}</b></td>
                                                <td><b>{{ \Illuminate\Support\Str::limit($q->question, 50) }}</b></td>
                                                <td>{{ $q->topic_name ?: '—' }}</td>
                                                <td class="text-right">{{ number_format($q->count ?? 0, 0, ',', ' ') }}</td>
                                                <td>
                                                    {{ $q->published_at ? \Illuminate\Support\Carbon::parse($q->published_at)->format('d/m/Y H:i') : '—' }}
                                                </td>
                                                <td>
                                                    {{ $q->expired_at ? \Illuminate\Support\Carbon::parse($q->expired_at)->format('d/m/Y H:i') : '—' }}
                                                </td>
                                                <td class="no-sort no-click text-right">
                                                    <button type="button" class="btn btn-success"
                                                        onclick="const b=this; navigator.clipboard.writeText('https://go.orthomaster.fr/open/daily-question/{{ $q->id }}'); b.textContent='✓ Copié !'; setTimeout(() => b.textContent='Partager', 1500);">
                                                        Partager
                                                    </button>
                                                    
                                                    <a href="{{ route('voyager.daily-questions.edit', $q->id) }}"
                                                        class="btn btn-sm btn-primary edit">
                                                        <i class="voyager-edit"></i>
                                                        <span class="hidden-xs hidden-sm">Modifier</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="table-navigation" style="margin-bottom: 2em">
                                    {{ $questions->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
@stop
