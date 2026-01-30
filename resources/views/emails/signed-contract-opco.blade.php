@extends('emails.template.user-side')

@section('title', 'Votre convention OPCO-EP')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>Merci pour votre inscription à la formation ORTHOMASTER.</p>
        <p>Veuillez trouver en pièce jointe les documents que nous nous chargeons de transmettre à l’OPCO-EP pour la prise en charge des formations de votre équipe.</p>
        <p><b>Vous n’avez rien à faire.</b> Nous reviendrons vers vous au besoin.</p>
        <br/>
        <p>Dans <b>1 mois</b> environ vous recevrez une notification de fin de formation. Là encore, nous nous chargerons de déclarer la fin de formation auprès de l’OPCO-EP.</p>

        <div class="divider"></div>
        <p>Une question, un problème ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a><p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
