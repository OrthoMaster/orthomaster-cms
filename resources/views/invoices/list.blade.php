@extends('voyager::master')

@section('page_title', ' Factures')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-receipt"></i> Factures
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ SECTION 1 : OPCO-EP — facture d'une convention (admin) ============ --}}
                @if ($canManageOpco)
                <div class="panel panel-bordered opco-section">
                    <div class="panel-body">
                        <div class="section-head">
                            <h3 class="section-title-main">Factures OPCO-EP</h3>
                            <span class="section-sub">
                                @if ($opcoPending->isNotEmpty())
                                    {{ $opcoPending->count() }} convention(s) signée(s) sans facture
                                @else
                                    Toutes les conventions signées ont leur facture.
                                @endif
                            </span>
                        </div>

                        @if ($opcoPending->isNotEmpty())
                            <form class="opco-form" id="opco-form" action="{{ route('invoices.opco.generate') }}" method="POST"
                                onsubmit="if (!this.contract_id.value) { alert('Choisissez une convention dans la liste.'); return false; } return confirm('Créer dans Odoo la facture de la convention ' + this.dataset.no + ' ?\n\nVérifiez d\'abord qu\'aucune facture n\'a déjà été faite à la main pour cette convention : une facture validée ne s\'annule que par un avoir.');">
                                @csrf
                                {{-- Recherche : 3 caractères au moins, 3 propositions au plus --}}
                                <div class="opco-search">
                                    <input type="text" class="form-control" id="opco-contract-search" autocomplete="off"
                                        placeholder="Rechercher une convention (n°, entreprise) — 3 caractères minimum">
                                    <input type="hidden" name="contract_id" id="opco-contract-id">
                                    <ul class="opco-suggestions" id="opco-suggestions"></ul>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="voyager-receipt"></i> Créer la facture
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ============ SECTION 2 : liste des factures ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <div class="section-head">
                            <h3 class="section-title-main">Toutes les factures</h3>
                        </div>

                        {{-- Filtres --}}
                        <form class="row filters" action="{{ route('invoices.list') }}" method="GET">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="q" id="q"
                                    value="{{ $search }}"
                                    placeholder="Numéro, nom ou adresse e-mail du client">
                            </div>

                            <div class="col-md-3">
                                <select class="form-control" name="status" id="status">
                                    <option value="">Tous les statuts</option>
                                    @foreach ($statuses as $key => $meta)
                                        <option value="{{ $key }}" @selected($status === $key)>
                                            {{ $meta['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <select class="form-control" name="origin" id="origin">
                                    <option value="">Tous les organismes</option>
                                    @foreach ($origins as $key => $label)
                                        <option value="{{ $key }}" @selected($origin === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                                @if ($search !== '' || $status || $origin)
                                    <a href="{{ route('invoices.list') }}" class="btn btn-default">
                                        Réinitialiser
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Liste --}}
                @if ($invoices->isEmpty())
                    <div class="alert alert-warning">
                        <strong>Aucune facture.</strong>
                        <p>Modifiez vos critères de recherche.</p>
                    </div>
                @else
                    <div class="panel panel-bordered">
                        <div class="panel-body invoice-list">
                            <div class="invoice-meta">
                                <span>
                                    <b>{{ $invoices->total() }}</b>
                                    {{ $invoices->total() > 1 ? 'factures' : 'facture' }}
                                </span>
                                <span>Page <b>{{ $invoices->currentPage() }}/{{ $invoices->lastPage() }}</b></span>
                            </div>

                            @foreach ($invoices as $invoice)
                                @php
                                    $meta   = $statuses[$invoice->status] ?? ['label' => $invoice->status, 'class' => ''];
                                    $isPaid = $invoice->status === 'paid';
                                    $isCard = $invoice->payment_method && $invoice->payment_method !== 'virement';
                                    $isOpco = $invoice->contract_organization === $opcoOrganization;
                                    $originLabel = $invoice->contract_organization
                                        ?: ($invoice->booking_id ? 'Rendez-vous' : ($invoice->is_direct ? 'Direct' : null));
                                @endphp

                                <div class="invoice-block" data-invoice="{{ $invoice->id }}">
                                    {{-- La ligne entière déploie le détail --}}
                                    <div class="invoice-row">
                                        <button type="button" class="invoice-toggle" aria-expanded="false">
                                            <i class="voyager-angle-right"></i>
                                        </button>

                                        <div class="invoice-no">
                                            {{ $invoice->no ?: '—' }}
                                            @if ($originLabel)
                                                <span class="invoice-org invoice-org-{{ \Illuminate\Support\Str::slug($originLabel) }}">{{ $originLabel }}</span>
                                            @endif
                                        </div>

                                        <div class="invoice-client">
                                            <div class="invoice-client-name">{{ $invoice->client_name ?: '—' }}</div>
                                            <div class="invoice-client-mail">{{ $invoice->client_email ?: '—' }}</div>
                                        </div>

                                        <div class="invoice-status">
                                            <span class="invoice-badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                        </div>

                                        <div class="invoice-amount">
                                            {{ number_format($invoice->amount, 2, ',', ' ') }}
                                            <small>{{ strtoupper($invoice->currency) }}</small>
                                        </div>

                                        <div class="invoice-date">
                                            {{ $invoice->created_at ? \Illuminate\Support\Carbon::parse($invoice->created_at)->format('d/m/Y') : '—' }}
                                        </div>
                                    </div>

                                    {{-- Détail, masqué tant que la ligne n'est pas déployée --}}
                                    <div class="invoice-detail" style="display:none">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="detail-head">Client</div>
                                                <dl class="detail-list">
                                                    <dt>Nom</dt>
                                                    <dd>{{ $invoice->client_name ?: '—' }}</dd>

                                                    <dt>E-mail</dt>
                                                    <dd>{{ $invoice->client_email ?: '—' }}</dd>

                                                    <dt>Société</dt>
                                                    <dd>{{ $invoice->client_company ?: '—' }}</dd>

                                                    <dt>Adresse</dt>
                                                    <dd>{{ $invoice->client_address ?: '—' }}</dd>

                                                    <dt>Compte</dt>
                                                    <dd>
                                                        @if ($invoice->user_id)
                                                            #{{ $invoice->user_id }}
                                                            <span class="detail-muted">{{ $invoice->user_email }}</span>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>
                                                </dl>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="detail-head detail-head-spaced">Documents</div>
                                                <dl class="detail-list">
                                                    @if ($invoice->contract_no)
                                                        <dt>Convention</dt>
                                                        <dd>{{ $invoice->contract_no }}</dd>
                                                    @endif

                                                    <dt>Facture</dt>
                                                    <dd>
                                                        @if ($invoice->document)
                                                            <a href="{{ $invoice->document }}" target="_blank">Ouvrir</a>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>

                                                    <dt>Attestation</dt>
                                                    <dd>
                                                        @if ($invoice->certification)
                                                            <a href="{{ $invoice->certification }}" target="_blank">Ouvrir</a>
                                                            @if ($isOpco && $invoice->sent_at)
                                                                {{-- Envoi des certificats aux apprenants : fait / prévu --}}
                                                                <span class="detail-muted">
                                                                    · {{ $invoice->sent_cnt > 0 ? 'envoyée le' : 'envoi prévu le' }}
                                                                    {{ \Illuminate\Support\Carbon::parse($invoice->sent_at)->format($invoice->sent_cnt > 0 ? 'd/m/Y à H:i' : 'd/m/Y') }}
                                                                </span>
                                                            @elseif ($isOpco)
                                                                <span class="detail-muted">· pas encore envoyée</span>
                                                            @endif
                                                        @elseif ($isOpco)
                                                            <span class="detail-muted">pas encore créée</span>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>
                                                </dl>

                                                @if ($isOpco && $canManageOpco)
                                                    @if (!$invoice->certification)
                                                        {{-- 1) Attestation = PDF de tous les certificats, sur S3. N'envoie rien. --}}
                                                        <form action="{{ route('invoices.opco.attestation', $invoice->id) }}" method="POST"
                                                            onsubmit="return confirm('Créer l\'attestation de la facture {{ $invoice->no }} ? Aucun e-mail ne sera envoyé.');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="voyager-certificate"></i> Créer l'attestation
                                                            </button>
                                                        </form>
                                                    @else
                                                        {{-- 2) Après vérification : chaque apprenant reçoit SA page de l'attestation --}}
                                                        @if ($invoice->sent_cnt > 0)
                                                            {{-- Renvoi : à tous ou à un apprenant --}}
                                                            <form class="opco-resend" action="{{ route('invoices.opco.certificates', $invoice->id) }}" method="POST"
                                                                onsubmit="var s = this.learner_id; return confirm('Renvoyer le certificat de réalisation à ' + (s.value ? s.options[s.selectedIndex].text : 'tous les apprenants') + ' ?');">
                                                                @csrf
                                                                <select name="learner_id" class="form-control input-sm">
                                                                    <option value="">Tous les apprenants</option>
                                                                    @foreach ($opcoLearners[$invoice->id] ?? [] as $l)
                                                                        <option value="{{ $l['id'] }}">{{ $l['name'] }}{{ $l['email'] ? '' : ' (sans e-mail)' }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <button type="submit" class="btn btn-sm btn-default">
                                                                    <i class="voyager-paper-plane"></i> Renvoyer
                                                                </button>
                                                            </form>
                                                        @else
                                                            <form action="{{ route('invoices.opco.certificates', $invoice->id) }}" method="POST"
                                                                onsubmit="return confirm('Envoyer à chaque apprenant son certificat de réalisation ?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-primary">
                                                                    <i class="voyager-paper-plane"></i> Envoyer les certificats aux apprenants
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                @endif
                                            </div>

                                            <div class="col-md-4">
                                                <div class="detail-head">Règlement</div>
                                                <dl class="detail-list">
                                                    <dt>Moyen</dt>
                                                    <dd>
                                                        @if ($invoice->payment_method === 'virement')
                                                            Virement
                                                        @elseif ($invoice->payment_method)
                                                            Carte
                                                            <span class="detail-muted">{{ $invoice->payment_method }}</span>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>

                                                    {{-- La référence Stripe n'a pas de sens pour un virement --}}
                                                    @if ($isCard)
                                                        <dt>Référence Stripe</dt>
                                                        <dd class="detail-mono">
                                                            {{ $invoice->stripe_payment_intent_id ?: '—' }}
                                                        </dd>
                                                    @endif

                                                    <dt>Réglée le</dt>
                                                    <dd>
                                                        {{ $invoice->paid_at
                                                            ? \Illuminate\Support\Carbon::parse($invoice->paid_at)->format('d/m/Y H:i')
                                                            : '—' }}
                                                    </dd>

                                                    @if ($invoice->failed_reason)
                                                        <dt>Échec</dt>
                                                        <dd class="detail-error">{{ $invoice->failed_reason }}</dd>
                                                    @endif

                                                    <dt>Créée le</dt>
                                                    <dd>
                                                        {{ $invoice->created_at
                                                            ? \Illuminate\Support\Carbon::parse($invoice->created_at)->format('d/m/Y H:i')
                                                            : '—' }}
                                                    </dd>
                                                </dl>

                                                {{-- OPCO-EP : pas de règlement dans le CMS --}}
                                                @unless ($isPaid || $isOpco)
                                                    {{-- Un virement se constate après coup : l'opération se fait ici --}}
                                                    <button type="button" class="btn btn-sm btn-success btn-virement"
                                                        data-url="{{ route('invoices.virement', $invoice->id) }}"
                                                        data-no="{{ $invoice->no ?: '#' . $invoice->id }}">
                                                        <i class="voyager-wallet"></i>
                                                        Marquer comme réglée par virement
                                                    </button>
                                                @endunless
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="invoice-pagination">
                                {{ $invoices->links() }}
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ============ RÈGLEMENT PAR VIREMENT ============ --}}
    <div class="modal fade" id="virementModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="" method="POST" id="virement-form">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title">Règlement par virement</h4>
                    </div>
                    <div class="modal-body">
                        <p>
                            Facture <b id="virement-no"></b> sera marquée comme réglée.
                        </p>

                        <div class="form-group">
                            <label for="paid_at">Date du règlement</label>
                            <input type="date" class="form-control" id="paid_at" name="paid_at"
                                max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required>
                            <p class="help-block">
                                Indiquez la date figurant sur le relevé bancaire.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="voyager-check"></i> Confirmer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    <style>
        .invoice-list {
            padding: 0;
        }

        .invoice-meta {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #eceff3;
            font-size: 12px;
            color: #8a9199;
        }

        .invoice-block + .invoice-block {
            border-top: 1px solid #eceff3;
        }

        /* --- Ligne --- */
        .invoice-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 11px 16px;
            cursor: pointer;
        }

        .invoice-row:hover {
            background: #fafbfc;
        }

        .invoice-block.is-open .invoice-row {
            background: #f7fbfe;
        }

        .invoice-toggle {
            width: 26px;
            height: 26px;
            padding: 0;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #8a9199;
            transition: transform .15s;
        }

        .invoice-block.is-open .invoice-toggle {
            transform: rotate(90deg);
        }

        .invoice-no {
            flex: 0 0 auto;
            width: 130px;
            font-size: 13px;
            font-weight: 600;
            color: #37474f;
        }

        .invoice-client {
            flex: 1;
            min-width: 0;
        }

        .invoice-client-name {
            font-size: 14px;
            color: #37474f;
        }

        .invoice-client-mail {
            font-size: 12px;
            color: #8a9199;
        }

        .invoice-status {
            flex: 0 0 auto;
            width: 100px;
            text-align: center;
        }

        .invoice-amount {
            flex: 0 0 auto;
            width: 110px;
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
        }

        .invoice-amount small {
            font-weight: 400;
            color: #8a9199;
        }

        .invoice-date {
            flex: 0 0 auto;
            width: 90px;
            text-align: right;
            font-size: 12px;
            color: #8a9199;
        }

        /* --- Badges --- */
        .invoice-badge {
            display: inline-block;
            padding: 2px 11px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 400;
        }

        .invoice-badge.is-paid {
            background: #e8f7ee;
            color: #27ae60;
        }

        .invoice-badge.is-processing {
            background: #eaf4fd;
            color: #2980b9;
        }

        .invoice-badge.is-pending {
            background: #fdf3e3;
            color: #d68910;
        }

        /* --- Détail --- */
        .invoice-detail {
            padding: 4px 16px 18px 42px;
            background: #fbfcfd;
        }

        .detail-head {
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #a4abb3;
        }

        .detail-head-spaced {
            margin-top: 18px;
        }

        .detail-list {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 5px 12px;
            margin: 0;
        }

        .detail-list dt {
            font-size: 12px;
            font-weight: 400;
            color: #8a9199;
        }

        .detail-list dd {
            margin: 0;
            font-size: 13px;
            color: #37474f;
            word-break: break-word;
        }

        .detail-muted {
            color: #a4abb3;
            font-size: 12px;
        }

        .detail-mono {
            font-family: Menlo, Consolas, monospace;
            font-size: 12px;
        }

        .detail-error {
            color: #c0392b;
        }

        .invoice-org-opco-ep {
            background: #fdf3e8;
            color: #b45f06;
        }

        .invoice-org-fifpl {
            background: #e8f1fd;
            color: #1f5fa8;
        }

        .invoice-org-rendez-vous {
            background: #eaf7ee;
            color: #2e7d46;
        }

        .section-head {
            display: flex;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 14px;
        }

        .section-title-main {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #2b3a4a;
        }

        .section-sub {
            font-size: 13px;
            color: #76838f;
        }

        .opco-form {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        /* La liste de propositions doit passer par-dessus la carte suivante */
        .panel.opco-section,
        .panel.opco-section .panel-body {
            overflow: visible;
        }

        .panel.opco-section {
            position: relative;
            z-index: 30;
        }

        .opco-search {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        .opco-suggestions {
            display: none;
            position: absolute;
            z-index: 1000;
            top: 100%;
            left: 0;
            right: 0;
            margin: 2px 0 0;
            padding: 4px 0;
            list-style: none;
            background: #fff;
            border: 1px solid #dfe3e8;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .opco-suggestions li {
            padding: 8px 12px;
            cursor: pointer;
            line-height: 1.35;
        }

        .opco-suggestions li:hover,
        .opco-suggestions li.active {
            background: #f3f6f9;
        }

        .opco-suggestions .sug-no {
            font-weight: 600;
            color: #2b3a4a;
        }

        .opco-suggestions .sug-meta {
            font-size: 12px;
            color: #76838f;
        }

        .opco-suggestions .sug-empty {
            cursor: default;
            color: #76838f;
        }

        .opco-resend {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .opco-resend select {
            width: auto;
            max-width: 240px;
        }

        .filters {
            margin-bottom: 0;
        }

        .invoice-org {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            border-radius: 3px;
            background: #eef2f7;
            color: #4a5a70;
            font-size: 11px;
            font-weight: 600;
        }

        .invoice-detail .btn-virement {
            margin-top: 14px;
        }

        .invoice-pagination {
            padding: 14px 16px;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {

            /* ---------- Déploiement du détail ---------- */
            $('.invoice-row').on('click', function () {
                var $block = $(this).closest('.invoice-block'),
                    open = !$block.hasClass('is-open');

                $block.toggleClass('is-open', open);
                $block.find('.invoice-detail').slideToggle(160);
                $block.find('.invoice-toggle').attr('aria-expanded', open);
            });

            /* ---------- Règlement par virement ---------- */
            $('.btn-virement').on('click', function () {
                $('#virement-no').text($(this).data('no'));
                $('#virement-form').attr('action', $(this).data('url'));
                $('#virementModal').modal('show');
            });
        });
    
            /* ---------- OPCO-EP : recherche de convention (3 caractères, 3 propositions) ---------- */
            (function () {
                var contracts = @json($opcoPending);
                var $input = $('#opco-contract-search'), $id = $('#opco-contract-id'),
                    $list = $('#opco-suggestions'), form = document.getElementById('opco-form');
                if (!$input.length) return;

                var norm = function (v) {
                    return (v || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                };
                var esc = function (v) { return $('<div>').text(v).html(); };
                var current = [], active = -1;

                function choose(c) {
                    $input.val(c.no + ' · ' + c.company);
                    $id.val(c.id);
                    form.dataset.no = c.no;
                    $list.hide();
                }

                function render() {
                    $list.empty();
                    if (!current.length) {
                        $list.append('<li class="sug-empty">Aucune convention trouvée</li>');
                    }
                    current.forEach(function (c, i) {
                        $('<li>')
                            .toggleClass('active', i === active)
                            .html('<div class="sug-no">' + esc(c.no) + '</div>'
                                + '<div class="sug-meta">' + esc(c.company) + ' · signée le ' + esc(c.signed_at) + '</div>')
                            .on('mousedown', function (e) { e.preventDefault(); choose(c); })
                            .appendTo($list);
                    });
                    $list.show();
                }

                $input.on('input', function () {
                    $id.val('');
                    var q = norm($input.val().trim());
                    if (q.length < 3) { $list.hide(); return; }
                    current = contracts.filter(function (c) {
                        return norm(c.no + ' ' + c.company).indexOf(q) !== -1;
                    }).slice(0, 3);
                    active = current.length ? 0 : -1;
                    render();
                });

                $input.on('keydown', function (e) {
                    if (!$list.is(':visible') || !current.length) return;
                    if (e.key === 'ArrowDown') { active = (active + 1) % current.length; render(); e.preventDefault(); }
                    if (e.key === 'ArrowUp') { active = (active - 1 + current.length) % current.length; render(); e.preventDefault(); }
                    if (e.key === 'Enter' && active >= 0) { choose(current[active]); e.preventDefault(); }
                    if (e.key === 'Escape') { $list.hide(); }
                });

                $input.on('blur', function () { $list.hide(); });
            })();
        </script>
@stop