@extends('voyager::master')

@section('page_title', 'Aperçu import')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title"><i class="voyager-eye"></i> Aperçu avant import</h1>
    </div>
@stop

@section('content')
<div class="page-content container-fluid">

    <div class="row" style="margin-bottom:16px;">
        @php
            $cards = [
                ['Groupes lus',        count($groups),   '#2a9d8f'],
                ['Produits modifiés',  count($changes),  '#1d3557'],
                ['Lignes ignorées',    $skipped,         '#8b95a8'],
                ['Erreurs',            count($errors),   count($errors) ? '#c1121f' : '#8b95a8'],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $color])
            <div class="col-md-3">
                <div style="border:1px solid #e8e8e8;border-radius:6px;padding:14px;text-align:center;">
                    <div style="font-size:26px;font-weight:700;color:{{ $color }};">{{ $value }}</div>
                    <div style="font-size:12px;color:#8b95a8;text-transform:uppercase;letter-spacing:.5px;">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if (count($errors))
        <div class="panel panel-bordered" style="border-left:4px solid #c1121f;">
            <div class="panel-heading"><h3 class="panel-title" style="color:#c1121f;">Erreurs — import bloqué</h3></div>
            <div class="panel-body">
                <ul style="margin:0;padding-left:18px;font-size:12.5px;">
                    @foreach ($errors as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (count($warnings))
        <div class="panel panel-bordered" style="border-left:4px solid #e8a300;">
            <div class="panel-heading"><h3 class="panel-title" style="color:#8a6300;">Avertissements</h3></div>
            <div class="panel-body">
                <ul style="margin:0;padding-left:18px;font-size:12.5px;">
                    @foreach ($warnings as $w)<li>{{ $w }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="panel panel-bordered">
        <div class="panel-heading"><h3 class="panel-title">Modifications ({{ count($changes) }})</h3></div>
        <div class="panel-body" style="max-height:520px;overflow:auto;">
            @if (empty($changes))
                <p class="text-muted" style="margin:0;">Aucune modification — les produits ont déjà ces filtres.</p>
            @else
                <table class="table table-condensed" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th style="width:60px;">id</th>
                            <th>Produit</th>
                            <th style="width:160px;">Groupe</th>
                            <th style="width:70px;">Total</th>
                            <th>Ajouts / Retraits</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($changes as $c)
                        <tr>
                            <td>{{ $c['id'] }}</td>
                            <td>{{ $c['name'] }}</td>
                            <td><code>{{ $c['code'] }}</code></td>
                            <td>{{ $c['total'] }}</td>
                            <td>
                                @if ($c['added'])
                                    <span style="color:#2a9d8f;">+{{ count($c['added']) }}</span>
                                    <span style="color:#8b95a8;">{{ implode(', ', array_slice($c['added'], 0, 6)) }}@if(count($c['added']) > 6) …@endif</span>
                                @endif
                                @if ($c['removed'])
                                    <br><span style="color:#c1121f;">−{{ count($c['removed']) }}</span>
                                    <span style="color:#8b95a8;">{{ implode(', ', array_slice($c['removed'], 0, 6)) }}@if(count($c['removed']) > 6) …@endif</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div style="margin-top:18px;">
        @if (empty($errors) && count($changes))
            <form method="POST" action="{{ route('products.predata.apply') }}" style="display:inline;"
                  onsubmit="return confirm('Appliquer {{ count($changes) }} modification(s) ? Les filtres actuels seront remplacés.');">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="btn btn-primary">
                    <i class="voyager-check"></i> Appliquer
                </button>
            </form>
        @endif
        <a href="{{ route('products.predata.index') }}" class="btn btn-default">Annuler</a>
    </div>
</div>
@stop