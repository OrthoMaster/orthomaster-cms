<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OrthoMaster Questionnaire')</title>

    <link rel="icon" sizes="192x192" href="{{ setting('system.web_icon') }}" type="image/png">
    <link rel="shortcut icon" href="{{ setting('system.web_icon') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Bricolage+Grotesque:opsz,wght@10..48,400..800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <style>
        :root {
            --orange: #FF9600;
            --amber: #FFAF32;
            --ink: #2A1A05;
            --card: #FFFFFF;
            --ok: #36B37E;
            --danger: #E84545;
            --shadow: 0 14px 40px -12px rgba(255, 150, 0, .45);
            --frame: 430px;
            /* largeur max façon mobile */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        html,
        body {
            min-height: 100%
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--ink);
            /* fond du "bureau" autour du cadre mobile */
            background:
                radial-gradient(900px 500px at 80% -10%, #FFD79A 0%, transparent 55%),
                radial-gradient(700px 500px at 0% 110%, #FFC069 0%, transparent 50%),
                linear-gradient(160deg, #FFE9C8 0%, #FFD9A6 100%);
            min-height: 100dvh;
            display: flex;
            align-items: stretch;
            justify-content: center;
        }

        /* Le cadre mobile : limite la largeur même sur ordinateur */
        .frame {
            width: 100%;
            max-width: var(--frame);
            min-height: 100dvh;
            background:
                radial-gradient(800px 500px at 85% -10%, #FFD79A 0%, transparent 55%),
                radial-gradient(700px 460px at 0% 110%, #FFC069 0%, transparent 50%),
                linear-gradient(160deg, #FFF3DC 0%, #FFE7C2 100%);
            box-shadow: 0 0 60px -10px rgba(170, 90, 0, .35);
            display: flex;
            flex-direction: column;
            padding: 22px 18px calc(22px + env(safe-area-inset-bottom));
            position: relative;
            overflow-x: hidden;
        }

        @media(min-width:460px) {
            .frame {
                margin: 0 auto;
                border-radius: 0
            }
        }

        /* ---------- éléments partagés ---------- */
        .screen {
            display: none;
            flex: 1;
            flex-direction: column;
            animation: pop .45s cubic-bezier(.2, .9, .3, 1.25)
        }

        .screen.active {
            display: flex
        }

        @keyframes pop {
            from {
                opacity: 0;
                transform: translateY(18px) scale(.98)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .brandmark {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-weight: 700;
            letter-spacing: .4px;
            font-size: .95rem;
            background: rgba(255, 255, 255, .75);
            padding: 7px 14px;
            border-radius: 99px;
            box-shadow: 0 6px 18px -8px rgba(255, 150, 0, .6);
            backdrop-filter: blur(6px);
        }

        .brandmark .dot {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--amber), var(--orange));
            box-shadow: inset 0 0 0 3px rgba(255, 255, 255, .6)
        }

        .card {
            background: var(--card);
            border-radius: 26px;
            padding: 26px 22px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 6px;
            background: linear-gradient(90deg, var(--amber), var(--orange))
        }

        .btn {
            font-family: inherit;
            font-weight: 600;
            font-size: 1.05rem;
            cursor: pointer;
            border: none;
            color: #fff;
            background: linear-gradient(135deg, var(--amber), var(--orange));
            padding: 16px 24px;
            border-radius: 15px;
            width: 100%;
            box-shadow: 0 10px 22px -8px rgba(255, 150, 0, .7);
            transition: transform .15s, box-shadow .15s;
        }

        .btn:hover {
            transform: translateY(-2px)
        }

        .btn:active {
            transform: translateY(0)
        }

        .btn:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none
        }

        .input {
            width: 100%;
            font-family: inherit;
            font-size: 1.02rem;
            padding: 14px 16px;
            border-radius: 13px;
            border: 2px solid #FFE0B0;
            background: #FFFBF4;
            outline: none;
            color: var(--ink);
            transition: border .15s, box-shadow .15s;
        }

        .input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 4px rgba(255, 150, 0, .16)
        }

        .input::placeholder {
            color: #C9A56B
        }

        @yield('styles')
    </style>
</head>

<body>
    <div class="frame">
        @yield('content')
    </div>
    @yield('scripts')
</body>

</html>
