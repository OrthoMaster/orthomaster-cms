@extends('voyager::master')

@section('page_title', 'Rendez-vous experts')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-calendar"></i> Rendez-vous experts
    </h1>
    <a href="{{ route('booking.config.index') }}" class="btn btn-default">
        <i class="voyager-settings"></i> Configuration générale
    </a>
@stop

@section('css')
    <style>
        /* --- Filtres --- */
        .booking-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .booking-filters .form-control {
            width: auto;
            min-width: 220px;
        }

        /* --- Tableau --- */
        .booking-row {
            cursor: pointer;
        }

        .booking-empty {
            padding: 30px !important;
            text-align: center;
            color: #8a8f98;
        }

        /* --- Badges --- */
        .booking-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid #d5d9df;
            color: #6b7280;
            font-size: 12px;
            line-height: 18px;
            white-space: nowrap;
        }

        .booking-pill--on {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
            color: #1e8e4f;
        }

        .booking-pill--warn {
            border-color: #f0ad4e;
            background: rgba(240, 173, 78, 0.12);
            color: #a86500;
            margin-left: 4px;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">
        <div class="panel panel-bordered">
            <div class="panel-body">

                <form method="GET" class="booking-filters">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Rechercher un expert">
                    <select name="status" class="form-control">
                        <option value="">Tous les statuts</option>
                        <option value="enabled" @selected(request('status') === 'enabled')>Réservation activée</option>
                        <option value="disabled" @selected(request('status') === 'disabled')>Réservation désactivée</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    @if (request()->filled('q') || request()->filled('status'))
                        <a href="{{ route('booking.experts.index') }}" class="btn btn-link">Réinitialiser</a>
                    @endif
                </form>

                <table class="table table-striped database-tables">
                    <thead>
                        <tr>
                            <th>Expert</th>
                            <th>Réservation</th>
                            <th class="text-center">Offres actives</th>
                            <th class="text-center">Créneaux récurrents</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($experts as $expert)
                            <tr class="booking-row" data-href="{{ route('booking.experts.show', $expert->id) }}">
                                <td>
                                    <a href="{{ route('booking.experts.show', $expert->id) }}">{{ $expert->name }}</a>
                                </td>
                                <td>
                                    @if ($expert->enabled_flg)
                                        <span class="booking-pill booking-pill--on">Activée</span>
                                        {{-- Activé sans offre ni créneau : l'expert est invisible côté utilisateurs. --}}
                                        @if (!$expert->offers_count || !$expert->rules_count)
                                            <span class="booking-pill booking-pill--warn"
                                                  title="Il faut au moins une offre active et un créneau pour recevoir des demandes.">
                                                Incomplet
                                            </span>
                                        @endif
                                    @else
                                        <span class="booking-pill">Désactivée</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $expert->offers_count }}</td>
                                <td class="text-center">{{ $expert->rules_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="booking-empty">Aucun expert ne correspond à ces filtres.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            // Toute la ligne est cliquable ; le lien du nom reste utilisable (clic molette, nouvel onglet).
            $('.booking-row').on('click', function (e) {
                if ($(e.target).closest('a').length) {
                    return;
                }
                window.location = $(this).data('href');
            });
        });
    </script>
@stop