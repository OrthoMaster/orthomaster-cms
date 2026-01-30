@extends('emails.template.user-side')

@section('title', 'Votre convention de formation')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>Merci pour votre inscription à la formation ORTHOMASTER.</p>
        <p>Veuillez trouver en pièce jointe les documents pour la prise en charge des formations de votre équipe.</p>
        <br />
        <p>Merci de nous contacter pour valider votre dossier, dans les meilleurs délais.</p>

        <div class="divider"></div>
        <p><a href="https://www.orthomaster.fr/contact">Contactez-nous</a><p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
