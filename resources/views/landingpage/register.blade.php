<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inscription | Orthomaster</title>
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
    <style>
        .iti {
            width: 100%;
        }

        .iti__selected-flag {
            padding: 0 10px 0 15px;
        }

        .iti--allow-dropdown input[type=tel] {
            padding-left: 60px;
        }
    </style>
</head>

<body class="bg-gray-50 flex flex-col min-h-screen font-body">
    <!-- Top Menu -->
    <header class="bg-white shadow-sm">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo and Brand -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                            </path>
                        </svg>
                    </div>
                    <span class="text-xl font-semibold text-gray-800 font-heading">Orthomaster</span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="https://orthomaster.fr" class="text-gray-600 hover:text-primary transition-colors">Notre
                        site</a>
                    <a href="javascript:void(0)" class="text-primary font-medium">S'inscrire</a>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div id="mobile-menu" class="hidden md:hidden py-4">
                <div class="flex flex-col space-y-3 text-right">
                    <a href="https://orthomaster.fr"
                        class="text-gray-600 hover:text-primary transition-colors">Notre site</a>
                    <a href="javascript:void(0);" class="text-primary font-medium">S'inscrire</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="flex-grow py-8 px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-3xl font-semibold text-gray-800 mb-0 font-heading">Ouverture de compte</h1>
                <p class="text-md text-gray-400">Merci de suivre les étapes ci-dessous</p>
            </div>

            <form id="register-form" method="post" action="{{ route('register.submit') }}" enctype="multipart/form-data">
                @csrf

                <!-- Progress Steps -->
                <div class="mb-8">
                    <div class="flex items-center justify-between relative">
                        <!-- Step 1 -->
                        <div class="flex flex-col items-center relative z-10 flex-1">
                            <div id="step1-circle"
                                class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-semibold mb-2 transition-all duration-300">
                                1
                            </div>
                            <span id="step1-text" class="text-sm font-medium text-primary text-center">Email</span>
                        </div>

                        <!-- Line 1-2 -->
                        <div class="flex-1 h-1 bg-gray-300 relative -mx-2" style="margin-top: -28px;">
                            <div id="progress-line-1" class="h-full bg-primary transition-all duration-300"
                                style="width: 0%"></div>
                        </div>

                        <!-- Step 2 -->
                        <div class="flex flex-col items-center relative z-10 flex-1">
                            <div id="step2-circle"
                                class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-semibold mb-2 transition-all duration-300">
                                2
                            </div>
                            <span id="step2-text" class="text-sm font-medium text-gray-500 text-center">Infos
                                personnelles</span>
                        </div>

                        <!-- Line 2-3 -->
                        <div class="flex-1 h-1 bg-gray-300 relative -mx-2" style="margin-top: -28px;">
                            <div id="progress-line-2" class="h-full bg-primary transition-all duration-300"
                                style="width: 0%"></div>
                        </div>

                        <!-- Step 3 -->
                        <div class="flex flex-col items-center relative z-10 flex-1">
                            <div id="step3-circle"
                                class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-semibold mb-2 transition-all duration-300">
                                3
                            </div>
                            <span id="step3-text"
                                class="text-sm font-medium text-gray-500 text-center">Profession</span>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                    <!-- Step 1: Email -->
                    <div id="step1-content" class="step-content">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6 font-heading">Étape 1 : Saisir l'email</h2>

                        <div class="space-y-4">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                    placeholder="votre-email@email.com" autofocus required>
                                <p id="email-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                <p id="email-success" class="mt-2 text-sm text-green-600 hidden"></p>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end">
                            <button onclick="nextStep(1)" type="button"
                                class="px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                Suivant
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Personal Info -->
                    <div id="step2-content" class="step-content hidden">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6 font-heading">Étape 2: Informations
                            personnelles
                        </h2>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nom <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="name" name="name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre nom" required>
                                </div>
                                <div>
                                    <label for="first-name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Prénom <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="first-name" name="first_name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre prénom" required>
                                </div>
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Votre numéro de téléphone
                                </label>
                                <input type="tel" id="phone" name="phone"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
                            </div>


                        </div>

                        <div class="mt-8 flex justify-between">
                            <button onclick="prevStep(2)" type="button"
                                class="px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                                Précédent
                            </button>
                            <button onclick="nextStep(2)" type="button"
                                class="px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                Suivant
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Occupation -->
                    <div id="step3-content" class="step-content hidden">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6 font-heading">Étape 3 : Profession</h2>

                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-4">
                                    Vous êtes <span class="text-red-500">*</span>
                                </label>
                                <div class="space-y-3">
                                    <label
                                        class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-primary transition-all peer-checked:border-primary">
                                        <input type="radio" name="occupation" value="professor"
                                            class="peer w-5 h-5 text-primary focus:ring-primary"
                                            onchange="toggleOccupationFields('professor')">
                                        <span
                                            class="ml-3 text-gray-700 font-medium peer-checked:text-primary">Professionnel(le)
                                            de santé</span>
                                    </label>

                                    <label
                                        class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-primary transition-all peer-checked:border-primary">
                                        <input type="radio" name="occupation" value="student"
                                            class="peer w-5 h-5 text-primary focus:ring-primary"
                                            onchange="toggleOccupationFields('student')">
                                        <span
                                            class="ml-3 text-gray-700 font-medium peer-checked:text-primary">Étudiant(e)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Professor Field -->
                            <div id="professor-field" class="hidden space-y-4">
                                <!-- Establishment -->
                                <div class="mb-4">
                                    <label for="estab" class="block text-sm font-medium text-gray-700 mb-2">
                                        Type d'établissement <span class="text-red-500">*</span>
                                    </label>
                                    <select id="estab" name="estab"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white"
                                        onchange="toggleOtherEstab()">
                                        <option value="" disabled selected>-- Veuillez sélectionner --</option>
                                        <option value="oral_surgery">Cabinet</option>
                                        <option value="pediatric_dentistry">Hospital</option>
                                        <option value="other">Autre</option>
                                    </select>
                                </div>
                                <div id="other-estab-field" class="hidden">
                                    <input type="text" id="other-estab" name="other_estab"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre type d'établissement">
                                </div>

                                <!-- Job title -->
                                <div>
                                    <label for="job" class="block text-sm font-medium text-gray-700 mb-2">
                                        Fonction professionelle <span class="text-red-500">*</span>
                                    </label>
                                    <select id="job" name="job"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white"
                                        onchange="toggleOtherJob()">
                                        <option value="" disabled selected>-- Veuillez sélectionner --</option>
                                        <option value="oral_surgery">Chirurgien</option>
                                        <option value="pediatric_dentistry">Ergo</option>
                                        <option value="other">Autre</option>
                                    </select>
                                </div>
                                <div id="other-job-field" class="hidden">
                                    <input type="text" id="other-job" name="other_job"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre fonction professionelle">
                                </div>

                                <!-- RPPS -->
                                <div>
                                    <label for="rpps" class="block text-sm font-medium text-gray-700 mb-2">
                                        Numéro professionnel <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex gap-2">
                                        <select id="rpps-type" name="rpps_type"
                                            class="w-48 px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white">
                                            <option value="rpps" selected>RPPS</option>
                                            <option value="adeli">ADELI</option>
                                            <option value="inami">INAMI</option>
                                            <option value="fph">FPH</option>
                                        </select>
                                        <input type="text" id="rpps" name="rpps"
                                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                            placeholder="N° professionnel">
                                    </div>
                                </div>

                            </div>

                            <!-- Student Field -->
                            <div id="student-field" class="hidden space-y-4">
                                <div>
                                    <label for="major" class="block text-sm font-medium text-gray-700 mb-2">
                                        Étudiant en <span class="text-red-500">*</span>
                                    </label>
                                    <select id="major" name="major"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white"
                                        onchange="toggleOtherMajor()">
                                        <option value="" disabled selected>-- Veuillez sélectionner --</option>
                                        <option value="oral_surgery">Chirurgien</option>
                                        <option value="pediatric_dentistry">Ergo</option>
                                        <option value="other">Autre</option>
                                    </select>
                                </div>
                                <div id="other-major-field" class="hidden">
                                    <input type="text" id="other-major" name="other_major"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre spécialisé">
                                </div>

                                <!-- Certificate -->
                                <label for="enrollment-certificate"
                                    class="block text-sm font-medium text-gray-700 mb-2">
                                    Certificat de scolarité <span class="text-red-500">*</span>
                                </label>
                                <div class="mt-2">
                                    <label
                                        class="flex flex-col items-center px-4 py-6 bg-white border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary transition-all">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                                            </path>
                                        </svg>
                                        <span class="mt-2 text-sm text-gray-600">Cliquez pour télécharger le
                                            certificat</span>
                                        <span class="mt-1 text-xs text-gray-500">PDF, JPG, PNG (max 5Mo)</span>
                                        <input type="file" id="enrollment-certificate"
                                            name="enrollment_certificate" class="hidden"
                                            accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                                    </label>
                                    <p id="file-name" class="mt-2 text-sm text-gray-600 hidden"></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <button onclick="prevStep(3)" type="button"
                                class="px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                                Précédent
                            </button>
                            <button onclick="submitForm()" type="button"
                                class="px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                Soumettre
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Success Modal -->
            <div id="success-modal"
                class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                            <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Demande envoyée avec succès !
                        </h3>
                        <p class="text-gray-600 mb-6">Merci d’avoir soumis votre demande d’inscription. Nous
                            vérifierons vos informations et créerons votre compte dans les plus brefs délais.</p>
                        <button onclick="closeModal()"
                            class="w-full px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>

    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- About Section -->
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                </path>
                            </svg>
                        </div>
                        <span class="text-lg font-semibold text-white font-heading">Orthomaster</span>
                    </div>
                    <p class="text-sm text-gray-400">
                        Plateforme digitale dédiée aux professionnels de santé, spécialisés en orthopédie.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-white font-semibold mb-4 font-heading">Liens</h3>
                    <ul class="space-y-2">
                        <li><a target="_blank" href="https://orthomaster.fr"
                                class="text-sm hover:text-primary transition-colors">À propos de nous</a>
                        </li>
                        <li><a target="_blank" href="https://www.orthomaster.fr/mentions-l%C3%A9gales"
                                class="text-sm hover:text-primary transition-colors">Mentions légales</a></li>
                        <li><a target="_blank" href="https://www.orthomaster.fr/politique-de-confidentialit%C3%A9"
                                class="text-sm hover:text-primary transition-colors">Politique de confidentialité</a>
                        </li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h3 class="text-white font-semibold mb-4 font-heading">Contactez-nous</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-top space-x-2">
                            <svg class="w-4 h-4 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>10 rue Saint Florent<br />67200 Strasbourg, France</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            <a href="mailto:info@orthomaster.fr">info@orthomaster.fr</a>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                </path>
                            </svg>
                            <a href="tel:+33768250616">+33 7 68 25 06 16</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-700 mt-8 pt-6 text-center">
                <p class="text-sm text-gray-400">&copy; 2021 par ORTHOMASTER ®</p>
            </div>
        </div>
    </footer>

    <script>
        let currentStep = 1;

        // Mobile menu toggle
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
        });

        // Phone input
        const phoneInput = document.querySelector("#phone");
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "fr",
            preferredCountries: ["fr", "de", "ch", "be"],
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js"
        });

        function nextStep(step) {
            if (step === 1) {
                const email = document.getElementById('email').value;
                const emailInput = document.getElementById('email');
                const nextButton = document.querySelector('#step1-content button');

                if (!email || !validateEmail(email)) {
                    showError('email-error', "Veuillez saisir une adresse email valide !");
                    return;
                }
                hideError('email-error');

                /* Check email with database */
                // Disable input và button
                emailInput.disabled = true;
                nextButton.disabled = true;
                nextButton.innerHTML =
                    '<span class="flex items-center justify-center"><svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Vérification en cours ...</span>';

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 seconds timeout

                // AJAX check email
                fetch("{{ route('register.email.check') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            email: email
                        }),
                        signal: controller.signal
                    })
                    .then(response => {
                        clearTimeout(timeoutId);

                        // Xử lý theo HTTP status code
                        if (response.status === 200) {
                            return response.json();
                        } else if (response.status === 400) {
                            throw new Error('BAD_REQUEST');
                        } else if (response.status === 404) {
                            throw new Error('NOT_FOUND');
                        } else if (response.status === 422) {
                            throw new Error('VALIDATION_ERROR');
                        } else if (response.status === 500) {
                            throw new Error('SERVER_ERROR');
                        } else if (response.status === 503) {
                            throw new Error('SERVICE_UNAVAILABLE');
                        } else {
                            throw new Error('UNKNOWN_ERROR');
                        }
                    })
                    .then(data => {
                        // Enable lại input và button
                        emailInput.disabled = false;
                        nextButton.disabled = false;
                        nextButton.innerHTML = 'Suivant';

                        if (!data.success) {
                            showError('email-error', data.message);
                        } else {
                            hideError('email-error');
                            // Move to step 2
                            document.getElementById('step1-content').classList.add('hidden');
                            currentStep = 2;
                            document.getElementById('step2-content').classList.remove('hidden');
                            updateProgress(currentStep);

                            setTimeout(() => {
                                document.getElementById('name').focus();
                            }, 100);
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);

                        // Enable lại
                        emailInput.disabled = false;
                        nextButton.disabled = false;
                        nextButton.innerHTML = 'Suivant';

                        // Errors
                        let errorMessage = '';

                        if (error.name === 'AbortError') {
                            errorMessage = 'La requête a expiré. Veuillez vérifier votre connexion et réessayer';
                        } else if (!navigator.onLine) {
                            errorMessage = 'Pas de connexion Internet. Veuillez vérifier et réessayer';
                        } else {
                            switch (error.message) {
                                case 'BAD_REQUEST':
                                    errorMessage = 'Requête invalide';
                                    break;
                                case 'NOT_FOUND':
                                    errorMessage = 'Service introuvable. Veuillez contacter l\'administrateur';
                                    break;
                                case 'VALIDATION_ERROR':
                                    errorMessage = 'Données invalides';
                                    break;
                                case 'SERVER_ERROR':
                                    errorMessage = 'Erreur serveur. Veuillez réessayer plus tard';
                                    break;
                                case 'SERVICE_UNAVAILABLE':
                                    errorMessage = 'Service temporairement indisponible. Veuillez réessayer plus tard';
                                    break;
                                default:
                                    errorMessage = 'Une erreur s\'est produite. Veuillez réessayer plus tard';
                            }
                        }

                        showError('email-error', errorMessage);
                        console.error('Error:', error);
                    });

                return;
            } else if (step === 2) {
                const name = document.getElementById('name').value;
                const firstName = document.getElementById('first-name').value;

                if (!firstName || !name) {
                    alert('Empty');
                    return;
                }
            }

            document.getElementById(`step${step}-content`).classList.add('hidden');
            currentStep = step + 1;
            document.getElementById(`step${currentStep}-content`).classList.remove('hidden');
            updateProgress(currentStep);

            // Set forcus
            if (currentStep === 1) {
                setTimeout(() => {
                    document.getElementById('email').focus();
                }, 100);
            } else if (currentStep === 2) {
                setTimeout(() => {
                    document.getElementById('name').focus();
                }, 100);
            }
        }

        function prevStep(step) {
            document.getElementById(`step${step}-content`).classList.add('hidden');
            currentStep = step - 1;
            document.getElementById(`step${currentStep}-content`).classList.remove('hidden');
            updateProgress(currentStep);
        }

        function updateProgress(step) {
            for (let i = 1; i <= 3; i++) {
                const circle = document.getElementById(`step${i}-circle`);
                const text = document.getElementById(`step${i}-text`);

                if (i < step) {
                    circle.classList.remove('bg-gray-300', 'text-gray-600');
                    circle.classList.add('bg-primary', 'text-white');
                    text.classList.remove('text-gray-500');
                    text.classList.add('text-primary');
                } else if (i === step) {
                    circle.classList.remove('bg-gray-300', 'text-gray-600');
                    circle.classList.add('bg-primary', 'text-white');
                    text.classList.remove('text-gray-500');
                    text.classList.add('text-primary');
                } else {
                    circle.classList.remove('bg-primary', 'text-white');
                    circle.classList.add('bg-gray-300', 'text-gray-600');
                    text.classList.remove('text-primary');
                    text.classList.add('text-gray-500');
                }
            }

            document.getElementById('progress-line-1').style.width = step >= 2 ? '100%' : '0%';
            document.getElementById('progress-line-2').style.width = step >= 3 ? '100%' : '0%';
        }

        // Toggle occupation
        function toggleOccupationFields(type) {
            const professorField = document.getElementById('professor-field');
            const studentField = document.getElementById('student-field');

            if (type === 'professor') {
                professorField.classList.remove('hidden');
                studentField.classList.add('hidden');
            } else {
                professorField.classList.add('hidden');
                studentField.classList.remove('hidden');
            }
        }

        // Toggle establishment
        function toggleOtherEstab() {
            const estabSelect = document.getElementById('estab');
            const otherEstabField = document.getElementById('other-estab-field');
            const otherEstabInput = document.getElementById('other-estab');

            if (estabSelect.value === 'other') {
                otherEstabField.classList.remove('hidden');
                // Focus input
                setTimeout(() => {
                    otherEstabInput.focus();
                }, 100);
            } else {
                otherEstabField.classList.add('hidden');
                otherEstabInput.value = '';
            }
        }

        // Toggle job title
        function toggleOtherJob() {
            const jobSelect = document.getElementById('job');
            const otherJobField = document.getElementById('other-job-field');
            const otherJobInput = document.getElementById('other-job');

            if (jobSelect.value === 'other') {
                otherJobField.classList.remove('hidden');
                // Focus input
                setTimeout(() => {
                    otherJobInput.focus();
                }, 100);
            } else {
                otherJobField.classList.add('hidden');
                otherJobInput.value = '';
            }
        }

        // Toggle major
        function toggleOtherMajor() {
            const majorSelect = document.getElementById('major');
            const otherMajorField = document.getElementById('other-major-field');
            const otherMajorInput = document.getElementById('other-major');

            if (majorSelect.value === 'other') {
                otherMajorField.classList.remove('hidden');
                // Focus input
                setTimeout(() => {
                    otherMajorInput.focus();
                }, 100);
            } else {
                otherMajorField.classList.add('hidden');
                otherMajorInput.value = '';
            }
        }

        // Upload file
        function updateFileName(input) {
            const fileName = document.getElementById('file-name');
            if (input.files && input.files[0]) {
                fileName.textContent = `Fichier: ${input.files[0].name}`;
                fileName.classList.remove('hidden');
            }
        }

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

        function submitForm() {
            const occupation = document.querySelector('input[name="occupation"]:checked');

            if (!occupation) {
                alert("Veuillez sélectionner une profession");
                return;
            }

            if (occupation.value === 'professor') {
                const estab = document.getElementById('estab').value;
                const rpps = document.getElementById('rpps').value;

                if (!estab) {
                    alert("Veuillez sélectionner une type d'établissement");
                    return;
                }

                if (estab === 'other') {
                    const estabSpecialty = document.getElementById('other-estab').value;
                    if (!estabSpecialty) {
                        alert("Veuillez saisir votre type d'établissement");
                        return;
                    }
                }

                if (!rpps) {
                    alert('RPPS');
                    return;
                }
            } else if (occupation.value === 'student') {
                const certificate = document.getElementById('enrollment-certificate').files[0];
                if (!certificate) {
                    alert('Certification');
                    return;
                }
            }

            document.getElementById('register-form').submit();

            document.getElementById('success-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('success-modal').classList.add('hidden');
        }

        // Add Enter to move step
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('step1-content').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextStep(1);
                }
            });

            document.getElementById('step2-content').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextStep(2);
                }
            });

            document.getElementById('step3-content').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitForm();
                }
            });
        });
    </script>
</body>

</html>
