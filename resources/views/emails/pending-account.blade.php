@extends('emails.template.user-side')

@section('title', 'Votre demande de création de compte sur OrthoMaster est en attente...')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name . ' ' . $user->first_name }},</div>

        <p>Nous vous remercions d’avoir soumis votre demande de création de compte sur notre plateforme.</p>

        <p>Votre dossier est actuellement en cours de vérification par notre équipe. Cette étape est
        nécessaire afin de garantir la conformité de l'accès à nos services.</p>

        <p>Nous vous informerons par mail dès que votre compte sera activé. Nous vous remercions pour votre patience et
        restons à votre disposition pour toute question complémentaire.</p>

        <div class="divider"></div>

        <p>Si vous n'êtes pas à l'origine de ce mail, n'hésitez pas à nous
            contacter.</p>

        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
