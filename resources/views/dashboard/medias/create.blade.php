@extends('voyager::master')

@section('page_title', ' Nouveau média')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-plus"></i> Nouveau média
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('medias.store') }}" method="POST"
              enctype="multipart/form-data" id="media-form">
            @csrf
            @include('dashboard.medias._form', ['submitLabel' => 'Créer'])
        </form>
    </div>
@stop

@section('javascript')
    @include('dashboard.medias._form_scripts')
@stop