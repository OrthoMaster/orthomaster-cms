@extends('emails.template.user-side')

@section('title', 'FIFPL OrthoMaster : Étape 2/3')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>Il y a un mois vous avez validé votre formation ORTHOMASTER.</p>

        <p>Voici donc la facture à régler pour recevoir la <b>facture acquittée</b> et <b>le certificat de formation</b> à mettre sur votre espace FIFPL et bénéficier du <b>remboursement</b> par votre organisme.</p>

        <p>Procédez très simplement via ce lien de paiement immédiat 
            :<br>
            <strong><a href="{{ 'https://app.orthomaster.fr/open/invoice/' . $invoice->id }}">{{ 'https://app.orthomaster.fr/open/invoice/' . $invoice->id }}</a>
        </p>
        
        <div class="divider"></div>
        <p>Une question, un problème ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a>
        <p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
