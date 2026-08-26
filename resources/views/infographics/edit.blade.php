@extends('voyager::master')

@section('page_title', ' Modifier l\'infographie')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-edit"></i> Modifier l'infographie <small>#{{ $infographic->id }}</small>
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('infographics.update', $infographic->id) }}" method="POST"
              enctype="multipart/form-data" id="infographic-form">
            @csrf
            @method('PUT')
            @include('infographics._form', ['submitLabel' => 'Enregistrer'])
        </form>
    </div>
@stop

@section('css')
    @include('infographics._form_styles')
@stop

@section('javascript')
    @include('infographics._form_scripts')
@stop