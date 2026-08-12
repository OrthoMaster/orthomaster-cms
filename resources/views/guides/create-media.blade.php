@extends('voyager::master')

@section('page_title', ' Guide — Mettre en ligne une vidéo')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-question"></i> Créer et mettre en ligne une vidéo
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content container-fluid">

        <div class="row">
            <div class="col-lg-9 col-md-12">

                <p class="guide-intro">
                    Ce guide décrit les cinq étapes à suivre, de l'encodage du fichier
                    jusqu'à la publication dans l'application.
                </p>

                {{-- ============ ÉTAPE 1 ============ --}}
                <div class="panel panel-bordered guide-step">
                    <div class="panel-heading">
                        <h3 class="panel-title"><span class="step-badge">1</span> Encoder la vidéo</h3>
                    </div>
                    <div class="panel-body">

                        <p>
                            Rendez-vous sur l'outil d'encodage pour envoyer votre fichier vidéo
                            et lancer le traitement.
                        </p>

                        <p>
                            <a href="http://192.168.1.16:3001/" target="_blank" rel="noopener"
                               class="btn btn-primary">
                                <i class="voyager-external"></i> Ouvrir l'outil d'encodage
                            </a>
                            <code class="guide-url">http://192.168.1.16:3001/</code>
                        </p>

                        <div class="alert alert-warning">
                            <strong>Page inaccessible ?</strong>
                            L'outil n'est disponible que depuis le réseau interne.
                            Si la page ne s'ouvre pas, contactez l'équipe technique.
                        </div>

                        <p>
                            Une fois l'encodage terminé, un <strong>code vidéo</strong> vous est
                            communiqué. Conservez-le : il sera à saisir dans le CMS à l'étape&nbsp;2.
                        </p>

                        {{-- Capture d'écran : remplacer le chemin par l'image réelle --}}
                        <figure class="guide-figure">
                            <img src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/cms-guides/encodage-code-video.jpg"
                                 alt="Emplacement du code vidéo après l'encodage">
                            <figcaption>Le code vidéo s'affiche à la fin de l'encodage.</figcaption>
                        </figure>

                    </div>
                </div>

                {{-- ============ ÉTAPE 2 ============ --}}
                <div class="panel panel-bordered guide-step">
                    <div class="panel-heading">
                        <h3 class="panel-title"><span class="step-badge">2</span> Ajouter la vidéo dans le CMS</h3>
                    </div>
                    <div class="panel-body">

                        <p>
                            <a href="{{ route('medias.create') }}" class="btn btn-primary">
                                <i class="voyager-plus"></i> Créer un média
                            </a>
                            <code class="guide-url">{{ route('medias.create') }}</code>
                        </p>

                        <h4 class="guide-subtitle">Renseigner les informations</h4>

                        <dl class="guide-cards">
                            <dt>Informations</dt>
                            <dd>
                                Titre, vignette, durée, description et mots-clés.
                                Ce sont les éléments visibles par l'utilisateur dans l'application.
                            </dd>

                            <dt>Classement</dt>
                            <dd>
                                Auteurs, centres d'intérêt, catégorie et événement.
                                Ces champs déterminent où le média apparaîtra dans l'application.
                            </dd>

                            <dt>Diffusion</dt>
                            <dd>
                                Saisissez le <strong>code vidéo</strong> obtenu à l'étape&nbsp;1.
                                <span class="text-danger">
                                    N'activez pas le bouton <em><b>Publication<b></em> tant que le contenu
                                    n'est pas finalisé.
                                </span>
                            </dd>
                        </dl>

                        <h4 class="guide-subtitle">Créer la fiche</h4>

                        <p>
                            Cliquez sur <strong>Créer</strong>. La fiche est enregistrée et le bloc
                            <strong>Contenu</strong> apparaît en bas de la page.
                        </p>

                        <h4 class="guide-subtitle">Composer le contenu</h4>

                        <p>
                            Dans le bloc <strong>Contenu</strong>, construisez la page telle qu'elle
                            sera affichée dans l'application : texte, titres, listes, images,
                            lecteur vidéo et chapitres.
                        </p>
                    </div>
                </div>

                {{-- ============ ÉTAPE 3 ============ --}}
                <div class="panel panel-bordered guide-step">
                    <div class="panel-heading">
                        <h3 class="panel-title"><span class="step-badge">3</span> Mettre le fichier en ligne</h3>
                    </div>
                    <div class="panel-body">
                        <p>
                            Demandez à l'équipe technique de déposer la vidéo encodée sur S3.
                            Sans cette étape, le lecteur restera vide même si la fiche est complète.
                        </p>
                    </div>
                </div>

                {{-- ============ ÉTAPE 4 ============ --}}
                <div class="panel panel-bordered guide-step">
                    <div class="panel-heading">
                        <h3 class="panel-title"><span class="step-badge">4</span> Relire le contenu</h3>
                    </div>
                    <div class="panel-body">

                        <p>
                            Une fois le fichier en ligne, connectez-vous à l'application avec un
                            compte administrateur OrthoMaster : la vidéo devient consultable via la
                            recherche, ce qui permet de relire l'intégralité du contenu avant
                            publication.
                        </p>

                        <p>
                            <a href="https://app.orthomaster.fr/" target="_blank" rel="noopener"
                               class="btn btn-default">
                                <i class="voyager-external"></i> Ouvrir l'application
                            </a>
                            <code class="guide-url">https://app.orthomaster.fr/</code>
                        </p>

                    </div>
                </div>

                {{-- ============ ÉTAPE 5 ============ --}}
                <div class="panel panel-bordered guide-step guide-step-last">
                    <div class="panel-heading">
                        <h3 class="panel-title"><span class="step-badge">5</span> Publier</h3>
                    </div>
                    <div class="panel-body">

                        <p>De retour sur la fiche du média, dans le bloc <strong>Diffusion</strong> :</p>

                        <ol class="guide-list">
                            <li>Activez le bouton <strong>Publication</strong>.</li>
                            <li>Renseignez la <strong>date de publication</strong>.</li>
                            <li>Cliquez sur <strong>Enregistrer</strong>.</li>
                        </ol>

                        <div class="alert alert-success">
                            La vidéo est en ligne. Elle apparaît désormais dans l'application
                            pour l'ensemble des utilisateurs.
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>
@stop

