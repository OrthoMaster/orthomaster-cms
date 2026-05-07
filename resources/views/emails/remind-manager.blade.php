@extends('emails.template.user-side')

@section('title', '🚀 Activez la montée en compétences de votre équipe en 2 minutes')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name . ' ' . ($user->first_name ?? '') }},</div>
        
        <p>Votre espace professionnel OrthoMaster est créé 👍<br/>
        Il ne vous reste plus qu’une étape pour débloquer tout son potentiel : activer les micro-formations pour votre équipe.</p>

        <p>🎯 L’objectif : développer le service et le conseil autour de la mobilité en officine tout en renforçant rapidement les compétences de vos collaborateurs.</p>

        <p style="margin-bottom: 5px;">Avec OrthoMaster :</p>
        <p style="padding-left: 20px;">
            ✅ Micro-formations courtes façon “Duolingo”<br/>
            ✅ 100 % financées par France Compétences<br/>
            ✅ Accessibles à vie<br/>
            ✅ Réalisables quand ils veulent, dans l’ordre qu’ils veulent<br/>
            ✅ Certificat de formation délivré<br/>
            ✅ Suivi des progrès de toute l’équipe depuis votre espace titulaire<br/>
        </p>
        
        <p>⚡ Tout se fait instantanément depuis l’application.</p>

        <p>🧾 Et surtout : aucune gestion administrative pour vous.</br>
        L’OPCO-EP nous règle directement et OrthoMaster est certifié tiers déclarant.</p>
        
        <br/>
        <p>👉 En 2 minutes, vous pouvez activer l’accès et faire monter toute votre équipe en compétences :</p>
        <div>
            <a href="https://app.orthomaster.fr/open/company" class="action-button">Mon Équipe</a>
        </div>
        <br />

        <div class="divider"></div>

        <p style="margin-top: 30px;">À très vite sur OrthoMaster,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
