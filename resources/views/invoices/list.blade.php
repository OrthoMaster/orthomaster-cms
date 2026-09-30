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

                {{-- ============ FILTRES ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <form class="row" action="{{ route('invoices.list') }}" method="GET">
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

                            <div class="col-md-5">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                                @if ($search !== '' || $status)
                                    <a href="{{ route('invoices.list') }}" class="btn btn-default">
                                        Réinitialiser
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ============ LISTE ============ --}}
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
                                @endphp

                                <div class="invoice-block" data-invoice="{{ $invoice->id }}">
                                    {{-- La ligne entière déploie le détail --}}
                                    <div class="invoice-row">
                                        <button type="button" class="invoice-toggle" aria-expanded="false">
                                            <i class="voyager-angle-right"></i>
                                        </button>

                                        <div class="invoice-no">{{ $invoice->no ?: '—' }}</div>

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
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>
                                                </dl>
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

                                                @unless ($isPaid)
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
    </script>
@stop