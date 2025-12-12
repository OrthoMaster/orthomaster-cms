@extends('emails.template.user-side')

@section('title', 'Votre Espace Pro !')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name . ' ' . $user->first_name }},</div>
        <p>Votre Espace Pro a été créé avec succès.</p>
        
        <p>Veuillez cliquer sur le bouton ci-dessous pour ajouter votre équipe :</p>

        <div>
            <a href="{{ route('register.company.view', $company->invite_code) }}" class="action-button">Modifiér mon équipe</a>
        </div>

        <p>Vous rencontrez un problème avec le bouton ci-dessus ? Copiez et collez l'URL suivante dans votre navigateur
            :<br>
            <strong><a href="{{ route('register.company.view', $company->invite_code) }}">{{ route('register.company.view', $company->invite_code) }}</a>
        </p>

        <p style="font-size: 14px;"><i> Ce lien expirera tant que vous n'aurez pas terminé de soumettre les informations de votre groupe.</i></p>

        <div class="divider"></div>

        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
