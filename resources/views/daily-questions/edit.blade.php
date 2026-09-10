@extends('voyager::master')

@section('page_title', ' Modifier la question du jour')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-edit"></i> Modifier la question <small>#{{ $question->id }}</small>
        </h1>
    </div>
@stop

@section('content')
    @include('partials._embed')

    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('voyager.daily-questions.update', $question->id) }}" method="POST" id="question-form">
            @csrf
            @method('PUT')
            @include('daily-questions._form', ['submitLabel' => 'Enregistrer'])
        </form>
    </div>
@stop

@section('css')
    @include('daily-questions._form_styles')
@stop

@section('javascript')
    @include('daily-questions._form_scripts')
@stop