@extends('emails.template.user-side')

@section('title', 'Confirmation de votre demande de création de compte !')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name . ' ' . $user->first_name }},</div>

        <p>Nous vous remercions d’avoir soumis votre demande de création de compte sur notre plateforme.</p>

        <p>Veuillez noter que votre dossier est actuellement en cours de vérification par notre équipe. Cette étape est
        nécessaire afin de garantir la conformité et la sécurité de nos services.</p>

        <p>Nous vous informerons dès que votre compte aura été validé et activé. Nous vous remercions de votre patience et
        restons à votre disposition pour toute question complémentaire.</p>

        <div class="divider"></div>

        <p>Si vous n'avez pas demandé la création de ce compte ou si vous avez des questions, n'hésitez pas à nous
            contacter.</p>

        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
