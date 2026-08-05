@extends('voyager::master')

@section('page_title', ' Modifier le média')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-edit"></i> Modifier le média <small>#{{ $media->id_content }}</small>
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('medias.update', $media->id_content) }}" method="POST">
            @csrf
            @method('PUT')
            @include('dashboard.medias._form', ['submitLabel' => 'Enregistrer'])
        </form>
    </div>
@stop

@section('javascript')
    @include('dashboard.medias._form_scripts')
@stop