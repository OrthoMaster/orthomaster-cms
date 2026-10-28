@extends('voyager::master')

@php
    use App\Models\ExpertAvailabilityRule;
    use App\Models\ExpertOffer;
    use App\Models\ExpertOfferMode;
    use App\Services\Booking\ExpertBookingSetupService as Setup;
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    // Après une erreur de validation, on revient sur l'onglet du formulaire envoyé.
    $activeTab = old('_tab', $tab);
    $oldForm   = old('_form');

    // Valeur propre à l'expert (vide = valeur système), HH:MM pour les heures.
    $own = function (string $key) use ($settings) {
        $value = $settings?->{$key};
        return in_array($key, ['morning_start', 'morning_end', 'afternoon_start', 'afternoon_end'], true) && $value
            ? substr($value, 0, 5)
            : $value;
    };
    $settingsValue = fn (string $key) => $oldForm === 'settings' ? old($key) : $own($key);

    $validity = function (ExpertAvailabilityRule $rule) {
        $from  = $rule->valid_from?->format('d/m/Y');
        $until = $rule->valid_until?->format('d/m/Y');
        if (!$from && !$until) return 'Toujours';
        if ($from && !$until) return 'Dès le ' . $from;
        if (!$from && $until) return "Jusqu'au " . $until;
        return 'Du ' . $from . ' au ' . $until;
    };

    $money = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' €';

    $activeLocations = $locations->whereNull('archived_at');
    $placesEnabled   = \App\Services\Google\GooglePlacesService::enabled();

    // « Mar. 12 nov. 2026 — matin · Cabinet A » : libellé d'une date précise.
    $dateLabel = function (string $key, ?string $place = null) {
        [$date, $period] = explode('|', $key);
        $text = Str::ucfirst(Carbon::parse($date)->locale('fr')->isoFormat('ddd D MMM YYYY'))
            . ' — ' . Str::lower(ExpertAvailabilityRule::PERIOD_LABELS[$period] ?? $period);
        return $place ? $text . ' · ' . $place : $text;
    };

    // « Fermer les réservations » reprend par défaut le délai minimum de l'expert (onglet Réglages).
    $defaultAhead = max(Setup::MIN_BOOK_AHEAD_DAYS, (int) $resolved['min_notice_days']);

    // Configuration d'une offre telle que la modale l'attend (JSON dans data-offer).
    $offerData = function (ExpertOffer $offer) use ($offerSlots, $offerDates, $defaultAhead) {
        $modes = [];
        foreach (array_keys(ExpertOfferMode::MODE_LABELS) as $mode) {
            $m = $offer->modes->firstWhere('mode', $mode);
            $modes[$mode] = [
                'enabled'         => (bool) $m?->active_flg,
                'price'           => $m ? (float) $m->price : '',
                'schedule_type'   => $m->schedule_type ?? ExpertOfferMode::SCHEDULE_ALL,
                'book_ahead_days' => $m?->book_ahead_days === null
                    ? $defaultAhead
                    : max(Setup::MIN_BOOK_AHEAD_DAYS, (int) $m->book_ahead_days),
                'slots'           => $offerSlots->get($offer->id . '|' . $mode, []),
                'dates'           => $offerDates->get($offer->id . '|' . $mode, []),
            ];
        }

        return [
            'id'           => $offer->id,
            'title'        => $offer->title,
            'description'  => $offer->description,
            'blocks_count' => $offer->blocks_count,
            'order_dsp'    => $offer->order_dsp,
            'active_flg'   => $offer->active_flg,
            'modes'        => $modes,
        ];
    };

    // [libellé, icône, compteur ou null] — même ordre que ExpertSetupController::TABS.
    $tabs = [
        'settings'     => ['Réglages', 'voyager-settings', null],
        'locations'    => ['Lieux', 'voyager-location', $activeLocations->count()],
        'availability' => ['Disponibilités', 'voyager-calendar', $rules->count()],
        'offers'       => ['Offres', 'voyager-wallet', $offers->count()],
    ];
@endphp

@section('page_title', 'Rendez-vous — ' . $expert->name)

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-calendar"></i> {{ $expert->name }}
        @if ($resolved['enabled_flg'])
            <span class="booking-pill booking-pill--on">Réservation activée</span>
        @else
            <span class="booking-pill">Réservation désactivée</span>
        @endif
    </h1>
    <a href="{{ route('booking.experts.index') }}" class="btn btn-default">
        <i class="voyager-angle-left"></i> Tous les experts
    </a>
@stop

