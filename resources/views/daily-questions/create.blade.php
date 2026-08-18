@extends('voyager::master')

@section('page_title', ' Nouvelle question du jour')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-plus"></i> Nouvelle question du jour
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('voyager.daily-questions.store') }}" method="POST" id="question-form">
            @csrf
            @include('daily-questions._form', ['submitLabel' => 'Créer'])
        </form>
    </div>
@stop

@section('css')
    @include('daily-questions._form_styles')
@stop

@section('javascript')
    @include('daily-questions._form_scripts')
@stop