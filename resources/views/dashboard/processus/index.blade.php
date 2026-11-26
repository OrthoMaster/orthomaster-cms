@extends('voyager::master')

@section('page_title', ' Processus FIFPL / OPCO-EP')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-params"></i> Processus FIFPL / OPCO-EP
        </h1>
        <a href="{{ route('invoices.list') }}" class="btn btn-default btn-header">
            <i class="voyager-receipt"></i> Factures
        </a>
    </div>
@stop

@section('content')
    {{-- Schémas statiques : parcours d'un achat de formation financé par le FIFPL et par l'OPCO-EP --}}
    <div class="page-content browse container-fluid">
        <div class="row">
            <div class="col-lg-6">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <h3 class="process-title">FIFPL</h3>
                        <p class="process-sub">Chaque apprenant paie sa facture (carte dans l'app ou virement), puis reçoit facture acquittée et attestation.</p>
                        <svg width="100%" viewBox="0 0 680 970" role="img" class="process-svg" font-family="inherit">
                        <title>Parcours FIFPL</title><desc>Étapes FIFPL par système : panier et signature du devis dans l&#x27;app, facture à payer dans Odoo, relance, paiement par carte ou virement, facture acquittée et attestation, e-mail final.</desc>
                        <defs><marker id="arr-fifpl" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M2 1L8 5L2 9" fill="none" stroke="#888780" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></marker></defs>
                        <rect x="42" y="36" width="146" height="880" rx="12" fill="#E6F1FB"/>
                        <text x="115" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">App</text>
                        <text x="115" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Utilisateur</text>
                        <rect x="192" y="36" width="146" height="880" rx="12" fill="#FAEEDA"/>
                        <text x="265" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">CMS</text>
                        <text x="265" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Gestionnaire</text>
                        <rect x="342" y="36" width="146" height="880" rx="12" fill="#FBEAF0"/>
                        <text x="415" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">Stripe</text>
                        <text x="415" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Paiement par carte</text>
                        <rect x="492" y="36" width="146" height="880" rx="12" fill="#EAF3DE"/>
                        <text x="565" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">Odoo</text>
                        <text x="565" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Comptabilité</text>
                        <rect x="46" y="96" width="138" height="92" rx="8" fill="#E1F5EE" stroke="#0F6E56" stroke-width="0.6"/>
                        <text x="115" y="118" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#085041">Panier FIFPL</text>
                        <text x="115" y="138" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Choix formations</text>
                        <text x="115" y="155" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">et apprenants</text>
                        <rect x="46" y="216" width="138" height="92" rx="8" fill="#E1F5EE" stroke="#0F6E56" stroke-width="0.6"/>
                        <text x="115" y="238" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#085041">Devis signé</text>
                        <text x="115" y="258" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Convention + prog.</text>
                        <text x="115" y="275" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">envoyés à chacun</text>
                        <text x="115" y="292" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Formations ouvertes</text>
                        <rect x="496" y="336" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="565" y="358" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">Facture à payer</text>
                        <text x="565" y="378" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">1 par apprenant</text>
                        <text x="565" y="395" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Date : J + 1 mois</text>
                        <text x="565" y="412" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Échéance J + 2 mois</text>
                        <rect x="46" y="456" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="115" y="478" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">Relance</text>
                        <text x="115" y="498" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">E-mail + notif.</text>
                        <text x="115" y="515" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Rappel après 1 mois</text>
                        <text x="115" y="532" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">puis tous les 15 j</text>
                        <rect x="196" y="576" width="138" height="92" rx="8" fill="#EEEDFE" stroke="#534AB7" stroke-width="0.6"/>
                        <text x="265" y="598" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#3C3489">Virement</text>
                        <text x="265" y="618" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#534AB7">Saisi par le</text>
                        <text x="265" y="635" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#534AB7">gestionnaire</text>
                        <rect x="346" y="576" width="138" height="92" rx="8" fill="#E1F5EE" stroke="#0F6E56" stroke-width="0.6"/>
                        <text x="415" y="598" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#085041">Paiement carte</text>
                        <text x="415" y="618" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Par l&#x27;apprenant</text>
                        <text x="415" y="635" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">dans l&#x27;app</text>
                        <rect x="496" y="696" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="565" y="718" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">Facture payée</text>
                        <text x="565" y="738" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Facture acquittée</text>
                        <text x="565" y="755" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">+ attestation (S3)</text>
                        <text x="565" y="772" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Paiement dans Odoo</text>
                        <rect x="46" y="816" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="115" y="838" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">E-mail final</text>
                        <text x="115" y="858" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Facture acquittée</text>
                        <text x="115" y="875" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">+ attestation</text>
                        <text x="115" y="892" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Dès J + 2 mois</text>
                        <path d="M115 190 L115 214" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-fifpl)"/>
                        <path d="M115 310 L115 322 L565 322 L565 334" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-fifpl)"/>
                        <path d="M565 430 L565 442 L115 442 L115 454" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-fifpl)"/>
                        <path d="M115 550 L115 562 L415 562 L415 574" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-fifpl)"/>
                        <path d="M265 562 L265 574" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-fifpl)"/>
                        <path d="M415 670 L415 682 L565 682 L565 694" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-fifpl)"/>
                        <path d="M265 670 L265 682 L415 682" fill="none" stroke="#888780" stroke-width="1.2"/>
                        <path d="M565 790 L565 802 L115 802 L115 814" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-fifpl)"/>
                        <rect x="40" y="931" width="14" height="14" rx="3" fill="#E1F5EE" stroke="#0F6E56" stroke-width="0.6"/>
                        <text x="62" y="938" dominant-baseline="central" font-size="12" fill="#5F5E5A">Utilisateur</text>
                        <rect x="150" y="931" width="14" height="14" rx="3" fill="#EEEDFE" stroke="#534AB7" stroke-width="0.6"/>
                        <text x="172" y="938" dominant-baseline="central" font-size="12" fill="#5F5E5A">Gestionnaire</text>
                        <rect x="270" y="931" width="14" height="14" rx="3" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="292" y="938" dominant-baseline="central" font-size="12" fill="#5F5E5A">Automatique</text>
                        <text x="450" y="938" dominant-baseline="central" font-size="12" fill="#5F5E5A">J = date de signature</text>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <h3 class="process-title">OPCO-EP</h3>
                        <p class="process-sub">Une facture par dossier, adressée à l'OPCO ; chaque apprenant reçoit son certificat, le gestionnaire dépose le dossier sur le site de l'OPCO.</p>
                        <svg width="100%" viewBox="0 0 680 1090" role="img" class="process-svg" font-family="inherit">
                        <title>Parcours OPCO-EP</title><desc>Étapes OPCO-EP par système : panier et signature du devis, facture OPCO dans Odoo, attestation sur le CMS, certificat envoyé à chaque apprenant, dépôt du dossier sur le site de l&#x27;OPCO, paiement par l&#x27;OPCO, facture marquée payée sur le CMS.</desc>
                        <defs><marker id="arr-opco" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M2 1L8 5L2 9" fill="none" stroke="#888780" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></marker></defs>
                        <rect x="42" y="36" width="146" height="1000" rx="12" fill="#E6F1FB"/>
                        <text x="115" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">App</text>
                        <text x="115" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Utilisateur</text>
                        <rect x="192" y="36" width="146" height="1000" rx="12" fill="#FAEEDA"/>
                        <text x="265" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">CMS</text>
                        <text x="265" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Gestionnaire</text>
                        <rect x="342" y="36" width="146" height="1000" rx="12" fill="#EAF3DE"/>
                        <text x="415" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">Odoo</text>
                        <text x="415" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Comptabilité</text>
                        <rect x="492" y="36" width="146" height="1000" rx="12" fill="#FAECE7"/>
                        <text x="565" y="56" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#2b3a4a">Web OPCO</text>
                        <text x="565" y="74" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#76838f">Gestionnaire</text>
                        <rect x="46" y="96" width="138" height="92" rx="8" fill="#E1F5EE" stroke="#0F6E56" stroke-width="0.6"/>
                        <text x="115" y="118" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#085041">Panier OPCO-EP</text>
                        <text x="115" y="138" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Choix formations</text>
                        <text x="115" y="155" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">et apprenants</text>
                        <rect x="46" y="216" width="138" height="92" rx="8" fill="#E1F5EE" stroke="#0F6E56" stroke-width="0.6"/>
                        <text x="115" y="238" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#085041">Devis signé</text>
                        <text x="115" y="258" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Dossiers ≤ 10 h</text>
                        <text x="115" y="275" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Convention + prog.</text>
                        <text x="115" y="292" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#0F6E56">Formations ouvertes</text>
                        <rect x="346" y="336" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="415" y="358" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">Facture OPCO</text>
                        <text x="415" y="378" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">1 par dossier</text>
                        <text x="415" y="395" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Date : J + 1 mois</text>
                        <text x="415" y="412" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Ni e-mail, ni app</text>
                        <rect x="196" y="456" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="265" y="478" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">Attestation</text>
                        <text x="265" y="498" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">À J + 1 mois</text>
                        <text x="265" y="515" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Tous les certificats</text>
                        <text x="265" y="532" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Sur S3, vue CMS</text>
                        <rect x="46" y="576" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="115" y="598" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">Certificat</text>
                        <text x="115" y="618" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">À chaque apprenant</text>
                        <text x="115" y="635" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Son certificat seul</text>
                        <text x="115" y="652" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#5F5E5A">Sans facture</text>
                        <rect x="496" y="696" width="138" height="92" rx="8" fill="#EEEDFE" stroke="#534AB7" stroke-width="0.6"/>
                        <text x="565" y="718" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#3C3489">Dépôt dossier</text>
                        <text x="565" y="738" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#534AB7">Facture OPCO</text>
                        <text x="565" y="755" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#534AB7">+ attestation</text>
                        <rect x="496" y="816" width="138" height="92" rx="8" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="565" y="862" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#444441">Paiement OPCO</text>
                        <rect x="196" y="936" width="138" height="92" rx="8" fill="#EEEDFE" stroke="#534AB7" stroke-width="0.6"/>
                        <text x="265" y="958" text-anchor="middle" dominant-baseline="central" font-size="14" font-weight="600" fill="#3C3489">Facture payée</text>
                        <text x="265" y="978" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#534AB7">Marquer payée</text>
                        <text x="265" y="995" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#534AB7">En attente → Payée</text>
                        <text x="265" y="1012" text-anchor="middle" dominant-baseline="central" font-size="12" fill="#534AB7">Irréversible</text>
                        <path d="M115 190 L115 214" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-opco)"/>
                        <path d="M115 310 L115 322 L415 322 L415 334" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-opco)"/>
                        <path d="M415 430 L415 442 L265 442 L265 454" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-opco)"/>
                        <path d="M265 550 L265 562 L115 562 L115 574" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-opco)"/>
                        <path d="M115 670 L115 682 L565 682 L565 694" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-opco)"/>
                        <path d="M565 790 L565 814" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-opco)"/>
                        <path d="M565 910 L565 922 L265 922 L265 934" fill="none" stroke="#888780" stroke-width="1.2" marker-end="url(#arr-opco)"/>
                        <rect x="40" y="1051" width="14" height="14" rx="3" fill="#E1F5EE" stroke="#0F6E56" stroke-width="0.6"/>
                        <text x="62" y="1058" dominant-baseline="central" font-size="12" fill="#5F5E5A">Utilisateur</text>
                        <rect x="150" y="1051" width="14" height="14" rx="3" fill="#EEEDFE" stroke="#534AB7" stroke-width="0.6"/>
                        <text x="172" y="1058" dominant-baseline="central" font-size="12" fill="#5F5E5A">Gestionnaire</text>
                        <rect x="270" y="1051" width="14" height="14" rx="3" fill="#F1EFE8" stroke="#888780" stroke-width="0.6"/>
                        <text x="292" y="1058" dominant-baseline="central" font-size="12" fill="#5F5E5A">Automatique / OPCO</text>
                        <text x="450" y="1058" dominant-baseline="central" font-size="12" fill="#5F5E5A">J = date de signature</text>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .btn-header {
            margin: 22px 0 0 10px;
        }

        .process-title {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 600;
            color: #2b3a4a;
        }

        .process-sub {
            margin: 0 0 14px;
            font-size: 13px;
            color: #76838f;
        }

        .process-svg {
            display: block;
            max-width: 680px;
            margin: 0 auto;
        }
    </style>
@stop
