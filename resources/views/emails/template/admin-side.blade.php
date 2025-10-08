<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orthomaster Admin Notification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f5f5f5;
            padding: 40px 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            background-color: white;
            padding: 30px 30px 0;
            text-align: left;
        }

        .header-logo {
            color: #eb7b0f;
            font-size: 16px;
            font-weight: 500;
            height: 30px;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
        }

        .header h1 {
            color: #000;
            font-size: 36px;
            font-weight: 700;
            margin: 0 0 20px 0;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .content {
            padding: 30px;
        }

        .content p {
            color: #555;
            font-size: 15px;
            margin-bottom: 20px;
        }

        .info-box {
            background-color: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 10px 20px;
            margin: 20px 0;
        }

        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            min-width: 150px;
        }

        .info-value {
            color: #666;
            flex: 1;
        }

        .action-button {
            display: inline-block;
            background-color: #eb7b0f;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            margin: 10px 10px 10px 0;
        }

        .footer {
            background-color: #f5f5f5;
            padding: 20px 30px;
            text-align: left;
            border-top: 1px solid #e5e5e5;
        }

        .footer p {
            color: #999;
            font-size: 12px;
            margin: 5px 0;
        }

        .text-success {
            color: #22a7f0 !important;
        }

        .text-danger {
            color: #c7254e !important;
        }

        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 20px 10px;
            }

            .email-container {
                border-radius: 0;
            }

            .header,
            .content,
            .footer {
                padding: 25px 20px;
            }

            .header h1 {
                font-size: 26px;
            }

            .info-row {
                flex-direction: column;
            }

            .info-label {
                margin-bottom: 5px;
            }

            .action-button {
                display: block;
                text-align: center;
                margin: 10px 0;
            }
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            @include('emails.template.header')

            <!-- Content -->
            <div class="content">
                <p>Bonjour l'équipe,</p>

                @yield('content')

                <p><strong>Actions requises :</strong></p>
                <div>
                    <a href="https://cms.orthomaster.fr/admin" class="action-button">Accès à l’espace administrateur</a>
                </div>

                <p style="margin-top: 30px; font-size: 12px; color: #999;">
                    Ceci est un email automatique. Veuillez ne pas y répondre directement.
                </p>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>{{ setting('mail.copyright') }}</p>
            </div>
        </div>
    </div>
</body>

</html>
