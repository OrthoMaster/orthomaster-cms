<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Enregistré</title>
</head>
<body>
    <script>
        // La fenêtre parente referme la boîte et recharge la liste
        if (window.parent !== window) {
            window.parent.postMessage({ source: 'linked-content', saved: true }, '*');
        } else {
            window.close();
        }
    </script>
</body>
</html>