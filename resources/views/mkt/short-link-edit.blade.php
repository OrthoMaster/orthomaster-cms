@extends('voyager::master')

@section('page_title', 'Modifier le short link')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-cursor"></i> Modifier /sh/{{ $link->code }}
        </h1>
        <a href="{{ route('dashboard.short-links') }}" class="btn btn-default btn-add-new">
            <i class="voyager-list"></i> <span>Retour à la liste</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content container-fluid">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <form method="POST" action="{{ route('dashboard.short-links.update', $link) }}">
                            @csrf
                            @method('PUT')

                            {{-- Champs en lecture seule : ne pas casser les liens déjà diffusés --}}
                            <div class="form-group">
                                <label>Code (non modifiable)</label>
                                <input type="text" class="form-control" value="/sh/{{ $link->code }}" disabled
                                    style="font-family:monospace;background:#f8fafc;">
                            </div>

                            <div class="form-group">
                                <label>URL de destination (non modifiable)</label>
                                <input type="text" class="form-control" value="{{ $link->destination_url }}" disabled
                                    style="background:#f8fafc;">
                                <p class="help-block" style="font-size:12px;">
                                    Pour changer la destination, créez un nouveau short link afin de ne pas invalider
                                    les QR codes / liens déjà partagés.
                                </p>
                            </div>

                            <hr>

                            <div class="form-group">
                                <label for="title">Libellé interne</label>
                                <input type="text" name="title" id="title" class="form-control"
                                    value="{{ old('title', $link->title) }}" placeholder="ex : Campagne rentrée Facebook">
                            </div>

                            <div class="form-group">
                                <label for="utm_campaign">Campagne (utm_campaign)</label>
                                <input type="text" name="utm_campaign" id="utm_campaign" class="form-control"
                                    value="{{ old('utm_campaign', $link->utm_campaign) }}" placeholder="ex : rentree-2026">
                            </div>

                            <div class="form-group">
                                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                                    <input type="checkbox" name="tracking_enabled" value="1"
                                        {{ old('tracking_enabled', $link->tracking_enabled) ? 'checked' : '' }}>
                                    Tracking activé
                                </label>
                                <p class="help-block" style="font-size:12px;">
                                    Si désactivé, les clics ne seront plus enregistrés (la redirection continue de fonctionner).
                                </p>
                            </div>

                            <div style="margin-top:20px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="voyager-check"></i> Enregistrer
                                </button>
                                <a href="{{ route('dashboard.short-links.tracking', ['short_link_id' => $link->id]) }}"
                                   class="btn btn-info">
                                    <i class="voyager-graph"></i> Voir le tracking de ce lien
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop