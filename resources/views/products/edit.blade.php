@extends('voyager::master')

@section('page_title', 'Edit product')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-edit"></i> Edit product
            <small class="text-muted">{{ $product->name }}</small>
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        <div class="row">
            <div class="col-md-12">
                <form method="POST" action="{{ route('products.update', $product->id) }}">
                    @method('PUT')
                    @include('products._form')
                </form>

                {{-- <form method="POST" action="{{ route('products.destroy', $product->id) }}"
                      onsubmit="return confirm('Delete this product? This cannot be undone.');"
                      style="margin-top: 30px;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="voyager-trash"></i> Delete product
                    </button>
                </form> --}}
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