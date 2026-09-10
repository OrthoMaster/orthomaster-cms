@extends('voyager::master')

@section('page_title', ' Nouvelle infographie')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-plus"></i> Nouvelle infographie
        </h1>
    </div>
@stop

@section('content')
    @include('partials._embed')

    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('infographics.store') }}" method="POST"
              enctype="multipart/form-data" id="infographic-form">
            @csrf
            @include('infographics._form', ['submitLabel' => 'Créer'])
        </form>
    </div>
@stop

@section('css')
    @include('infographics._form_styles')
@stop

@section('javascript')
    @include('infographics._form_scripts')
@stop