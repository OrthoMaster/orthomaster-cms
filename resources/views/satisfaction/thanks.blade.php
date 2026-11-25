<!doctype html>
<html lang="fr">
<head>
    @include('satisfaction._head')
    <title>Merci pour votre avis</title>
    <style>
        .thanks { text-align: center; padding: 36px 24px; }
        .thanks .icon { width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 999px; background: #FF9600; color: #fff; font-size: 38px; line-height: 72px; font-weight: 700; }
        .thanks p { margin: 0 0 8px; color: #5A6170; }
    </style>
</head>
<body>
<main>
    @include('congres._brand')
    <div class="card thanks">
        <div class="icon">✓</div>
        <h1>Merci pour votre avis !</h1>
        <p>Vos réponses ont bien été enregistrées.</p>
        <p>Elles nous aident à améliorer nos formations.</p>
    </div>
</main>
</body>
</html>
