@extends('voyager::master')

@section('page_title', 'Add product')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-plus"></i> Add product
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        <div class="row">
            <div class="col-md-12">
                <form method="POST" action="{{ route('products.store') }}">
                    @include('products._form')
                </form>
            </div>
        </div>
    </div>
@stop

@section('css')
    @include('products._form_styles')
@stop

@section('javascript')
    @include('products._form_scripts')
@stop