@section('css')
    <style>
        /* --- Badges --- */
        .booking-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid #d5d9df;
            color: #6b7280;
            font-size: 12px;
            line-height: 18px;
            font-weight: normal;
            vertical-align: middle;
            white-space: nowrap;
        }

        .booking-pill--on {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
            color: #1e8e4f;
        }

        .booking-pill--danger {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.08);
            color: #b03a2e;
        }

        .booking-pill--online {
            border-color: #22a7f0;
            background: rgba(34, 167, 240, 0.1);
            color: #136b9c;
        }

        .booking-pill--onsite {
            border-color: #9b59b6;
            background: rgba(155, 89, 182, 0.1);
            color: #6c3483;
        }

        /* --- Onglets --- */
        .stats-tabs {
            display: inline-flex;
            gap: 4px;
            margin-bottom: 18px;
            padding: 4px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .stats-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 20px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            transition: background .15s, color .15s;
        }

        .stats-tab:hover {
            color: #1d3557;
            background: #f4f7fb;
        }

        .stats-tab.is-active,
        .stats-tab.is-active:hover {
            background: #1d3557;
            color: #fff;
            box-shadow: 0 2px 6px rgba(29, 53, 87, .28);
        }

        .stats-tab-count {
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(0, 0, 0, .07);
            font-size: 11px;
            font-weight: 600;
        }

        .stats-tab.is-active .stats-tab-count {
            background: rgba(255, 255, 255, .22);
        }

        /* --- Structure --- */
        .booking-section + .booking-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eef0f3;
        }

        .booking-section h3 {
            margin: 0 0 6px;
            font-size: 17px;
        }

        .booking-section > .booking-intro {
            margin: 0 0 18px;
            color: #8a8f98;
        }

        .booking-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .booking-empty {
            padding: 30px !important;
            text-align: center;
            color: #8a8f98;
        }

        .booking-inline-form {
            display: inline;
        }

        .booking-muted {
            color: #8a8f98;
        }

        /* --- Réglages : blocs horaires --- */
        .booking-blocks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 15px;
        }

        .booking-block {
            padding: 15px;
            border: 1px solid #e4e7eb;
            border-radius: 6px;
        }

        .booking-block h4 {
            margin: 0 0 10px;
            font-size: 15px;
        }

        .booking-block__times {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-block__duration {
            margin: 8px 0 0;
            font-weight: 600;
            color: #1e8e4f;
        }

        .booking-block.is-invalid {
            border-color: #e74c3c;
        }

        .booking-block.is-invalid .booking-block__duration {
            color: #b03a2e;
        }

        /* --- Lieux --- */
        .booking-location-row.is-archived td {
            color: #9ca3af;
        }

        .booking-access {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
            white-space: pre-line;
        }

        /* --- Disponibilités : grille de la semaine --- */
        .booking-week-wrap {
            overflow-x: auto;
        }

        .booking-week {
            width: 100%;
            min-width: 860px;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 4px;
        }

        .booking-week th {
            padding: 6px;
            font-weight: 600;
            text-align: center;
            color: #4b5563;
        }

        .booking-week tbody th {
            width: 120px;
            text-align: left;
        }

        .booking-week tbody th small {
            display: block;
            font-weight: normal;
            color: #8a8f98;
        }

        .booking-week td {
            height: 64px;
            padding: 6px;
            vertical-align: top;
            border-radius: 6px;
            background: #f6f7f9;
        }

        .booking-week td.is-open {
            background: rgba(46, 204, 113, 0.12);
        }

        .booking-rule {
            font-size: 12px;
            line-height: 16px;
            color: #1e5e3a;
        }

        .booking-rule + .booking-rule {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed rgba(30, 94, 58, .2);
        }

        .booking-rule__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 4px;
        }

        .booking-rule.is-expired {
            color: #9ca3af;
        }

        .booking-rule.is-expired .booking-rule__head span {
            text-decoration: line-through;
        }

        .booking-rule__remove {
            padding: 0 4px;
            border: 0;
            background: none;
            color: #9ca3af;
            font-size: 15px;
            line-height: 16px;
        }

        .booking-rule__remove:hover,
        .booking-rule__remove:focus {
            color: #b03a2e;
        }

        .booking-rule__location {
            width: 100%;
            margin-top: 4px;
            height: 24px;
            padding: 0 4px;
            font-size: 11px;
            border-radius: 4px;
        }

        /* --- Sélecteurs en pilule --- */
        .booking-choices {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .booking-choice input {
            position: absolute;
            opacity: 0;
        }

        .booking-choice span {
            display: inline-block;
            padding: 5px 14px;
            border: 1px solid #d5d9df;
            border-radius: 999px;
            cursor: pointer;
            user-select: none;
        }

        .booking-choice span small {
            opacity: .75;
        }

        .booking-choice input:checked + span {
            border-color: #22a7f0;
            background: #22a7f0;
            color: #fff;
        }

        .booking-choice input:focus-visible + span {
            outline: 2px solid #22a7f0;
            outline-offset: 2px;
        }

        .booking-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .booking-form-row .form-group {
            margin-bottom: 0;
        }

        .booking-scroll {
            max-height: 420px;
            overflow-y: auto;
        }

        /* --- Offres : modes dans le tableau --- */
        .booking-offer-mode + .booking-offer-mode {
            margin-top: 6px;
        }

        .booking-offer-mode.is-off {
            opacity: .5;
        }

        .booking-offer-mode .booking-muted {
            font-size: 12px;
        }

        /* --- Modale offre : un bloc par mode --- */
        .booking-mode {
            margin-top: 15px;
            border: 1px solid #e4e7eb;
            border-radius: 8px;
        }

        .booking-mode__head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            margin: 0;
            font-weight: 600;
            cursor: pointer;
        }

        .booking-mode__head input {
            margin: 0;
        }

        .booking-mode__body {
            padding: 4px 14px 14px;
            border-top: 1px solid #eef0f3;
        }

        .booking-mode.is-off .booking-mode__body {
            display: none;
        }

        .booking-mode--online.is-on {
            border-color: #22a7f0;
        }

        .booking-mode--onsite.is-on {
            border-color: #9b59b6;
        }

        .booking-mode__schedule {
            margin-top: 12px;
        }

        /* --- Modale offre : grille « Certaines demi-journées » --- */
        .booking-slot-week {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 6px;
        }

        .booking-slot-day {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: stretch;
        }

        .booking-slot-day__name {
            font-size: 12px;
            font-weight: 600;
            color: #4b5563;
            text-align: center;
        }

        .booking-slot-day .booking-choice span {
            display: block;
            padding: 5px 6px;
            text-align: center;
            font-size: 12px;
        }

        .booking-slot-day .booking-choice span small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .booking-slot-day__empty {
            padding: 5px 0;
            text-align: center;
            color: #d1d5db;
            font-size: 12px;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">

        {{-- Les erreurs des formulaires en modale s'affichent dans la modale. --}}
        @if ($errors->any() && !in_array($oldForm, ['offer', 'location'], true))
            <div class="alert alert-danger">
                <ul class="list-unstyled" style="margin: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ============ ONGLETS ============ --}}
        <div class="stats-tabs">
            @foreach ($tabs as $key => [$label, $icon, $count])
                <button type="button" class="stats-tab {{ $activeTab === $key ? 'is-active' : '' }}" data-tab="{{ $key }}">
                    <i class="{{ $icon }}"></i> {{ $label }}
                    @if ($count !== null)
                        <span class="stats-tab-count">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ---------- Réglages ---------- --}}
        <div class="stats-pane" data-pane="settings" @if ($activeTab !== 'settings') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <form method="POST" action="{{ route('booking.experts.settings.update', $expert->id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_tab" value="settings">
                        <input type="hidden" name="_form" value="settings">

                        <div class="booking-section">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="enabled_flg" value="1"
                                        @checked($oldForm === 'settings' ? old('enabled_flg') : $resolved['enabled_flg'])>
                                    <strong>Accepter les demandes de rendez-vous</strong>
                                </label>
                            </div>
                            <p class="booking-muted">
                                Désactiver n'annule pas les rendez-vous existants : seules les nouvelles demandes sont bloquées.
                            </p>
                        </div>

                        <div class="booking-section">
                            <h3>Délais de réservation</h3>
                            <p class="booking-intro">Laissez vide pour utiliser la valeur par défaut.</p>

                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="booking-timezone">Fuseau horaire</label>
                                    <select name="timezone" id="booking-timezone" class="form-control">
                                        @foreach (DateTimeZone::listIdentifiers() as $zone)
                                            <option value="{{ $zone }}"
                                                @selected(($oldForm === 'settings' ? old('timezone') : $resolved['timezone']) === $zone)>
                                                {{ $zone }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="min_notice_days">Délai minimum avant le rendez-vous (jours)</label>
                                    <input type="number" min="1" max="{{ Setup::MAX_NOTICE_DAYS }}"
                                           name="min_notice_days" id="min_notice_days" class="form-control"
                                           value="{{ $settingsValue('min_notice_days') }}"
                                           placeholder="{{ $defaults['min_notice_days'] }}">
                                    <span class="help-block">
                                        Par défaut : {{ $defaults['min_notice_days'] }} {{ $defaults['min_notice_days'] > 1 ? 'jours' : 'jour' }}
                                        — 1 jour = réservable dès le lendemain matin.
                                    </span>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="max_advance_months">Réservation au plus tard dans (mois)</label>
                                    <input type="number" min="1" max="{{ Setup::MAX_ADVANCE_MONTHS }}"
                                           name="max_advance_months" id="max_advance_months" class="form-control"
                                           value="{{ $settingsValue('max_advance_months') }}"
                                           placeholder="{{ $defaults['max_advance_months'] }}">
                                    <span class="help-block">Par défaut : {{ $defaults['max_advance_months'] }} mois</span>
                                </div>
                            </div>
                        </div>

                        <div class="booking-section">
                            <h3>Horaires des demi-journées</h3>
                            <p class="booking-intro">
                                Chaque bloc dure entre {{ Setup::BLOCK_MIN_MINUTES / 60 }} et {{ Setup::BLOCK_MAX_MINUTES / 60 }} heures.
                                Laissez vide pour reprendre l'horaire par défaut.
                            </p>

                            <div class="booking-blocks">
                                @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                    <div class="booking-block">
                                        <h4>{{ $periodLabel }}</h4>
                                        <div class="booking-block__times">
                                            <input type="time" class="form-control" data-role="start"
                                                   name="{{ $period }}_start"
                                                   value="{{ $settingsValue($period . '_start') }}"
                                                   data-default="{{ $defaults[$period . '_start'] }}"
                                                   aria-label="Début {{ $periodLabel }}">
                                            <span>à</span>
                                            <input type="time" class="form-control" data-role="end"
                                                   name="{{ $period }}_end"
                                                   value="{{ $settingsValue($period . '_end') }}"
                                                   data-default="{{ $defaults[$period . '_end'] }}"
                                                   aria-label="Fin {{ $periodLabel }}">
                                        </div>
                                        <p class="booking-block__duration" data-role="duration"></p>
                                        <span class="help-block">
                                            Par défaut : {{ $defaults[$period . '_start'] }} – {{ $defaults[$period . '_end'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="booking-section">
                            <button type="submit" class="btn btn-primary">Enregistrer les réglages</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ---------- Lieux ---------- --}}
        <div class="stats-pane" data-pane="locations" @if ($activeTab !== 'locations') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="booking-toolbar">
                        <p class="booking-muted" style="margin: 0;">
                            Lieux où l'expert reçoit en présentiel. Rattachez-les ensuite aux demi-journées dans « Disponibilités ».
                        </p>
                        <button type="button" class="btn btn-success" data-action="new-location">
                            <i class="voyager-plus"></i> Nouveau lieu
                        </button>
                    </div>

                    <table class="table table-striped database-tables">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Adresse</th>
                                <th class="text-center">Demi-journées</th>
                                <th>Statut</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($locations as $location)
                                @php $archived = $location->archived_at !== null; @endphp
                                <tr class="booking-location-row {{ $archived ? 'is-archived' : '' }}">
                                    <td>
                                        <strong>{{ $location->label }}</strong>
                                        @if (in_array($location->id, $lockedLocationIds, true))
                                            <div><span class="booking-pill" title="Des rendez-vous en attente ou à venir s'y tiennent">Adresse figée</span></div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $location->full_address }}
                                        @if ($location->country && $location->country !== 'FR')
                                            <span class="booking-muted">({{ $location->country }})</span>
                                        @endif
                                        @if ($location->access_details)
                                            <div class="booking-access">{{ $location->access_details }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $location->rules_count }}</td>
                                    <td>
                                        @if ($archived)
                                            <span class="booking-pill">Archivé</span>
                                        @else
                                            <span class="booking-pill booking-pill--on">Actif</span>
                                        @endif
                                    </td>
                                    <td class="text-right" style="white-space: nowrap;">
                                        @if ($archived)
                                            <form method="POST" class="booking-inline-form"
                                                  action="{{ route('booking.experts.locations.restore', [$expert->id, $location->id]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-default">Réactiver</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-primary" data-action="edit-location"
                                                    data-location="{{ json_encode($location->only([
                                                        'id', 'label', 'address_line', 'postal_code', 'city', 'country',
                                                        'lat', 'lng', 'google_place_id', 'access_details',
                                                    ]) + ['locked' => in_array($location->id, $lockedLocationIds, true)]) }}">
                                                Modifier
                                            </button>
                                            <form method="POST" class="booking-inline-form"
                                                  action="{{ route('booking.experts.locations.archive', [$expert->id, $location->id]) }}"
                                                  data-confirm="Archiver « {{ $location->label }} » ? Les demi-journées qui l'utilisent resteront ouvertes en ligne uniquement.">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning">Archiver</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="booking-empty">
                                        Aucun lieu. Sans lieu, l'expert ne peut proposer que des rendez-vous en ligne.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ---------- Disponibilités ---------- --}}
        <div class="stats-pane" data-pane="availability" @if ($activeTab !== 'availability') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">

                    <div class="booking-section">
                        <h3>Semaine type</h3>
                        <p class="booking-intro">
                            Demi-journées ouvertes chaque semaine. Une demi-journée avec un lieu sert en ligne et en présentiel ;
                            sans lieu, en ligne seulement. Les exceptions ci-dessous priment sur cette grille.
                        </p>

                        <div class="booking-week-wrap">
                            <table class="booking-week">
                                <thead>
                                    <tr>
                                        <th></th>
                                        @foreach (ExpertAvailabilityRule::WEEKDAY_LABELS as $dayLabel)
                                            <th>{{ $dayLabel }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                        <tr>
                                            <th>
                                                {{ $periodLabel }}
                                                <small>{{ $resolved[$period . '_start'] }} – {{ $resolved[$period . '_end'] }}</small>
                                            </th>
                                            @foreach (ExpertAvailabilityRule::WEEKDAY_LABELS as $weekday => $dayLabel)
                                                @php
                                                    $cellRules = $rules->where('weekday', $weekday)->where('period', $period);
                                                    $isOpen    = $cellRules->contains(fn ($r) => !$r->valid_until || $r->valid_until->toDateString() >= $today);
                                                @endphp
                                                <td class="{{ $isOpen ? 'is-open' : '' }}">
                                                    @foreach ($cellRules as $rule)
                                                        @php $expired = $rule->valid_until && $rule->valid_until->toDateString() < $today; @endphp
                                                        <div class="booking-rule {{ $expired ? 'is-expired' : '' }}">
                                                            <div class="booking-rule__head">
                                                                <span>{{ $validity($rule) }}</span>
                                                                <form method="POST" class="booking-inline-form"
                                                                      action="{{ route('booking.experts.rules.destroy', [$expert->id, $rule->id]) }}"
                                                                      data-confirm="Supprimer ce créneau ({{ $dayLabel }} {{ Str::lower($periodLabel) }}) ?">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="booking-rule__remove"
                                                                            title="Supprimer" aria-label="Supprimer">&times;</button>
                                                                </form>
                                                            </div>
                                                            @unless ($expired)
                                                                {{-- Changement de lieu enregistré dès la sélection. --}}
                                                                <form method="POST" class="booking-rule-location"
                                                                      action="{{ route('booking.experts.rules.location', [$expert->id, $rule->id]) }}">
                                                                    @csrf
                                                                    @method('PUT')
                                                                    <input type="hidden" name="_tab" value="availability">
                                                                    <select name="location_id" class="form-control booking-rule__location"
                                                                            aria-label="Lieu ({{ $dayLabel }} {{ Str::lower($periodLabel) }})">
                                                                        <option value="">En ligne seulement</option>
                                                                        @foreach ($activeLocations as $location)
                                                                            <option value="{{ $location->id }}" @selected($rule->location_id === $location->id)>
                                                                                {{ $location->label }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </form>
                                                            @else
                                                                @if ($rule->location)
                                                                    <div class="booking-muted">{{ $rule->location->label }}</div>
                                                                @endif
                                                            @endunless
                                                        </div>
                                                    @endforeach
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="booking-section">
                        <h3>Ajouter des créneaux récurrents</h3>
                        <p class="booking-intro">
                            Chaque jour coché est combiné avec chaque demi-journée cochée.
                        </p>

                        <form method="POST" action="{{ route('booking.experts.rules.store', $expert->id) }}">
                            @csrf
                            <input type="hidden" name="_tab" value="availability">
                            <input type="hidden" name="_form" value="rules">

                            @php
                                $oldWeekdays = $oldForm === 'rules' ? array_map('intval', (array) old('weekdays', [])) : [];
                                $oldPeriods  = $oldForm === 'rules' ? (array) old('periods', []) : [];
                            @endphp

                            <div class="form-group">
                                <label>Jours</label>
                                <div class="booking-choices">
                                    @foreach (ExpertAvailabilityRule::WEEKDAY_LABELS as $weekday => $dayLabel)
                                        <label class="booking-choice">
                                            <input type="checkbox" name="weekdays[]" value="{{ $weekday }}"
                                                @checked(in_array($weekday, $oldWeekdays, true))>
                                            <span>{{ $dayLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Demi-journées</label>
                                <div class="booking-choices">
                                    @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                        <label class="booking-choice">
                                            <input type="checkbox" name="periods[]" value="{{ $period }}"
                                                @checked(in_array($period, $oldPeriods, true))>
                                            <span>{{ $periodLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="booking-form-row">
                                <div class="form-group">
                                    <label for="rule-location">Lieu</label>
                                    <select name="location_id" id="rule-location" class="form-control">
                                        <option value="">En ligne seulement</option>
                                        @foreach ($activeLocations as $location)
                                            <option value="{{ $location->id }}"
                                                @selected($oldForm === 'rules' && (int) old('location_id') === $location->id)>
                                                {{ $location->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="valid_from">Valable du</label>
                                    <input type="date" name="valid_from" id="valid_from" class="form-control"
                                           value="{{ $oldForm === 'rules' ? old('valid_from') : '' }}">
                                </div>
                                <div class="form-group">
                                    <label for="valid_until">au</label>
                                    <input type="date" name="valid_until" id="valid_until" class="form-control"
                                           value="{{ $oldForm === 'rules' ? old('valid_until') : '' }}">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Ajouter les créneaux</button>
                                </div>
                            </div>
                            <span class="help-block">Dates vides : le créneau s'applique sans limite de durée.</span>
                        </form>
                    </div>

                    <div class="booking-section">
                        <h3>Exceptions</h3>
                        <p class="booking-intro">
                            Fermer une date (congés, congrès) ou ouvrir exceptionnellement une demi-journée hors semaine type.
                            Une ouverture exceptionnelle peut être choisie dans une offre en ligne « Dates précises »
                            (elle n'a pas de lieu) ; elle ne compte pas dans « Toutes » ou « Certaines demi-journées ».
                        </p>

                        <form method="POST" action="{{ route('booking.experts.exceptions.store', $expert->id) }}">
                            @csrf
                            <input type="hidden" name="_tab" value="availability">
                            <input type="hidden" name="_form" value="exceptions">

                            @php
                                $exOld    = $oldForm === 'exceptions';
                                $exType   = $exOld ? old('type', 'block') : 'block';
                                $exPeriod = $exOld ? old('period') : null;
                            @endphp

                            <div class="booking-form-row">
                                <div class="form-group">
                                    <label for="date_from">Du</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control" required
                                           min="{{ $today }}" value="{{ $exOld ? old('date_from') : '' }}">
                                </div>
                                <div class="form-group">
                                    <label for="date_to">au <span class="booking-muted">(facultatif)</span></label>
                                    <input type="date" name="date_to" id="date_to" class="form-control"
                                           min="{{ $today }}" value="{{ $exOld ? old('date_to') : '' }}">
                                </div>
                                <div class="form-group">
                                    <label for="period">Demi-journée</label>
                                    <select name="period" id="period" class="form-control">
                                        <option value="">Journée entière</option>
                                        @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                            <option value="{{ $period }}" @selected($exPeriod === $period)>{{ $periodLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Action</label>
                                    <div class="booking-choices">
                                        <label class="booking-choice">
                                            <input type="radio" name="type" value="block" @checked($exType === 'block')>
                                            <span>Fermer</span>
                                        </label>
                                        <label class="booking-choice">
                                            <input type="radio" name="type" value="add" @checked($exType === 'add')>
                                            <span>Ouvrir</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </div>
                        </form>

                        <div class="booking-scroll" style="margin-top: 20px;">
                            <table class="table table-striped database-tables">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Demi-journée</th>
                                        <th>Action</th>
                                        <th class="text-right"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($exceptions as $exception)
                                        <tr>
                                            <td>{{ Str::ucfirst($exception->date->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}</td>
                                            <td>{{ $exception->period_label }}</td>
                                            <td>
                                                @if ($exception->type === 'block')
                                                    <span class="booking-pill booking-pill--danger">Fermé</span>
                                                @else
                                                    <span class="booking-pill booking-pill--on">Ouvert</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <form method="POST" class="booking-inline-form"
                                                      action="{{ route('booking.experts.exceptions.destroy', [$expert->id, $exception->id]) }}"
                                                      data-confirm="Supprimer cette exception ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="booking-empty">Aucune exception à venir.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Offres ---------- --}}
        <div class="stats-pane" data-pane="offers" @if ($activeTab !== 'offers') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="booking-toolbar">
                        <p class="booking-muted" style="margin: 0;">
                            Les offres masquées restent attachées aux rendez-vous déjà pris.
                        </p>
                        <button type="button" class="btn btn-success" data-action="new-offer">
                            <i class="voyager-plus"></i> Nouvelle offre
                        </button>
                    </div>

                    <table class="table table-striped database-tables">
                        <thead>
                            <tr>
                                <th style="width: 24%;">Offre</th>
                                <th>Durée</th>
                                <th style="width: 40%;">Détails</th>
                                <th>Statut</th>
                                <th class="text-center">Ordre</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($offers as $offer)
                                <tr>
                                    <td>
                                        <strong>{{ $offer->title }}</strong>
                                        @if ($offer->description)
                                            <div class="booking-muted">{{ Str::limit($offer->description, 120) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $offer->duration_label }}</td>
                                    <td>
                                        @forelse ($offer->modes as $mode)
                                            @php
                                                $slotKeys = $offerSlots->get($offer->id . '|' . $mode->mode, []);
                                                $dateKeys = $offerDates->get($offer->id . '|' . $mode->mode, []);
                                            @endphp
                                            <div class="booking-offer-mode {{ $mode->active_flg ? '' : 'is-off' }}">
                                                <span class="booking-pill booking-pill--{{ $mode->mode }}">{{ $mode->mode_label }}</span>
                                                <strong>{{ $money($mode->price) }}</strong>
                                                @unless ($mode->active_flg)
                                                    <span class="booking-muted">(désactivé)</span>
                                                @endunless
                                                <div class="booking-muted">
                                                    {{ $mode->schedule_label }}
                                                    @if ($mode->schedule_type === ExpertOfferMode::SCHEDULE_SELECTED)
                                                        : {{ collect($slotKeys)->map(fn ($k) => ExpertAvailabilityRule::slotLabel((int) $k, explode('|', $k)[1]))->implode(', ') ?: '—' }}
                                                    @elseif ($mode->schedule_type === ExpertOfferMode::SCHEDULE_DATES)
                                                        : {{ count($dateKeys) }} à venir
                                                        @if ($dateKeys)
                                                            (prochaine : {{ Carbon::parse(substr($dateKeys[0], 0, 10))->format('d/m') }})
                                                        @endif
                                                        @if ($mode->book_ahead_days)
                                                            · fermeture {{ $mode->book_ahead_days }} j avant
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <span class="booking-muted">Aucun mode</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        @if ($offer->active_flg)
                                            <span class="booking-pill booking-pill--on">Active</span>
                                        @else
                                            <span class="booking-pill">Masquée</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $offer->order_dsp }}</td>
                                    <td class="text-right" style="white-space: nowrap;">
                                        <button type="button" class="btn btn-sm btn-primary" data-action="edit-offer"
                                                data-offer="{{ json_encode($offerData($offer)) }}">
                                            Modifier
                                        </button>
                                        <a href="{{ route('booking.experts.offers.images.index', [$expert->id, $offer->id]) }}"
                                           class="btn btn-sm btn-default">
                                            <i class="voyager-photos"></i> Images ({{ $offer->images_count ?? 0 }})
                                        </a>
                                        <form method="POST" class="booking-inline-form"
                                              action="{{ route('booking.experts.offers.destroy', [$expert->id, $offer->id]) }}"
                                              data-confirm="Supprimer l'offre « {{ $offer->title }} » ?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="booking-empty">
                                        Aucune offre. Les utilisateurs ne peuvent pas réserver tant qu'aucune offre active n'existe.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== MODALE LIEU ===================== --}}
    @php $locationOld = $oldForm === 'location'; @endphp
    <div class="modal fade" id="location-modal" tabindex="-1" role="dialog" aria-labelledby="location-modal-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" id="location-form"
                      action="{{ route('booking.experts.locations.store', $expert->id) }}"
                      data-store-url="{{ route('booking.experts.locations.store', $expert->id) }}"
                      data-update-url="{{ route('booking.experts.locations.update', [$expert->id, '__ID__']) }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="location-method">
                    <input type="hidden" name="_tab" value="locations">
                    <input type="hidden" name="_form" value="location">
                    <input type="hidden" name="_location_id" id="location-id" value="{{ $locationOld ? old('_location_id') : '' }}">
                    <input type="hidden" name="google_place_id" id="location-place" value="{{ $locationOld ? old('google_place_id') : '' }}">

                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="location-modal-title">Nouveau lieu</h4>
                    </div>

                    <div class="modal-body">
                        @if ($locationOld && $errors->any())
                            <div class="alert alert-danger">
                                <ul class="list-unstyled" style="margin: 0;">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="location-label">Nom court</label>
                            <input type="text" name="label" id="location-label" class="form-control" maxlength="60" required
                                   value="{{ $locationOld ? old('label') : '' }}" placeholder="Ex. Cabinet A, Domicile, Hôpital ABC">
                            <span class="help-block">Affiché partout où la place manque : choix des créneaux, agenda.</span>
                        </div>

                        <div class="alert alert-info" id="location-locked" style="display: none;">
                            Des rendez-vous en attente ou à venir se tiennent à cette adresse : seuls le nom et les
                            informations d'accès sont modifiables. Pour une nouvelle adresse, créez un nouveau lieu.
                        </div>

                        <div class="form-group" id="location-search-group">
                            <label for="location-search">Rechercher l'adresse</label>
                            @if ($placesEnabled)
                                <select id="location-search" class="form-control"
                                        data-placeholder="Tapez une adresse ou le nom d'un établissement…"></select>
                                <span class="help-block">
                                    Choisir un résultat remplit les champs ci-dessous ; ils restent modifiables.
                                </span>
                            @else
                                <p class="booking-muted" style="margin: 0;">
                                    Recherche Google indisponible (GOOGLE_PLACES_KEY absent) : saisissez l'adresse à la main.
                                </p>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="location-address">Adresse</label>
                            <input type="text" name="address_line" id="location-address" class="form-control" maxlength="255" required
                                   value="{{ $locationOld ? old('address_line') : '' }}" placeholder="12 rue des Lilas">
                        </div>

                        <div class="row">
                            <div class="form-group col-sm-4">
                                <label for="location-postal">Code postal</label>
                                <input type="text" name="postal_code" id="location-postal" class="form-control" maxlength="20"
                                       value="{{ $locationOld ? old('postal_code') : '' }}">
                            </div>
                            <div class="form-group col-sm-5">
                                <label for="location-city">Ville</label>
                                <input type="text" name="city" id="location-city" class="form-control" maxlength="120" required
                                       value="{{ $locationOld ? old('city') : '' }}">
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="location-country">Pays</label>
                                <input type="text" name="country" id="location-country" class="form-control" maxlength="2"
                                       value="{{ $locationOld ? old('country', 'FR') : 'FR' }}" placeholder="FR">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="location-access">Informations d'accès <span class="booking-muted">(facultatif)</span></label>
                            <textarea name="access_details" id="location-access" class="form-control" rows="3" maxlength="1000"
                                      placeholder="Étage, digicode, stationnement…">{{ $locationOld ? old('access_details') : '' }}</textarea>
                            <span class="help-block">Montrées au demandeur seulement une fois le rendez-vous accepté.</span>
                        </div>

                        <div class="row">
                            <div class="form-group col-sm-6">
                                <label for="location-lat">Latitude <span class="booking-muted">(facultatif)</span></label>
                                <input type="number" step="any" name="lat" id="location-lat" class="form-control"
                                       value="{{ $locationOld ? old('lat') : '' }}">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="location-lng">Longitude <span class="booking-muted">(facultatif)</span></label>
                                <input type="number" step="any" name="lng" id="location-lng" class="form-control"
                                       value="{{ $locationOld ? old('lng') : '' }}">
                            </div>
                        </div>
                        <span class="help-block" style="margin-top: 0;">
                            Remplies par la recherche d'adresse ; elles servent au bouton d'itinéraire
                            (sans elles, l'itinéraire part de l'adresse en texte).
                        </span>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="location-submit">Ajouter le lieu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== MODALE OFFRE ===================== --}}
    @php
        $offerOld = $oldForm === 'offer';
        // Valeur d'un champ de mode : saisie conservée après erreur, sinon valeur par défaut.
        $modeOld = fn (string $mode, string $key, $default = null) => $offerOld ? old("modes.$mode.$key", $default) : $default;
    @endphp
    <div class="modal fade" id="offer-modal" tabindex="-1" role="dialog" aria-labelledby="offer-modal-title">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" id="offer-form"
                      action="{{ route('booking.experts.offers.store', $expert->id) }}"
                      data-store-url="{{ route('booking.experts.offers.store', $expert->id) }}"
                      data-update-url="{{ route('booking.experts.offers.update', [$expert->id, '__ID__']) }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="offer-method">
                    <input type="hidden" name="_tab" value="offers">
                    <input type="hidden" name="_form" value="offer">
                    <input type="hidden" name="_offer_id" id="offer-id" value="{{ $offerOld ? old('_offer_id') : '' }}">

                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="offer-modal-title">Nouvelle offre</h4>
                    </div>

                    <div class="modal-body">
                        @if ($offerOld && $errors->any())
                            <div class="alert alert-danger">
                                <ul class="list-unstyled" style="margin: 0;">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="offer-title">Titre</label>
                            <input type="text" name="title" id="offer-title" class="form-control" maxlength="150" required
                                   value="{{ $offerOld ? old('title') : '' }}" placeholder="Ex. Chirurgie du ligament croisé">
                        </div>

                        <div class="form-group">
                            <label for="offer-description">Description</label>
                            <textarea name="description" id="offer-description" class="form-control" rows="3"
                                      maxlength="5000">{{ $offerOld ? old('description') : '' }}</textarea>
                        </div>

                        <div class="row">
                            <div class="form-group col-sm-5">
                                <label for="offer-blocks">Durée</label>
                                <select name="blocks_count" id="offer-blocks" class="form-control">
                                    @for ($blocks = 1; $blocks <= Setup::MAX_BLOCKS_PER_OFFER; $blocks++)
                                        <option value="{{ $blocks }}"
                                            @selected((int) ($offerOld ? old('blocks_count', 1) : 1) === $blocks)>
                                            {{ ExpertOffer::durationLabel($blocks) }}
                                            ({{ $blocks }} {{ $blocks > 1 ? 'demi-journées' : 'demi-journée' }})
                                        </option>
                                    @endfor
                                </select>
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="offer-order">Ordre d'affichage</label>
                                <input type="number" name="order_dsp" id="offer-order" class="form-control" min="0"
                                       value="{{ $offerOld ? old('order_dsp') : '' }}" placeholder="En dernier">
                            </div>
                            <div class="form-group col-sm-4">
                                <label>&nbsp;</label>
                                <div class="checkbox" style="margin-top: 0;">
                                    <label>
                                        <input type="checkbox" name="active_flg" value="1" id="offer-active"
                                            @checked($offerOld ? old('active_flg') : true)>
                                        Proposée aux utilisateurs
                                    </label>
                                </div>
                            </div>
                        </div>

                        @foreach ($modeLabels as $mode => $modeLabel)
                            @php
                                // Case décochée absente de old() : on ne retombe pas sur la valeur par défaut.
                                $enabled  = $offerOld ? (bool) old("modes.$mode.enabled") : $mode === ExpertOfferMode::ONLINE;
                                $schedule = $modeOld($mode, 'schedule_type', ExpertOfferMode::SCHEDULE_ALL);
                                $oldSlots = (array) $modeOld($mode, 'slots', []);
                                $oldDates = (array) $modeOld($mode, 'dates', []);
                                // Dates déjà choisies mais qui ne sont plus proposables : on les garde visibles.
                                $dateOptions = $gridDates[$mode] + array_fill_keys(array_diff($oldDates, array_keys($gridDates[$mode])), null);
                            @endphp
                            <div class="booking-mode booking-mode--{{ $mode }} {{ $enabled ? 'is-on' : 'is-off' }}" data-mode="{{ $mode }}">
                                <label class="booking-mode__head">
                                    <input type="checkbox" name="modes[{{ $mode }}][enabled]" value="1" data-role="enabled"
                                        @checked($enabled)>
                                    <span class="booking-pill booking-pill--{{ $mode }}">{{ $modeLabel }}</span>
                                    <span class="booking-muted" style="font-weight: normal;">
                                        @if ($mode === ExpertOfferMode::ONLINE)
                                            Visio Google Meet, sur toutes les demi-journées de la grille.
                                        @else
                                            Sur les demi-journées qui ont un lieu.
                                        @endif
                                    </span>
                                </label>

                                <div class="booking-mode__body">
                                    @if ($mode === ExpertOfferMode::ONSITE && !$grid[$mode])
                                        <div class="alert alert-warning" style="margin: 10px 0 0;">
                                            Aucune demi-journée de la grille n'a de lieu : ajoutez un lieu dans « Lieux »,
                                            puis rattachez-le aux demi-journées dans « Disponibilités ».
                                        </div>
                                    @endif

                                    <div class="row" style="margin-top: 10px;">
                                        <div class="form-group col-sm-4">
                                            <label for="offer-price-{{ $mode }}">Prix TTC de l'offre</label>
                                            <div class="input-group">
                                                <input type="text" inputmode="decimal" name="modes[{{ $mode }}][price]"
                                                       id="offer-price-{{ $mode }}" class="form-control" data-role="price"
                                                       value="{{ $modeOld($mode, 'price', '') }}">
                                                <span class="input-group-addon">€</span>
                                            </div>
                                            <span class="help-block">Toutes demi-journées comprises.</span>
                                        </div>
                                        <div class="form-group col-sm-8">
                                            <label>Calendrier</label>
                                            <div class="booking-choices">
                                                @foreach ($scheduleLabels as $type => $typeLabel)
                                                    <label class="booking-choice">
                                                        <input type="radio" name="modes[{{ $mode }}][schedule_type]" value="{{ $type }}"
                                                               data-role="schedule" @checked($schedule === $type)>
                                                        <span>{{ $typeLabel }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Certaines demi-journées --}}
                                    <div class="booking-mode__schedule" data-schedule="{{ ExpertOfferMode::SCHEDULE_SELECTED }}">
                                        <label>Demi-journées de la grille</label>
                                        @if ($grid[$mode])
                                            {{-- Une colonne par jour de la semaine, matin au-dessus de l'après-midi. --}}
                                            <div class="booking-slot-week">
                                                @foreach (ExpertAvailabilityRule::WEEKDAY_LABELS as $weekday => $dayLabel)
                                                    <div class="booking-slot-day">
                                                        <div class="booking-slot-day__name">{{ $dayLabel }}</div>
                                                        @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                                            @php $key = $weekday . '|' . $period; @endphp
                                                            @if (array_key_exists($key, $grid[$mode]))
                                                                <label class="booking-choice">
                                                                    <input type="checkbox" name="modes[{{ $mode }}][slots][]" value="{{ $key }}"
                                                                           data-role="slot" @checked(in_array($key, $oldSlots, true))>
                                                                    <span>
                                                                        {{ $periodLabel }}
                                                                        {{-- Le lieu n'a de sens qu'en présentiel. --}}
                                                                        @if ($mode === ExpertOfferMode::ONSITE && $grid[$mode][$key])
                                                                            <small>{{ $grid[$mode][$key] }}</small>
                                                                        @endif
                                                                    </span>
                                                                </label>
                                                            @else
                                                                <span class="booking-slot-day__empty">—</span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="booking-muted">Aucune demi-journée disponible pour ce mode.</p>
                                        @endif
                                    </div>

                                    {{-- Dates précises --}}
                                    <div class="booking-mode__schedule" data-schedule="{{ ExpertOfferMode::SCHEDULE_DATES }}">
                                        <div class="row">
                                            <div class="form-group col-sm-9">
                                                <label for="offer-dates-{{ $mode }}">Dates</label>
                                                <select multiple name="modes[{{ $mode }}][dates][]" id="offer-dates-{{ $mode }}"
                                                        class="form-control offer-dates" data-role="dates" data-placeholder="Choisir des demi-journées…">
                                                    @foreach ($dateOptions as $key => $place)
                                                        <option value="{{ $key }}" @selected(in_array($key, $oldDates, true))>
                                                            {{ $dateLabel($key, $mode === ExpertOfferMode::ONSITE ? $place : null) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="help-block">
                                                    Seules les demi-journées ouvertes de la grille sont proposées
                                                    ({{ $resolved['max_advance_months'] }} mois à venir, dates fermées exclues).
                                                </span>
                                            </div>
                                            <div class="form-group col-sm-3">
                                                <label for="offer-ahead-{{ $mode }}">Fermer les réservations</label>
                                                <div class="input-group">
                                                    <input type="number" min="{{ Setup::MIN_BOOK_AHEAD_DAYS }}" max="{{ Setup::MAX_BOOK_AHEAD_DAYS }}"
                                                           name="modes[{{ $mode }}][book_ahead_days]" id="offer-ahead-{{ $mode }}"
                                                           class="form-control" data-role="ahead"
                                                           value="{{ $modeOld($mode, 'book_ahead_days', $defaultAhead) }}">
                                                    <span class="input-group-addon">j avant</span>
                                                </div>
                                                <span class="help-block">Par défaut : délai minimum des Réglages ({{ $defaultAhead }} j).</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="offer-submit">Créer l'offre</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            var BLOCK_MIN = {{ Setup::BLOCK_MIN_MINUTES }};
            var BLOCK_MAX = {{ Setup::BLOCK_MAX_MINUTES }};

            /* --- Onglets --- */
            function showTab(name) {
                var $tab = $('.stats-tab[data-tab="' + name + '"]');

                if (!$tab.length) return;

                $('.stats-tab').removeClass('is-active');
                $tab.addClass('is-active');

                $('.stats-pane').each(function () {
                    $(this).toggle($(this).data('pane') === name);
                });
            }

            $('.stats-tab').on('click', function () {
                var name = $(this).data('tab');

                // En paramètre plutôt qu'en ancre : les redirections du serveur
                // (?tab=) et un rechargement rouvrent ainsi le même onglet.
                var url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                history.replaceState(null, '', url);

                showTab(name);
            });

            $('#booking-timezone').select2({ width: '100%' });

            /* --- Durée des blocs --- */
            function toMinutes(value) {
                var parts = (value || '').split(':');
                return parts.length < 2 ? null : parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            }

            function formatDuration(minutes) {
                var h = Math.floor(minutes / 60);
                var m = minutes % 60;
                return h + ' h' + (m ? ' ' + (m < 10 ? '0' : '') + m : '');
            }

            // Calcul sur la valeur effective (saisie ou défaut), comme le fait la validation serveur.
            function refreshBlock($block) {
                var $start = $block.find('[data-role="start"]');
                var $end = $block.find('[data-role="end"]');
                var start = toMinutes($start.val() || $start.data('default'));
                var end = toMinutes($end.val() || $end.data('default'));
                var $out = $block.find('[data-role="duration"]');

                if (start === null || end === null) {
                    $out.text('');
                    return;
                }

                var length = end - start;
                var invalid = length < BLOCK_MIN || length > BLOCK_MAX;
                $out.text(length > 0
                    ? 'Durée : ' + formatDuration(length) + (invalid ? ' — hors limites' : '')
                    : 'La fin doit être après le début');
                $block.toggleClass('is-invalid', invalid);
            }

            $('.booking-block').each(function () {
                var $block = $(this);
                refreshBlock($block);
                $block.on('input change', 'input', function () {
                    refreshBlock($block);
                });
            });

            /* --- Grille : lieu d'une demi-journée --- */
            $('.booking-rule__location').on('change', function () {
                $(this).closest('form').trigger('submit');
            });

            /* --- Modale lieu --- */
            var $locationModal = $('#location-modal');
            var $locationForm = $('#location-form');

            function setLocationMode(id) {
                $locationForm.attr('action', id
                    ? $locationForm.data('update-url').replace('__ID__', id)
                    : $locationForm.data('store-url'));
                $('#location-method').val(id ? 'PUT' : 'POST');
                $('#location-modal-title').text(id ? 'Modifier le lieu' : 'Nouveau lieu');
                $('#location-submit').text(id ? 'Enregistrer' : 'Ajouter le lieu');
                $('#location-id').val(id || '');
            }

            function fillLocation(location) {
                location = location || {};
                $('#location-label').val(location.label || '');
                $('#location-address').val(location.address_line || '');
                $('#location-postal').val(location.postal_code || '');
                $('#location-city').val(location.city || '');
                $('#location-country').val(location.country || 'FR');
                $('#location-access').val(location.access_details || '');
                $('#location-lat').val(location.lat === null || location.lat === undefined ? '' : location.lat);
                $('#location-lng').val(location.lng === null || location.lng === undefined ? '' : location.lng);
                $('#location-place').val(location.google_place_id || '');
                $locationModal.find('.alert-danger').remove();
                $('#location-search').val(null).trigger('change');
                placesSession = null;

                // Adresse figée : champs en lecture seule (encore envoyés, contrairement à disabled).
                var locked = !!location.locked;
                $('#location-locked').toggle(locked);
                $('#location-search-group').toggle(!locked);
                $('#location-address, #location-postal, #location-city, #location-country, #location-lat, #location-lng')
                    .prop('readonly', locked);
            }

            /* --- Modale lieu : recherche Google Places (via le serveur) --- */
            var placesSession = null;
            var $placeSearch = $('#location-search');

            // Une session par recherche : Google facture la frappe et le choix comme un seul lot.
            function placesToken() {
                if (!placesSession) {
                    placesSession = window.crypto && crypto.randomUUID
                        ? crypto.randomUUID()
                        : Date.now().toString(16) + '-' + Math.random().toString(16).slice(2);
                }
                return placesSession;
            }

            if ($placeSearch.length) {
                $placeSearch.select2({
                    width: '100%',
                    dropdownParent: $locationModal,
                    placeholder: $placeSearch.data('placeholder'),
                    allowClear: true,
                    minimumInputLength: {{ \App\Services\Google\GooglePlacesService::MIN_INPUT_LENGTH }},
                    language: {
                        inputTooShort: function () { return 'Tapez au moins {{ \App\Services\Google\GooglePlacesService::MIN_INPUT_LENGTH }} caractères'; },
                        noResults: function () { return 'Aucune adresse trouvée'; },
                        searching: function () { return 'Recherche…'; },
                        errorLoading: function () { return 'Recherche indisponible : saisissez l\'adresse à la main'; },
                    },
                    ajax: {
                        url: '{{ route('booking.places.autocomplete') }}',
                        dataType: 'json',
                        delay: 300,
                        data: function (params) {
                            return { q: params.term || '', session: placesToken() };
                        },
                        processResults: function (data) {
                            return { results: data.results || [] };
                        },
                    },
                    templateResult: function (item) {
                        if (!item.id) {
                            return item.text;
                        }
                        return $('<div>')
                            .append($('<strong>').text(item.main))
                            .append($('<div class="booking-muted" style="font-size: 12px;">').text(item.secondary));
                    },
                });

                $placeSearch.on('select2:select', function (e) {
                    var url = '{{ route('booking.places.details', '__ID__') }}'.replace('__ID__', encodeURIComponent(e.params.data.id));

                    $.getJSON(url, { session: placesToken() })
                        .done(function (res) {
                            var p = res.data || {};
                            $('#location-address').val(p.address_line || '');
                            $('#location-postal').val(p.postal_code || '');
                            $('#location-city').val(p.city || '');
                            $('#location-country').val(p.country || 'FR');
                            $('#location-lat').val(p.lat === null || p.lat === undefined ? '' : p.lat);
                            $('#location-lng').val(p.lng === null || p.lng === undefined ? '' : p.lng);
                            $('#location-place').val(p.google_place_id || '');

                            // Un cabinet ou un hôpital : son nom fait un bon libellé, si aucun n'est saisi.
                            if (!$('#location-label').val() && p.is_establishment && p.name) {
                                $('#location-label').val(p.name.substring(0, 60));
                            }
                        })
                        .fail(function (xhr) {
                            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Adresse introuvable.');
                        })
                        .always(function () {
                            // Le choix clôt la session : la prochaine frappe en ouvre une nouvelle.
                            placesSession = null;
                        });
                });
            }

            $('[data-action="new-location"]').on('click', function () {
                setLocationMode(null);
                fillLocation(null);
                $locationModal.modal('show');
            });

            $('[data-action="edit-location"]').on('click', function () {
                var location = $(this).data('location');
                setLocationMode(location.id);
                fillLocation(location);
                $locationModal.modal('show');
            });

            $locationModal.on('shown.bs.modal', function () {
                $('#location-label').trigger('focus');
            });

            /* --- Modale offre --- */
            var $modal = $('#offer-modal');
            var $form = $('#offer-form');
            var MODES = @json(array_keys($modeLabels));
            var MIN_AHEAD = {{ Setup::MIN_BOOK_AHEAD_DAYS }};
            var DEFAULT_AHEAD = {{ $defaultAhead }};

            $('.offer-dates').each(function () {
                $(this).select2({
                    width: '100%',
                    dropdownParent: $modal,
                    placeholder: $(this).data('placeholder'),
                    closeOnSelect: false,
                });
            });

            // Bloc d'un mode : corps visible si le mode est coché, sous-partie selon le calendrier.
            function refreshMode($mode) {
                var enabled = $mode.find('[data-role="enabled"]').is(':checked');
                var schedule = $mode.find('[data-role="schedule"]:checked').val();

                $mode.toggleClass('is-on', enabled).toggleClass('is-off', !enabled);
                $mode.find('.booking-mode__schedule').each(function () {
                    $(this).toggle($(this).data('schedule') === schedule);
                });
            }

            $('.booking-mode').each(function () {
                var $mode = $(this);
                refreshMode($mode);
                $mode.on('change', '[data-role="enabled"], [data-role="schedule"]', function () {
                    refreshMode($mode);
                });
            });

            function setOfferMode(id) {
                if (id) {
                    $form.attr('action', $form.data('update-url').replace('__ID__', id));
                    $('#offer-method').val('PUT');
                    $('#offer-modal-title').text("Modifier l'offre");
                    $('#offer-submit').text('Enregistrer');
                } else {
                    $form.attr('action', $form.data('store-url'));
                    $('#offer-method').val('POST');
                    $('#offer-modal-title').text('Nouvelle offre');
                    $('#offer-submit').text("Créer l'offre");
                }
                $('#offer-id').val(id || '');
            }

            function fillMode(mode, config) {
                var $mode = $('.booking-mode[data-mode="' + mode + '"]');
                config = config || {
                    enabled: mode === 'online',
                    price: '',
                    schedule_type: 'weekly_all',
                    book_ahead_days: DEFAULT_AHEAD,
                    slots: [],
                    dates: []
                };

                $mode.find('[data-role="enabled"]').prop('checked', !!config.enabled);
                $mode.find('[data-role="price"]').val(config.price === '' ? '' : String(config.price).replace('.', ','));
                $mode.find('[data-role="schedule"]').each(function () {
                    $(this).prop('checked', $(this).val() === config.schedule_type);
                });
                $mode.find('[data-role="ahead"]').val(Math.max(MIN_AHEAD, parseInt(config.book_ahead_days, 10) || DEFAULT_AHEAD));
                $mode.find('[data-role="slot"]').each(function () {
                    $(this).prop('checked', config.slots.indexOf($(this).val()) !== -1);
                });

                // Une date déjà choisie qui n'est plus proposable reste affichée, pour pouvoir la retirer.
                var $dates = $mode.find('[data-role="dates"]');
                config.dates.forEach(function (key) {
                    if (!$dates.find('option[value="' + key + '"]').length) {
                        $dates.append(new Option(key.replace('|', ' — '), key));
                    }
                });
                $dates.val(config.dates).trigger('change');

                refreshMode($mode);
            }

            function fillOffer(offer) {
                $('#offer-title').val(offer ? offer.title : '');
                $('#offer-description').val(offer ? (offer.description || '') : '');
                $('#offer-blocks').val(offer ? offer.blocks_count : 1);
                $('#offer-order').val(offer ? offer.order_dsp : '');
                $('#offer-active').prop('checked', offer ? !!offer.active_flg : true);
                MODES.forEach(function (mode) {
                    fillMode(mode, offer ? offer.modes[mode] : null);
                });
                $modal.find('.alert-danger').remove();
            }

            $('[data-action="new-offer"]').on('click', function () {
                setOfferMode(null);
                fillOffer(null);
                $modal.modal('show');
            });

            $('[data-action="edit-offer"]').on('click', function () {
                var offer = $(this).data('offer');
                setOfferMode(offer.id);
                fillOffer(offer);
                $modal.modal('show');
            });

            $modal.on('shown.bs.modal', function () {
                $('#offer-title').trigger('focus');
            });

            // Retour après erreur : on rouvre la modale avec la saisie conservée.
            @if ($offerOld)
                setOfferMode($('#offer-id').val() || null);
                $modal.modal('show');
            @endif

            @if ($locationOld)
                setLocationMode($('#location-id').val() || null);
                $locationModal.modal('show');
            @endif

            /* --- Confirmations --- */
            $(document).on('submit', 'form[data-confirm]', function (e) {
                if (!window.confirm($(this).data('confirm'))) {
                    e.preventDefault();
                }
            });
        });
    </script>
@stop