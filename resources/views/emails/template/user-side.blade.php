<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrthoMaster | System Email</title>
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
            line-height: 1.2;
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
            overflow: hidden;
            border: 1px solid #ddd;
        }

        .header {
            background-color: white;
            padding: 30px 30px 0;
            text-align: left;
        }

        .header-logo {
            color: #eb7b0f;
            font-size: 20px;
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
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .content p {
            color: #555;
            font-size: 15px;
            margin-bottom: 16px;
            line-height: 1.7;
        }

        .credentials-box {
            background: linear-gradient(135deg, #fff5eb 0%, #ffe8cc 100%);
            border-left: 4px solid #eb7b0f;
            padding: 25px;
            margin: 25px 0;
            border-radius: 8px;
        }

        .credential-row {
            margin: 15px 0;
        }

        .credential-label {
            font-weight: 600;
            color: #eb7b0f;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }

        .credential-value {
            font-size: 16px;
            color: #333;
            background-color: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ffd699;
            display: inline-block;
            letter-spacing: 1px;
        }

        .credential-value a {
            text-decoration: none !important;
            color: inherit !important;
            pointer-events: none !important;
        }

        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin: 25px 0;
        }

        .warning-box p {
            color: #856404;
            margin: 0;
            font-size: 12px;
        }

        .warning-icon {
            display: inline-block;
            font-weight: bold;
            margin-right: 8px;
            color: #ffc107;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #eb7b0f 0%, #ffaf31 100%);
            color: white;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 25px 0;
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(235, 123, 15, 0.4);
        }

        .cta-container {
            text-align: center;
        }

        .steps-list {
            margin: 25px 0;
            padding-left: 0;
        }

        .step-item {
            list-style: none;
            padding: 12px 0;
            padding-left: 35px;
            position: relative;
            color: #555;
        }

        .step-number {
            position: absolute;
            left: 0;
            top: 12px;
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #eb7b0f 0%, #ffaf31 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd, transparent);
            margin: 30px 0;
        }

        .footer {
            background-color: #f5f5f5;
            padding: 30px;
            text-align: left;
        }

        .footer-logo {
            color: #eb7b0f;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .app-buttons {
            margin: 20px 0;
        }

        .app-button {
            display: inline-block;
            padding: 8px 16px;
            margin-left: 8px;
            margin-bottom: 8px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 20px;
            color: #666;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        .footer-note {
            color: #999;
            font-size: 12px;
            line-height: 1.6;
            margin: 20px 0;
        }

        .footer-note a {
            color: #eb7b0f;
            text-decoration: underline;
        }

        .footer-links {
            margin: 20px 0;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .footer-links a {
            color: #666;
            text-decoration: none;
            margin-right: 20px;
            font-size: 12px;
            white-space: nowrap;
        }

        .footer-links a:hover {
            color: #eb7b0f;
        }

        .footer-address {
            color: #999;
            font-size: 11px;
            margin-top: 15px;
            line-height: 1.6;
        }

        .download-buttons {
            margin: 20px 0;
            justify-items: center;
            text-align: center;
        }

        .download-button {
            display: inline-flex;
            padding: 12px 24px;
            margin: 8px 6px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            min-width: 130px;
            line-height: 24px;
            text-align: center;
        }

        .download-button:hover {
            border-color: #eb7b0f;
            background-color: #fff9f5;
            color: #333;
        }

        .download-button svg {
            width: 24px;
            height: 24px;
            margin-right: 5px;
        }

        .download-button img {
            height: 24px;
            width: auto;
            margin-right: 7px;
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

        .sub-version-web {
            font-size: 12px;
            color: #666;

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

            .credential-value {
                font-size: 14px;
                word-break: break-all;
            }

            .credential-value {
                font-size: 14px;
                word-break: break-all;
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
            @yield('content')

            <!-- Footer -->
            @include('emails.template.footer')
        </div>
    </div>
</body>

</html>
