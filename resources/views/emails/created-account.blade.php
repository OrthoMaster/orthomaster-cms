@extends('emails.template.user-side')

@section('title', 'Bienvenue sur OrthoMaster !')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name . ' ' . $user->first_name }},</div>
        <p>Votre compte a été créé avec succès.</p>
        <p>Nous sommes ravis de vous accueillir sur le plus grand écosystème de l'orthopédie !</p>
        <p>Voici ci-dessous vos informations de connexion.</p>

        <div class="credentials-box">
            <div class="credential-row">
                <span class="credential-label">Nom d'utilisateur / Email :</span>
                <div class="credential-value">{!! str_replace('@', '&#64;', $user->email) !!}</div>
            </div>
            <div class="credential-row">
                <span class="credential-label">Mot de passe temporaire :</span>
                <div class="credential-value">{{ $user->password_temp }}</div>
            </div>
        </div>

        <div class="warning-box">
            <p><strong>Important :</strong> Pour des raisons de sécurité, nous vous
                recommandons fortement de changer votre mot de passe temporaire lors de votre première connexion.</p>
        </div>
        <br/>
        <div class="greeting" style="margin-bottom: 10px;">Pour accéder à OrthoMaster :</div>
        <p>Retrouvez-nous sur tout support mobile <b>( smartphone / tablette )</b> et <b>ordinateur</b> :</p>
        <div class="download-buttons">
            <a href="{{ setting('mail.ios_download_link') }}" class="download-button">
                <img src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/settings/appstore-buttons/apple_logo_grey.png" />
                App Store
            </a>
            <a href="{{ setting('mail.android_download_link') }}" class="download-button">
                <img src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/settings/appstore-buttons/google_play_logo.png" />
                Google Play
            </a>
            <a href="{{ setting('mail.web_app_link') }}" target="_blank" class="download-button">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                </svg>
                Web  <small style="color:#eb7b0f; font-size:14px">*</small>
            </a>
            {{-- <a href="{{ setting('mail.web_app_link') }}" class="download-button">Version Web</a> --}}
        </div>

        <small class="sub-version-web"><span style="color:#eb7b0f;">*</span> La version web est uniquement accessible sur ordinateur.</small>


        <div class="divider"></div>

        <p>Si vous n'avez pas demandé la création de ce compte ou si vous avez des questions, n'hésitez pas à nous
            contacter.</p>

        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
