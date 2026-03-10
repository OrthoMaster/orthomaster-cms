@extends('emails.template.user-side')

@section('title', 'ORTHOMASTER évolue !')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name . ' ' . ($user->first_name ?? '') }},</div>
        <p>Nous lançons aujourd’hui une <b>nouvelle version</b> de l’application, plus <b>moderne</b>, plus <b>intuitive</b> et plus <b>personnalisée</b> pour vous accompagner au quotidien.</p>
        
        <p>>>> Mettez à jour ORTHOMASTER.</p>
        
        <br />

        <div class="greeting" style="margin-bottom: 10px; color:#eb7b0f">✨ NOUVEAUTÉS</div>
        <p style="margin-bottom: 5px;">Retrouvez régulièrement dans l’app :</p>
        <p style="padding-left: 20px;">
            • Quiz<br/>
            • Infographies<br/>
            • Podcasts<br/>
            • Cas pratiques<br/>
        </p>
        <p>Tous ces contenus sont <b>personnalisables</b> selon vos préférences dans votre <b>Profil</b>.</p>

        <p>Profitez aussi d’une version Web retravaillée accessible depuis : <a href="https://app.orthomaster.fr">https://app.orthomaster.fr</a>  avec vos identifiants habituels.</p>

        <p>🎁 Bien sûr, <b>ORTHOMASTER</b> reste <b>GRATUIT</b>, <b>sans abonnement</b>, et réservé aux <b>professionnels de santé</b>.</p>

        <br/>

        <div class="greeting" style="margin-bottom: 10px; color:#58cc02">🎓 Des FORMATIONS plus simples et plus modulaires</div>
        <p>Vous pouvez maintenant <b>composer vos formations</b> à partir de modules <b>courts et opérationnels</b> :</p>
        <p style="padding-left: 20px;"><i>Appareillage orthopédique, Examen clinique express, Rééducation fonctionnelle, et bien d’autres.</i></p>
        <p>Composez votre programme comme vous le voulez (organisme, modules, bénéficiaires), la génération d’une convention de formation se fait instantanément !</p>
        <p>💡 Et comme d’habitude, les modules débloqués sont <b>ACCESSIBLES À VIE</b>.</p>

        <br />

        <div class="greeting" style="margin-bottom: 10px; color:#ff4b4b">🎥 MÉDIAS, la plus grande bibliothèque francophone en orthopédie</div>
        <p>ORTHOMASTER, c’est aujourd’hui <b>plus de 500 vidéos d’experts</b> couvrant de nombreuses spécialités.</p>
        <p>À l’heure où les IA donnent souvent des informations à côté de la plaque, il est important d’avoir l’information par ceux qui étudient les sujets et transmettent leur expertise avec enthousiasme.</p>


        <br />
        <p><b>DONC</b>... <b style="color:#5696df">📲 METTEZ À JOUR</b> votre application et découvrez le nouvel ORTHOMASTER, aux côtés de plus de <b>7.300 professionnels de santé</b> engagés pour la <b>mobilité</b> des patients.</p>

        <div class="download-buttons">
            <a href="{{ setting('mail.ios_download_link') }}" class="download-button">
                <img
                    src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/settings/appstore-buttons/apple_logo_grey.png" />
                App Store
            </a>
            <a href="{{ setting('mail.android_download_link') }}" class="download-button">
                <img
                    src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/settings/appstore-buttons/google_play_logo.png" />
                Google Play
            </a>
        </div>

        <div class="divider"></div>

        <p style="margin-top: 30px;">Bienvenue dans votre nouvelle expérience ORTHOMASTER,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
