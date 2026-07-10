<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ouverture d'OrthoMaster…</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', system-ui, sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .content {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .icon-container {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background-color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .icon-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 16px;
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            text-align: center;
            margin-bottom: 15px;
        }

        .description {
            font-size: 16px;
            color: #666;
            text-align: center;
            margin-bottom: 40px;
            line-height: 24px;
            padding: 0 10px;
        }

        .button-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .store-button {
            background-color: #000;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            cursor: pointer;
            display: none;
            /* affiché via JS selon l'appareil */
        }

        .button-content {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
        }

        .store-icon {
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .store-icon svg {
            fill: #fff;
        }

        .button-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-left: 12px;
        }

        .download-text {
            font-size: 12px;
            color: #fff;
            font-weight: 400;
        }

        .store-name {
            font-size: 18px;
            color: #fff;
            font-weight: 700;
        }

        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: #999;
            text-align: center;
            font-weight: 300;
        }
    </style>
</head>

<body>
    <div class="content">
        <div class="icon-container">
            <img src="https://app.orthomaster.fr/assets/assets/v2_images/logo/icon.87ca285584c35afbecabbe849ae1d9b4.png" alt="OrthoMaster">
        </div>

        <h1 class="title">Ouverture d'OrthoMaster …</h1>

        <p class="description">
            Si l'application ne s'ouvre pas automatiquement, téléchargez-la ci-dessous.
        </p>

        <div class="button-container">
            <!-- App Store -->
            <a href="https://apps.apple.com/us/app/orthomaster/id1644851864"
                class="store-button" id="btn-ios">
                <div class="button-content">
                    <span class="store-icon">
                        <svg width="34" height="34" viewBox="0 0 384 512">
                            <path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z" />
                        </svg>
                    </span>
                    <span class="button-text">
                        <span class="download-text">Télécharger sur</span>
                        <span class="store-name">App Store</span>
                    </span>
                </div>
            </a>

            <!-- Google Play -->
            <a href="https://play.google.com/store/apps/details?id=com.hugotaieb.OrthoMaster"
                class="store-button" id="btn-android">
                <div class="button-content">
                    <span class="store-icon">
                        <svg width="28" height="28" viewBox="0 0 512 512">
                            <path d="M325.3 234.3L104.6 13l280.8 161.2-60.1 60.1zM47 0C34 6.8 25.3 19.2 25.3 35.3v441.3c0 16.1 8.7 28.5 21.7 35.3l256.6-256L47 0zm425.2 225.6l-58.9-34.1-65.7 64.5 65.7 64.5 60.1-34.1c18-14.3 18-46.5-1.2-60.8zM104.6 499l220.7-221.3 60.1 60.1L104.6 499z" />
                        </svg>
                    </span>
                    <span class="button-text">
                        <span class="download-text">Télécharger sur</span>
                        <span class="store-name">Google Play</span>
                    </span>
                </div>
            </a>
        </div>

        <p class="footer">
            Version web disponible uniquement sur écran plus large
        </p>
    </div>

    <script>
        (function() {
            var suffix = @json($suffix);

            var appUrl = "orthomaster://open/" + suffix;
            var webFallback = "https://app.orthomaster.fr/open/" + suffix;

            // Détection de l'appareil (même logique que l'écran RN)
            var ua = navigator.userAgent || navigator.vendor || window.opera;
            var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
            var isAndroid = /android/i.test(ua);

            // Afficher les boutons store selon l'appareil
            if (isIOS) {
                document.getElementById('btn-ios').style.display = 'block';
            } else if (isAndroid) {
                document.getElementById('btn-android').style.display = 'block';
            } else {
                // Desktop : montrer les deux et rediriger vers le web
                document.getElementById('btn-ios').style.display = 'block';
                document.getElementById('btn-android').style.display = 'block';
                window.location = webFallback;
                return;
            }

            // Tenter d'ouvrir l'app (mobile uniquement)
            var didHide = false;
            document.addEventListener("visibilitychange", function() {
                if (document.hidden) didHide = true; // l'app s'est ouverte
            });

            window.location = appUrl;

            // App non installée → fallback web après 1.5s
            setTimeout(function() {
                if (!didHide) {
                    window.location = webFallback;
                }
            }, 1500);
        })();
    </script>
</body>

</html>