@section('css')
    <style>
        .guide-intro {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 24px;
        }

        /* --- Étapes --- */
        .guide-step { position: relative; }

        .guide-step .panel-heading { padding-left: 16px; }

        .guide-step .panel-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 17px;
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #22A7F0;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* Trait vertical reliant les étapes */
        .guide-step:not(.guide-step-last)::after {
            content: '';
            position: absolute;
            left: 31px;
            bottom: -20px;
            width: 2px;
            height: 20px;
            background: #dfe3e9;
        }

        .guide-step .panel-body { padding: 20px 24px; }
        .guide-step .panel-body > p:last-child { margin-bottom: 0; }

        .guide-subtitle {
            margin-top: 24px;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #8a9199;
        }

        .guide-subtitle:first-child { margin-top: 0; }

        /* --- Description des blocs du formulaire --- */
        .guide-cards { margin: 0; }

        .guide-cards dt {
            display: inline-block;
            padding: 3px 10px;
            margin-bottom: 6px;
            border-radius: 4px;
            background: #eef3f9;
            border: 1px solid #d6dce6;
            font-size: 13px;
            color: #37474f;
        }

        .guide-cards dd {
            margin: 0 0 18px 0;
            padding-left: 2px;
            color: #4b5563;
        }

        .guide-cards dd:last-child { margin-bottom: 0; }

        .guide-list { padding-left: 20px; }
        .guide-list li { margin-bottom: 6px; }

        .guide-url {
            display: inline-block;
            margin-left: 10px;
            font-size: 12px;
            color: #6b7280;
            background: #f4f6f9;
            border: 1px solid #e3e6ec;
        }

        /* --- Captures d'écran --- */
        .guide-figure {
            margin: 18px 0 0;
            padding: 12px;
            background: #f7f9fc;
            border: 1px solid #e3e6ec;
            border-radius: 6px;
        }

        .guide-figure img {
            display: block;
            max-width: 100%;
            height: auto;
            border: 1px solid #dfe3e9;
            border-radius: 4px;
            background: #fff;
        }

        .guide-figure figcaption {
            margin-top: 10px;
            font-size: 12px;
            color: #8a9199;
            text-align: center;
        }

        @media (max-width: 767px) {
            .guide-url { display: block; margin: 8px 0 0; }
        }
    </style>
@stop