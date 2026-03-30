@extends('emails.template.user-side')

@section('title', 'FIFPL OrthoMaster : Étape finale')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>Suite au règlement de votre formation, veuillez trouver ci-joint la <b>facture acquittée</b> et <b>le certificat de formation</b> à mettre sur votre espace FIFPL pour bénéficier du <b>remboursement</b> par votre organisme.</p>
        <p>Dès que c’est fait, tout est bon.</p>
        <p>Et bien sûr, on est là pour vous accompagner dans de nouvelles formations à venir !</p>

        <div class="divider"></div>
        <p>Une question, un problème ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a><p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
