@extends('emails.template.user-side')

@section('title', 'Invitation à rejoindre le groupe')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour,</div>

        <p><strong>{{ $user->name . ' ' . $user->first_name }}</strong> vous a invité à rejoindre son groupe sur ORTHOMASTER.
        </p>

        <p>Si vous souhaitez en faire partie, veuillez cliquer sur le bouton ci-dessous afin de créer votre compte et
            rejoindre le groupe :</p>

        <div>
            <a href="{{ 'https://auth.orthomaster.fr/register?join_code=' . $joinCode }}" class="action-button">Ouverture de
                compte</a>
        </div>

        <p>Vous rencontrez un problème avec le bouton ci-dessus ? Copiez et collez l'URL suivante dans votre navigateur
            :<br>
            <strong><a href="{{ 'https://auth.orthomaster.fr/register?join_code=' . $joinCode }}">{{ 'https://auth.orthomaster.fr/register?join_code=' . $joinCode }}</a>
        </p>

        <div class="divider"></div>

        <p>Si vous ne souhaitez pas participer, veuillez ignorer cet e-mail.</p>

        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
