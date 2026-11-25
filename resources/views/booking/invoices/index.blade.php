@extends('voyager::master')

@php
    use Carbon\Carbon;

    $fmt = fn ($value) => $value ? Carbon::parse($value)->timezone('Europe/Paris')->format('d/m/Y') : '—';
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' €';
@endphp

@section('page_title', 'Factures des rendez-vous')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-file-text"></i> Factures et avoirs des rendez-vous
    </h1>
    <a href="{{ route('booking.appointments.index') }}" class="btn btn-default">
        <i class="voyager-calendar"></i> Rendez-vous
    </a>
    <a href="{{ route('booking.revenue.index') }}" class="btn btn-default">
        <i class="voyager-dollar"></i> Revenus
    </a>
    <a href="{{ route('booking.invoices.index', array_merge(array_filter($filters), ['export' => 'csv'])) }}" class="btn btn-success">
        <i class="voyager-download"></i> Export CSV
    </a>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="panel panel-bordered">
            <div class="panel-body">
                <p class="booking-muted" style="margin-top: 0;">
                    Factures (VE/…) émises quand l'expert accepte (montant débité) et avoirs (RVE/…) émis à chaque remboursement.
                    Numéros donnés par Odoo. « À émettre » : Odoo n'a pas encore répondu — relancer depuis la fiche du rendez-vous.
                </p>
                <form class="row" action="{{ route('booking.invoices.index') }}" method="GET">
                    <div class="col-md-3">
                        <label for="q">Recherche</label>
                        <input class="form-control" type="text" name="q" id="q" value="{{ $filters['q'] }}" placeholder="Numéro, client, e-mail">
                    </div>
                    <div class="col-md-2">
                        <label for="kind">Type</label>
                        <select class="form-control" name="kind" id="kind">
                            <option value="">Factures et avoirs</option>
                            <option value="invoice" @selected($filters['kind'] === 'invoice')>Factures</option>
                            <option value="credit_note" @selected($filters['kind'] === 'credit_note')>Avoirs</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="state">État</label>
                        <select class="form-control" name="state" id="state">
                            <option value="">Tous</option>
                            <option value="issued" @selected($filters['state'] === 'issued')>Émis</option>
                            <option value="pending" @selected($filters['state'] === 'pending')>À émettre</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="from">Du</label>
                        <input type="date" class="form-control" name="from" id="from" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-md-2">
                        <label for="to">Au</label>
                        <input type="date" class="form-control" name="to" id="to" value="{{ $filters['to'] }}">
                    </div>
                    <div class="col-md-1" style="padding-top: 25px;">
                        <button type="submit" class="btn btn-primary btn-block" style="margin: 0;">OK</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body" style="padding-top: 0;">
                @if ($invoices->isEmpty())
                    <p class="booking-muted">Aucune pièce.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Numéro</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Expert</th>
                                    <th>Rendez-vous</th>
                                    <th class="text-right">HT</th>
                                    <th class="text-right">TTC</th>
                                    <th>Odoo</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $inv)
                                    @php
                                        $avoir = $inv->kind === 'credit_note';
                                        $b = $bookings->get($avoir ? $inv->parent_invoice_id : $inv->id);
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="label {{ $avoir ? 'label-danger' : 'label-primary' }}">{{ $avoir ? 'Avoir' : 'Facture' }}</span>
                                            <strong>{{ $inv->no ?: 'à émettre' }}</strong>
                                        </td>
                                        <td>{{ $fmt($inv->paid_at) }}</td>
                                        <td>{{ $inv->client_name }}<br><small class="booking-muted">{{ $inv->client_email }}</small></td>
                                        <td>{{ $b?->expert?->name ?? '—' }}</td>
                                        <td>
                                            @if ($b)
                                                <a href="{{ route('booking.appointments.show', $b->id) }}">#{{ $b->id }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $avoir ? '- ' : '' }}{{ $money($inv->amount_ht) }}</td>
                                        <td class="text-right"><strong>{{ $avoir ? '- ' : '' }}{{ $money($inv->amount) }}</strong></td>
                                        <td>
                                            @if ($inv->odoo_error)
                                                <span class="label label-warning" title="{{ $inv->odoo_error }}">À vérifier</span>
                                            @elseif ($inv->id_odoo)
                                                <span class="label label-success">#{{ $inv->id_odoo }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($inv->document)
                                                <a href="{{ $inv->document }}" target="_blank" class="btn btn-xs btn-default">PDF</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $invoices->links() }}
                @endif
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .booking-muted { color: #8a9199; font-size: 13px; }
    </style>
@stop
