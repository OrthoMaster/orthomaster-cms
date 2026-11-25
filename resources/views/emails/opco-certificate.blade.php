@extends('emails.template.user-side')

@section('title', 'OrthoMaster : votre certificat de réalisation')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ trim($learner->first_name . ' ' . $learner->name) }},</div>
        <p>Félicitations, votre formation OrthoMaster est terminée !</p>
        <p>Vous trouverez ci-joint votre <b>certificat de réalisation</b>.</p>
        <p>Conservez-le précieusement : il atteste de la formation que vous avez suivie.</p>
        <p>Vous pouvez utiliser cette attestation à toutes fins utiles (DPC, valorisation de vos compétences…).</p>
        <p>À très vite sur OrthoMaster pour une nouvelle formation ou simplement pour vous tenir informé(e) de nos dernières actualités !</p>

        <div class="divider"></div>
        <p>Une question, un problème ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a><p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
