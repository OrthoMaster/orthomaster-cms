<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | Orthomaster</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@300;400;500&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#eb7b0f',
                    },
                    fontFamily: {
                        heading: ['Poppins', 'sans-serif'],
                        body: ['Roboto', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    @yield('css')
</head>

<body class="bg-gray-50 flex flex-col min-h-screen font-body">
    <!-- Top Menu -->
    @include('landingpage.template.header')

    <!-- Main Content -->
    @yield('content')

    <!-- Footer -->
    @include('landingpage.template.footer')

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
        });

        // Validation
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');

            const inputId = elementId.replace('-error', '');
            const inputElement = document.getElementById(inputId);

            // Radio button
            const isRadio = inputElement.type === 'radio';
            if (isRadio) {
                const radioButtons = document.querySelectorAll(`input[name="${inputElement.name}"]`);
                radioButtons.forEach(radio => {
                    radio.addEventListener('change', () => {
                        hideError(elementId);
                    }, {
                        once: true
                    });
                });
            }

            // Text input            
            if (inputElement) {
                inputElement.classList.add('border-red-500', 'focus:ring-red-500');
                inputElement.classList.remove('border-gray-300', 'focus:ring-primary');

                // Add event listener clear erro when focus
                inputElement.addEventListener('focus', function handler() {
                    hideError(elementId);
                    inputElement.removeEventListener('focus', handler);
                }, {
                    once: true
                });
            }
        }

        function hideError(elementId) {
            const errorElement = document.getElementById(elementId);
            errorElement.classList.add('hidden');

            const inputId = elementId.replace('-error', '');
            const inputElement = document.getElementById(inputId);
            if (inputElement) {
                inputElement.classList.remove('border-red-500', 'focus:ring-red-500');
                inputElement.classList.add('border-gray-300', 'focus:ring-primary');
            }
        }
    </script>

    @yield('js')
</body>

</html>
