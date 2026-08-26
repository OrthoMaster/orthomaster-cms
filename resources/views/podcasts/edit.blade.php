@extends('voyager::master')

@section('page_title', ' Modifier le podcast')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-edit"></i> Modifier le podcast <small>#{{ $podcast->id }}</small>
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('voyager.podcasts.update', $podcast->id) }}" method="POST" id="podcast-form">
            @csrf
            @method('PUT')
            @include('podcasts._form', ['submitLabel' => 'Enregistrer'])
        </form>
    </div>
@stop

@section('css')
    @include('podcasts._form_styles')
@stop

@section('javascript')
    @include('podcasts._form_scripts')
@stop