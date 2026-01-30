@extends('emails.template.user-side')

@section('title', 'Votre convention de formation FIFPL')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>Merci pour votre inscription à la formation ORTHOMASTER.</p>
        <p>Voici les prochaines étapes pour obtenir votre prise en charge auprès du FIFPL :</p>

        <p>1. Prenez connaissance du <b>Tutoriel</b> en <a href="https://drive.google.com/file/d/1Ie4NXHRiu2dweye7FgdHxQwks-BGt7MP/view">lien ici</a> pour accompagner votre démarche</p>
        <p>2. Rendez-vous le plus rapidement possible sur votre <b>Espace FIFPL</b> (<a href="https://www.fifpl.fr">lien ici</a>) pour déclarer votre action. Vous aurez
        besoin des 2 documents ci-joints : <i>Devis signé et programme de formation</i>.</p>
        <p>3. Dans 2 mois vous recevrez une <b>Facture</b> (correspondant au devis) avec un lien de paiement intégré. Une fois
        acquittée, vous recevrez deux documents à déposer sur votre espace FIFPL (cf. Tutoriel) afin de finaliser votre
        prise en charge :</p>
        <p style="padding-left: 40px;">
            • Facture acquittée<br/>
            • Certificat de formation
        </p>

        <p>4. Vous serez remboursé sous 15 jours environ (délai moyen communiqué par le FIFPL)</p>

        <div class="divider"></div>
        <p>Une question, un problème ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a><p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
