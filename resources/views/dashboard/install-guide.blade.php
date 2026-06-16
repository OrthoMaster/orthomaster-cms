@extends('voyager::master')

@section('page_title', 'Installation du tracker')

@section('content')
    <div class="page-content">
        <div class="analytics-container vts-install-guide">

            {{-- ============================================================ --}}
            {{--  En-tête + bouton de téléchargement                           --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered vts-hero">
                        <div class="panel-body">
                            <h2 style="margin-top:0;">Installation du tracker d'appels</h2>
                            <p style="font-size:14px; color:#555; margin-bottom:20px;">
                                Ce guide vous accompagne pour installer le tracker sur votre Mac et configurer la
                                continuité avec votre iPhone. <strong>Comptez environ 10 minutes.</strong>
                            </p>

                            <div class="vts-prereqs">
                                <div class="vts-prereq-title">Avant de commencer&nbsp;:</div>
                                <ul>
                                    <li>Un Mac avec macOS 12 ou supérieur</li>
                                    <li>Votre iPhone professionnel (avec la carte SIM de l'entreprise)</li>
                                    <li>Le mot de passe administrateur du Mac</li>
                                    <li>iPhone et Mac connectés au même réseau Wi-Fi</li>
                                </ul>
                            </div>

                            <a href="{{ $downloadUrl ?? '#' }}" class="btn btn-lg btn-info vts-download-btn">
                                <span style="font-size:18px;">⬇</span>
                                &nbsp; Télécharger tracker.zip
                            </a>
                            <p class="text-muted" style="margin-top:8px; font-size:12px;">
                                Fichier ZIP, environ {{ $downloadSize ?? '30 Mo' }} — compatible Intel et Apple Silicon
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  ÉTAPE 1 — Installation                                       --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <span class="vts-step-badge">1</span>
                                Installer le tracker sur votre Mac
                            </h3>
                        </div>
                        <div class="panel-body">

                            <div class="vts-substep">
                                <div class="vts-substep-num">1.1</div>
                                <div class="vts-substep-body">
                                    <h4>Décompresser le fichier</h4>
                                    <p>
                                        Ouvrez le dossier <strong>Téléchargements</strong> dans le Finder.
                                        Double-cliquez sur <code>tracker.zip</code> — un dossier <code>tracker</code>
                                        apparaît à côté.
                                    </p>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">1.2</div>
                                <div class="vts-substep-body">
                                    <h4>Ouvrir le Terminal</h4>
                                    <p>
                                        Appuyez sur <kbd>⌘ Cmd</kbd> + <kbd>Espace</kbd>, tapez
                                        <strong>Terminal</strong>, puis appuyez sur Entrée. Une fenêtre noire
                                        (ou blanche) apparaît.
                                    </p>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">1.3</div>
                                <div class="vts-substep-body">
                                    <h4>Aller dans le dossier décompressé</h4>
                                    <p>Tapez la commande suivante puis appuyez sur Entrée&nbsp;:</p>
                                    <pre class="vts-code">cd ~/Downloads/tracker</pre>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">1.4</div>
                                <div class="vts-substep-body">
                                    <h4>Lancer le script d'installation</h4>
                                    <p>Tapez&nbsp;:</p>
                                    <pre class="vts-code">sudo bash setup.sh</pre>
                                    <p>
                                        Le Terminal demande votre <strong>mot de passe administrateur</strong>.
                                        Tapez-le et appuyez sur Entrée.
                                    </p>
                                    <div class="vts-tip">
                                        💡 <strong>Astuce&nbsp;:</strong> lorsque vous tapez le mot de passe,
                                        rien ne s'affiche à l'écran — c'est normal, pour des raisons de sécurité.
                                        Tapez-le entièrement puis appuyez sur Entrée.
                                    </div>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">1.5</div>
                                <div class="vts-substep-body">
                                    <h4>Saisir votre numéro de téléphone professionnel</h4>
                                    <p>
                                        Le script vous demande le numéro de la carte SIM de votre iPhone professionnel.
                                        Saisissez-le au format&nbsp;:
                                    </p>
                                    <ul>
                                        <li><code>+33612345678</code> (format international)</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">1.6</div>
                                <div class="vts-substep-body">
                                    <h4>Accorder l'<strong>Accès complet au disque</strong> au tracker</h4>
                                    <p>
                                        Étape <strong>critique</strong>&nbsp;: sans cela, le tracker ne pourra pas
                                        lire l'historique des appels.
                                    </p>

                                    <ol class="vts-numbered">
                                        <li>
                                            Ouvrez <strong>Réglages Système</strong>
                                            (menu Pomme&nbsp; → &nbsp;Réglages Système).
                                        </li>
                                        <li>
                                            Dans le panneau de gauche, allez dans
                                            <strong>Confidentialité et sécurité</strong>.
                                        </li>
                                        <li>
                                            Cliquez sur <strong>Accès complet au disque</strong>
                                            (vous devrez peut-être faire défiler vers le bas).
                                        </li>
                                        <li>
                                            Cliquez sur le bouton <strong>+</strong> en bas de la liste.
                                            Saisissez votre mot de passe si demandé.
                                        </li>
                                        <li>
                                            Une fenêtre Finder apparaît. Appuyez sur
                                            <kbd>⌘ Cmd</kbd> + <kbd>⇧ Maj</kbd> + <kbd>G</kbd>
                                            pour ouvrir le champ "Aller au dossier".
                                        </li>
                                        <li>
                                            Collez ce chemin&nbsp;:
                                            <pre class="vts-code">/Library/Application Support/CompanyTracker/runtime/</pre>
                                        </li>
                                        <li>
                                            Sélectionnez le fichier nommé <code>tracker</code>
                                            (sans extension), puis cliquez <strong>Ouvrir</strong>.
                                        </li>
                                        <li>
                                            De retour dans la liste, vérifiez que la
                                            <strong>case à côté de "tracker" est cochée</strong> (en bleu).
                                        </li>
                                    </ol>

                                    <div class="vts-warning">
                                        ⚠ <strong>Important&nbsp;:</strong> si vous mettez à jour le tracker
                                        plus tard, vous devrez peut-être <strong>refaire cette étape</strong> car
                                        macOS recalcule la signature à chaque mise à jour.
                                    </div>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">1.7</div>
                                <div class="vts-substep-body">
                                    <h4>Vérifier que tout fonctionne (pour Développeur/Administrateur)</h4>
                                    <p>
                                        Revenez dans le tableau de bord (lien Tableau de bord dans le menu).
                                        Votre Mac doit apparaître avec le statut <strong>En ligne</strong> (badge vert).
                                    </p>
                                    <p>
                                        Si vous voyez <strong>Accès refusé</strong>, revérifiez l'étape 1.6&nbsp;:
                                        le fichier <code>tracker</code> doit bien apparaître coché dans la liste
                                        Accès complet au disque.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  ÉTAPE 2 — Configurer la continuité iCloud                    --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <span class="vts-step-badge">2</span>
                                Configurer la continuité avec votre iPhone
                            </h3>
                        </div>
                        <div class="panel-body">

                            <p>
                                Pour que les appels passés et reçus sur votre iPhone professionnel apparaissent
                                aussi sur le Mac (et soient donc enregistrés par le tracker), vous devez activer
                                la <strong>continuité Apple</strong>.
                            </p>

                            <div class="vts-substep">
                                <div class="vts-substep-num">2.1</div>
                                <div class="vts-substep-body">
                                    <h4>Vérifier les conditions</h4>
                                    <ul>
                                        <li>iPhone et Mac connectés au <strong>même réseau Wi-Fi</strong></li>
                                        <li>
                                            <strong>Même identifiant Apple</strong> sur les deux appareils
                                            (Réglages → votre nom)
                                        </li>
                                        <li>
                                            <strong>Bluetooth activé</strong> sur les deux appareils
                                            (recommandé pour une meilleure stabilité)
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">2.2</div>
                                <div class="vts-substep-body">
                                    <h4>Sur votre iPhone</h4>
                                    <ol class="vts-numbered">
                                        <li>Ouvrez <strong>Réglages</strong></li>
                                        <li>Allez dans <strong>Téléphone</strong></li>
                                        <li>Touchez <strong>Appels sur d'autres appareils</strong></li>
                                        <li>
                                            Activez <strong>« Autoriser les appels sur d'autres appareils »</strong>
                                            (interrupteur vert)
                                        </li>
                                        <li>
                                            Dans la liste qui apparaît, activez la ligne correspondant
                                            à <strong>votre Mac</strong>
                                        </li>
                                    </ol>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">2.3</div>
                                <div class="vts-substep-body">
                                    <h4>Sur votre Mac</h4>
                                    <ol class="vts-numbered">
                                        <li>Ouvrez l'application <strong>FaceTime</strong></li>
                                        <li>
                                            Dans la barre de menu en haut, cliquez sur
                                            <strong>FaceTime → Réglages…</strong>
                                        </li>
                                        <li>Allez dans l'onglet <strong>Général</strong></li>
                                        <li>Cochez <strong>« Appels depuis l'iPhone »</strong></li>
                                    </ol>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">2.4</div>
                                <div class="vts-substep-body">
                                    <h4>Tester</h4>
                                    <p>Demandez à un collègue d'appeler votre numéro professionnel&nbsp;:</p>
                                    <ul>
                                        <li>L'iPhone sonne&nbsp;: ✓ normal</li>
                                        <li>Le Mac affiche aussi une notification d'appel&nbsp;: ✓ continuité OK</li>
                                        <li>
                                            Le Mac ne sonne pas&nbsp;: revérifiez les conditions de l'étape 2.1
                                            (Wi-Fi, identifiant Apple identique)
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="vts-warning">
                                ⚠ <strong>À retenir&nbsp;:</strong> la continuité ne fonctionne que si l'iPhone
                                est <strong>allumé, déverrouillé au moins une fois, et sur le même Wi-Fi</strong> que
                                le Mac. Si vous éteignez l'iPhone ou le déconnectez du Wi-Fi de l'entreprise,
                                les appels ne seront plus enregistrés.
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  ÉTAPE 3 — Partager Google Calendar avec OrthoMaster          --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <span class="vts-step-badge">3</span>
                                Partager votre Google Calendar avec OrthoMaster
                            </h3>
                        </div>
                        <div class="panel-body">

                            <p>
                                Pour que le tableau de bord puisse créer automatiquement les rendez-vous
                                dans votre agenda Google quand vous définissez un meeting, vous devez
                                <strong>partager votre calendrier avec le compte de service d'OrthoMaster</strong>.
                                Cette étape se fait <strong>une seule fois</strong>.
                            </p>

                            <div class="vts-substep">
                                <div class="vts-substep-num">3.1</div>
                                <div class="vts-substep-body">
                                    <h4>Ouvrir Google Calendar</h4>
                                    <p>
                                        Rendez-vous sur
                                        <a href="https://calendar.google.com" target="_blank">calendar.google.com</a>
                                        et connectez-vous avec votre compte Google professionnel
                                        (<code>prénom.nom@orthomaster.fr</code>).
                                    </p>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">3.2</div>
                                <div class="vts-substep-body">
                                    <h4>Trouver votre agenda principal</h4>
                                    <p>
                                        Dans le panneau de gauche, sous <strong>« Mes agendas »</strong>,
                                        repérez la ligne portant votre nom (ex. <em>Tien Nguyen</em>).
                                        C'est votre agenda principal, celui que le tracker doit pouvoir mettre à jour.
                                    </p>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">3.3</div>
                                <div class="vts-substep-body">
                                    <h4>Ouvrir les paramètres de partage</h4>
                                    <p>
                                        Passez la souris sur la ligne de votre agenda. Trois petits points
                                        (<strong>⋮</strong>) apparaissent à droite du nom. Cliquez dessus,
                                        puis choisissez <strong>« Paramètres et partage »</strong>.
                                    </p>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">3.4</div>
                                <div class="vts-substep-body">
                                    <h4>Ajouter le compte de service OrthoMaster</h4>
                                    <p>
                                        Sur la page de paramètres, faites défiler jusqu'à la section
                                        <strong>« Partager avec des personnes et des groupes spécifiques »</strong>,
                                        puis cliquez sur <strong>« Ajouter des personnes et des groupes »</strong>.
                                    </p>
                                    <p>Dans le champ qui apparaît, collez exactement cette adresse&nbsp;:</p>

                                    <div class="vts-copy-row">
                                        <code id="vtsServiceAccountEmail">google-calendar-cms-generation@orthomaster-b5a0e.iam.gserviceaccount.com</code>
                                        <button type="button" class="btn btn-xs btn-default" id="vtsCopyEmailBtn">
                                            📋 Copier
                                        </button>
                                    </div>

                                    <div class="vts-tip">
                                        💡 <strong>Astuce&nbsp;:</strong> utilisez le bouton « Copier »
                                        pour éviter toute faute de frappe — cette adresse est sensible
                                        à chaque caractère.
                                    </div>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">3.5</div>
                                <div class="vts-substep-body">
                                    <h4>Choisir le bon niveau de permission</h4>
                                    <p>
                                        Dans le menu déroulant à côté de l'adresse, sélectionnez&nbsp;:
                                    </p>
                                    <pre class="vts-code">Modifier des événements</pre>
                                    <p>
                                        Ce niveau est <strong>strictement nécessaire</strong>&nbsp;:
                                        le tracker doit pouvoir créer et modifier des événements, mais
                                        n'a pas besoin de gérer les partages de votre agenda.
                                    </p>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">3.6</div>
                                <div class="vts-substep-body">
                                    <h4>Valider</h4>
                                    <p>
                                        Cliquez sur <strong>« Envoyer »</strong>.
                                    </p>
                                    <div class="vts-tip">
                                        💡 Google peut afficher un avertissement indiquant que cette adresse
                                        n'est pas un compte Google standard. C'est normal&nbsp;: il s'agit
                                        d'un <strong>compte technique d'OrthoMaster</strong>, pas d'une
                                        personne. Confirmez l'envoi.
                                    </div>
                                </div>
                            </div>

                            <div class="vts-substep">
                                <div class="vts-substep-num">3.7</div>
                                <div class="vts-substep-body">
                                    <h4>Vérifier</h4>
                                    <p>
                                        Pour confirmer que tout fonctionne, demandez à un administrateur
                                        de créer un meeting de test depuis le tableau de bord en votre nom.
                                        Un événement doit apparaître <strong>automatiquement</strong>
                                        dans votre Google Calendar, avec un lien Google Meet inclus.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  En cas de problème                                           --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">En cas de problème</h3>
                        </div>
                        <div class="panel-body">
                            <p>Si l'installation échoue ou si votre Mac n'apparaît pas dans le tableau de bord&nbsp;:</p>
                            <ol class="vts-numbered">
                                <li>
                                    Vérifiez le statut de votre Mac dans la
                                    <a href="#">page Appareils du tableau de bord</a>.
                                    Le détail de l'erreur y est affiché si une autorisation manque.
                                </li>
                                <li>
                                    Réessayez l'étape 1.6 (Accès complet au disque)&nbsp;: c'est la cause
                                    la plus fréquente.
                                </li>
                                <li>
                                    Si le problème persiste, contactez l'administrateur en lui transmettant&nbsp;:
                                    le numéro de série de votre Mac (Pomme → À propos de ce Mac), votre numéro
                                    professionnel, et une capture d'écran du tableau de bord.
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <style>
        /* Page-level container */
        .vts-install-guide h2 { color: #333; }
        .vts-install-guide h4 { margin-top: 0; margin-bottom: 8px; color: #333; }

        /* Hero (téléchargement) */
        .vts-hero { border-left: 4px solid #5bc0de; }
        .vts-prereqs {
            background: #f8f9fa; border-left: 3px solid #5bc0de;
            padding: 12px 16px; margin-bottom: 20px; border-radius: 0 4px 4px 0;
        }
        .vts-prereq-title { font-weight: 600; margin-bottom: 6px; color: #333; }
        .vts-prereqs ul { margin: 0; padding-left: 20px; }
        .vts-prereqs li { line-height: 1.7; }
        .vts-download-btn {
            padding: 12px 30px; font-size: 16px; font-weight: 600;
            box-shadow: 0 2px 8px rgba(91, 192, 222, 0.3);
        }
        .vts-download-btn:hover { box-shadow: 0 4px 12px rgba(91, 192, 222, 0.45); }

        /* Pastille de numéro d'étape principale (1, 2, 3...) */
        .vts-step-badge {
            display: inline-block; width: 28px; height: 28px;
            background: #5bc0de; color: #fff; border-radius: 50%;
            text-align: center; line-height: 28px; font-weight: 600;
            margin-right: 8px; vertical-align: middle;
        }

        /* Sous-étape (1.1, 1.2, ...) */
        .vts-substep {
            display: flex; gap: 16px; padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .vts-substep:last-child { border-bottom: none; }
        .vts-substep-num {
            flex: 0 0 50px; font-size: 13px; font-weight: 600;
            color: #5bc0de; padding-top: 2px;
        }
        .vts-substep-body { flex: 1; }
        .vts-substep-body p { line-height: 1.6; margin-bottom: 10px; }
        .vts-substep-body ul, .vts-substep-body ol { line-height: 1.8; }
        .vts-substep-body code {
            background: #f5f5f5; padding: 2px 6px; border-radius: 3px;
            color: #c7254e; font-size: 13px;
        }
        .vts-substep-body kbd {
            background: #f5f5f5; border: 1px solid #ccc; border-bottom-width: 2px;
            border-radius: 3px; padding: 2px 6px; font-size: 12px;
            font-family: Menlo, Monaco, monospace;
            color: #333;
        }

        /* Numbered list type */
        ol.vts-numbered { padding-left: 22px; }
        ol.vts-numbered li { padding: 3px 0; }

        /* Code block (commandes Terminal) */
        .vts-code {
            background: #2d2d2d; color: #f8f8f2; padding: 10px 14px;
            border-radius: 4px; font-family: Menlo, Monaco, monospace;
            font-size: 13px; margin: 8px 0; white-space: pre-wrap;
        }

        /* Astuce / avertissement */
        .vts-tip, .vts-warning {
            padding: 10px 14px; border-radius: 4px; margin: 10px 0;
            font-size: 13px; line-height: 1.5;
        }
        .vts-tip { background: #fdf8ee; border-left: 3px solid #f0ad4e; }
        .vts-warning { background: #fdf4f4; border-left: 3px solid #d9534f; }
    </style>
@stop