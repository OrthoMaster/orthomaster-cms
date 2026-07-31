@extends('voyager::master')

@section('page_title', 'Import / Export pathologies')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title"><i class="voyager-list"></i> Pathologies en masse</h1>
    </div>
@stop

@section('content')
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-bordered">
                <div class="panel-heading"><h3 class="panel-title">1. Exporter</h3></div>
                <div class="panel-body">
                    <p class="text-muted" style="font-size:12px;">
                        Génère un .xlsx avec deux onglets :<br>
                        <strong>Produits</strong> — {{ $productCount }} ligne(s), colonne
                        <code>pathology_filters</code> avec liste déroulante.<br>
                        <strong>Groupes</strong> — deux exemples prêts à l'emploi. Ajoutez vos
                        propres codes ; collez dans la colonne <code>keys</code> la liste copiée
                        depuis l'écran d'édition d'un produit.
                    </p>
                    <a href="{{ route('products.predata.export') }}" class="btn btn-primary">
                        <i class="voyager-download"></i> Télécharger le fichier
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-bordered">
                <div class="panel-heading"><h3 class="panel-title">2. Importer</h3></div>
                <div class="panel-body">
                    <p class="text-muted" style="font-size:12px;">
                        Un aperçu est affiché avant toute écriture. Les filtres existants des
                        produits concernés sont <strong>remplacés</strong> ; une ligne sans code
                        est ignorée. Une sauvegarde JSON est écrite avant application.
                    </p>
                    <form method="POST" action="{{ route('products.predata.preview') }}"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <input type="file" name="file" accept=".xlsx,.xls" class="form-control" required>
                            @error('file')<small class="help-block text-danger">{{ $message }}</small>@enderror
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="voyager-upload"></i> Analyser
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@stop