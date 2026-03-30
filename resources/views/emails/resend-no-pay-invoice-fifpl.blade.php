@extends('emails.template.user-side')

@section('title', 'FIFPL OrthoMaster : Rappel d’étape 2/3')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>Il y a quelque temps vous avez validé votre formation ORTHOMASTER.</p>

        <p>Sauf erreur de notre part, vous n’avez pas réglé notre facture qui vous permet de recevoir la <b>facture acquittée</b> et <b>le certificat de formation</b> à mettre sur votre espace FIFPL et bénéficier du <b>remboursement</b> par votre organisme.</p>

        <p>Procédez rapidement et simplement via ce lien de paiement immédiat 
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
