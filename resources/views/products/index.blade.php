@extends('voyager::master')

@section('page_title', 'Produits')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-bag"></i> Produits
        </h1>
        <a href="{{ route('products.create') }}" class="btn btn-success btn-add-new">
            <i class="voyager-plus"></i> <span>Ajouter un nouveau</span>
        </a>

        <a href="{{ route('rules.products') }}" target="_blank" class="btn btn-warning btn-add-new">
            <i class="voyager-helm"></i> <span>Filtre de règles</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Fabricant</th>
                                <th>Filtres</th>
                                <th style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($products as $p)
                            <tr>
                                <td>{{ $p->name }}</td>
                                <td>{{ $p->category }}</td>
                                <td>{{ optional($p->manufacturer)->name }}</td>
                                <td>{{ $p->filters_count ?? $p->filters()->count() }}</td>
                                <td>
                                    <a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-primary">
                                        <i class="voyager-edit"></i> Modifier
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted" style="padding: 40px;">Aucun produit pour le moment.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $products->links() }}
            </div>
        </div>
    </div>
@stop