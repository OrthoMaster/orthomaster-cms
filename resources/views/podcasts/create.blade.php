@extends('voyager::master')

@section('page_title', ' Nouveau podcast')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-plus"></i> Nouveau podcast
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('voyager.podcasts.store') }}" method="POST" id="podcast-form">
            @csrf
            @include('podcasts._form', ['submitLabel' => 'Créer'])
        </form>
    </div>
@stop

@section('css')
    @include('podcasts._form_styles')
@stop

@section('javascript')
    @include('podcasts._form_scripts')
@stop