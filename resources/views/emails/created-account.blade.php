@extends('emails.template.user-side')

@section('title', 'Bienvenue !')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name .' '. $user->first_name }},</div>

        <p>Nous sommes ravis de vous accueillir ! Votre compte a été créé avec succès. Vous trouverez ci-dessous vos
            informations de connexion.</p>

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

        <p><strong>Accédez à votre compte depuis :</strong></p>
        <div class="download-buttons" style="justify-items: center;">
            <a href="{{ setting('mail.ios_download_link') }}" class="download-button">App Store</a>
            <a href="{{ setting('mail.android_download_link') }}" class="download-button">Google Play</a>
            {{-- <a href="{{ setting('mail.web_app_link') }}" class="download-button">Version Web</a> --}}
        </div>

        <div class="divider"></div>

        <p>Si vous n'avez pas demandé la création de ce compte ou si vous avez des questions, n'hésitez pas à nous
            contacter.</p>

        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
