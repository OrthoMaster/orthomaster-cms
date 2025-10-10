@extends('emails.template.user-side')

@section('title', 'Réinitialiser le Mot de passe')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour ,</div>

        <p>Nous avons reçu une demande de réinitialisation du mot de passe pour votre compte avec l'adresse e-mail :
            <strong>{{ $email }}</strong>
        </p>

        <p>Veuillez cliquer sur le bouton ci-dessous pour réinitialiser votre mot de passe :</p>

        <div>
            <a href="{{ $url }}" class="action-button">Réinitialiser le Mot de Passe</a>
        </div>


        <p>Vous rencontrez un problème avec le bouton ci-dessus ? Copiez et collez l'URL suivante dans votre navigateur
            :<br>
            <strong><a href="{{ $url }}">{{ $url }}</a>
        </p>
        <p style="font-size: 14px;"><i> Ce lien expirera dans <strong>{{ $expireMinutes }} minutes.</i></p>

        <div class="divider"></div>

        <p>Si vous n'avez pas demandé de réinitialisation de mot de passe, veuillez ignorer cet e-mail. Votre compte reste
            sécurisé.</p>

        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
