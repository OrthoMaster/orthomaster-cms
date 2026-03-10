@extends('landingpage.template.index')

@section('title', 'Inscription')

@section('content')
    <main class="flex-grow py-8 px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-3xl font-semibold text-gray-800 mb-0 font-heading">Ouverture de compte</h1>
                <p class="text-md text-gray-400">Merci de suivre les étapes ci-dessous</p>
            </div>

            <form id="register-form" method="post" action="{{ route('register.submit') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="inapp" value="{{ request('inapp') ? 'APP' : 'WEB' }}" />
                <input type="hidden" name="join_code" value="{{ request('join_code') }}" />

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
                            <span id="step2-text" class="text-sm font-medium text-gray-500 text-center">Infos</span>
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
                            <span id="step3-text" class="text-sm font-medium text-gray-500 text-center">Profession</span>
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
                                <label for="email" class="block text-sm text-gray-500 mb-2">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" maxlength="150"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                    placeholder="votre-email@email.com" autofocus required>
                                <p id="email-error" class="mt-2 text-sm text-red-600 hidden"></p>
                            </div>

                            <!-- Invitation link -->
                            <div>
                                <div class="w-full md:w-[25ch]">
                                    <div id="invite-container" class="mt-3 hidden">
                                        <div class="flex justify-between items-center mb-2">
                                            <label for="invite-code" class="block text-sm text-sm text-gray-500">
                                                Code d’invitation
                                            </label>
                                            <p class="text-xs text-primary hover:underline cursor-pointerregister"
                                                onclick="toggleInviteInput()">
                                                Supprimer
                                            </p>
                                        </div>

                                        <input type="text" id="invite-code" name="invite_code" maxlength="15"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                            placeholder="ABC123XYZ" value="{{ $invCode }}"
                                            oninput="formatInviteCode(this)">
                                        <p id="invite-code-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                    </div>
                                </div>
                                <p id="toggle-invite"
                                    class="mt-2 text-sm text-primary cursor-pointer hover:underline block text-right"
                                    onclick="toggleInviteInput()">
                                    J'ai un code d’invitation !
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
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
                                    <label for="name" class="block text-sm text-gray-500 mb-2">
                                        Nom <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="name" name="name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre nom" maxlength="100" required>
                                    <p id="name-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                                <div>
                                    <label for="first-name" class="block text-sm text-gray-500 mb-2">
                                        Prénom <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="first-name" name="first_name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre prénom" maxlength="100" required>
                                    <p id="first-name-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                            </div>

                            <div>
                                <label for="phone" class="block text-sm text-gray-500 mb-2">
                                    Votre numéro de téléphone
                                </label>
                                <input type="tel" id="phone" name="phone" maxlength="50"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
                                <p id="phone-error" class="mt-2 text-sm text-red-600 hidden"></p>
                            </div>

                            <div>
                                <label for="country" class="block text-sm text-gray-500 mb-2">
                                    Pays
                                </label>
                                <select id="country" name="country"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white">
                                    @foreach ($countries as $code => $country)
                                        <option value="{{ $code }}" @selected('FR' == $code)>
                                            {{ $country }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="postal-code" class="block text-sm text-gray-500 mb-2">
                                        Code postal
                                    </label>
                                    <input type="text" id="postal-code" name="postal_code"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Code postal" maxlength="10">
                                </div>
                                <div>
                                    <label for="city" class="block text-sm text-gray-500 mb-2">
                                        Ville
                                    </label>
                                    <input type="text" id="city" name="city"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Ville" maxlength="30">
                                </div>
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
                                <label class="block text-sm text-gray-500 mb-4">
                                    Vous êtes <span class="text-red-500">*</span>
                                </label>
                                <div class="space-y-3">
                                    <label
                                        class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-primary transition-all peer-checked:border-primary">
                                        <input type="radio" id="occupation" name="occupation" value="professor"
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

                                    <p id="occupation-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                            </div>

                            <!-- Professor Field -->
                            <div id="professor-field" class="hidden space-y-4">
                                <!-- Establishment -->
                                <div class="mb-4">
                                    <label for="estab" class="block text-sm text-gray-500 mb-2">
                                        Type d'établissement <span class="text-red-500">*</span>
                                    </label>
                                    <select id="estab" name="estab"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white"
                                        onchange="toggleOtherEstab()">
                                        <option value="" disabled selected>-- Veuillez sélectionner --</option>
                                        @foreach ($estabs as $estab)
                                            <option value="{{ $estab->id }}">&#160;&#160;&#160;&#160;{{ $estab->name }}
                                            </option>
                                        @endforeach
                                        <option value="other">Autre</option>
                                    </select>
                                    <p id="estab-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                                <div id="other-estab-field" class="hidden">
                                    <input type="text" id="other-estab" name="other_estab" maxlength="100"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre type d'établissement">
                                    <p id="other-estab-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <!-- Job title -->
                                <div>
                                    <label for="job" class="block text-sm text-gray-500 mb-2">
                                        Fonction professionelle <span class="text-red-500">*</span>
                                    </label>
                                    <select id="job" name="job"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white"
                                        onchange="toggleOtherJob()">
                                        <option value="" disabled selected>-- Veuillez sélectionner --</option>
                                        @foreach ($professions as $prof)
                                            @if ($prof->hasChildren())
                                                <option value="" disabled>{{ $prof->name }}</option>
                                            @else
                                                @if ($prof->child_flg)
                                                    <option value="{{ $prof->id }}">
                                                        &#160;&#160;&#160;&#160;{{ $prof->name }}</option>
                                                @else
                                                    <option value="{{ $prof->id }}">{{ $prof->name }}</option>
                                                @endif
                                            @endif
                                        @endforeach
                                        <option value="other">Autre</option>
                                    </select>
                                    <p id="job-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                                <div id="other-job-field" class="hidden">
                                    <input type="text" id="other-job" name="other_job" maxlength="100"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre fonction professionelle">
                                    <p id="other-job-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <!-- RPPS -->

                                <div id="rpps-field" class="mb-4">
                                    <hr class="mb-4" />

                                    <label class="block text-md text-gray-800 mb-2">
                                        Numéro professionnel
                                    </label>
                                    <div
                                        class="px-4 py-4 mb-4 bg-gray-50 text-xs text-gray-500 border border-gray-300 rounded-lg transition-all">
                                        L’application OrthoMaster est réservée aux professionnels de santé.<br />
                                        Afin de nous assurer de cela, merci de nous indiquer votre numéro
                                        professionnel.<br />
                                        Il sera vérifié automatiquement et <b class="font-semibold text-gray-600">votre
                                            accès sera confirmé par mail dès que votre
                                            numéro sera validé</b>.<br />
                                        En cas d’erreur ou si vous laissez cette information vierge, nous devrons procéder à
                                        une vérification manuelle qui peut prendre du temps.<br />
                                        Vous ne vous rappelez pas de votre RPPS ? Retrouvez le sur
                                        <a class="text-blue-400 underline" href="{{ setting('system.check_rpps_link') }}"
                                            target="_blank">ici</a> (France).<br /><br />
                                        Merci pour votre compréhension.
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:gap-2 gap-3">
                                        <select id="rpps-type" name="rpps_type"
                                            class="w-full sm:w-48 px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white">
                                            @foreach ($noProfTypes as $npt)
                                                <option value="{{ $npt->id }}">{{ $npt->name }}</option>
                                            @endforeach
                                            <option value="other">Autre</option>
                                        </select>
                                        <input type="text" id="rpps" name="rpps" maxlength="100"
                                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                            placeholder="N° professionnel">
                                    </div>
                                    <p id="rpps-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                            </div>

                            <!-- Student Field -->
                            <div id="student-field" class="hidden space-y-4">
                                <div>
                                    <label for="major" class="block text-sm text-gray-500 mb-2">
                                        Étudiant en <span class="text-red-500">*</span>
                                    </label>
                                    <select id="major" name="major"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white"
                                        onchange="toggleOtherMajor()">
                                        <option value="" disabled selected>-- Veuillez sélectionner --</option>
                                        @foreach ($professions as $prof)
                                            @if ($prof->hasChildren())
                                                <option value="" disabled>{{ $prof->name }}</option>
                                            @else
                                                @if ($prof->child_flg)
                                                    <option value="{{ $prof->id }}">
                                                        &#160;&#160;&#160;&#160;{{ $prof->name }}</option>
                                                @else
                                                    <option value="{{ $prof->id }}">{{ $prof->name }}</option>
                                                @endif
                                            @endif
                                        @endforeach
                                        <option value="other">Autre</option>
                                    </select>
                                    <p id="major-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                                <div id="other-major-field" class="hidden">
                                    <input type="text" id="other-major" name="other_major" maxlength="100"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Votre spécialisé">
                                    <p id="other-major-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <!-- Certificate -->
                                <label for="enrollment-certificate" class="block text-sm text-gray-500 mb-2">
                                    Certificat de scolarité
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
                                        <input type="file" id="enrollment-certificate" name="enrollment_certificate"
                                            class="hidden" accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                                    </label>
                                    <p id="enrollment-certificate-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                    <p id="file-name" class="mt-2 text-sm text-gray-600 hidden"></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <button onclick="prevStep(3)" type="button"
                                class="px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                                Précédent
                            </button>
                            <button id="submit-button" onclick="nextStep(3)" type="button"
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
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-orange-200 mb-4">
                            <svg class="h-10 w-10 text-primary" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Inscription validée !
                        </h3>
                        <p class="text-gray-600 mb-6">Regardez vos mails, nous vous avons envoyé un mot de passe provisoire
                            pour vous connecter à l'application OrthoMaster.</p>

                        @if (request('inapp'))
                            <button onclick="closeSuccessModal()"
                                class="w-full px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                Fermer
                            </button>
                        @else
                            <p class="text-gray-600 mb-6 text-left">Souhaitez-vous créer un Espace Pro pour explorer avec
                                votre équipe ?</p>

                            <div class="flex flex-col md:flex-row gap-3">
                                <button onclick="createEspacePro()"
                                    class="w-full md:w-2/3 px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                    Créer Espace Pro
                                </button>

                                <button onclick="closeSuccessModal()"
                                    class="w-full md:w-1/3 px-6 py-3 text-primary font-medium rounded-lg border border-primary transition-colors">
                                    Plus tard
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>


            <!-- Email Duplicate Modal -->
            <div id="email-duplicate-modal"
                class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <svg class="h-10 w-10 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>

                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Email déjà utilisé
                        </h3>
                        <p class="text-gray-600 text-justify text-sm mb-6">Cette adresse email est déjà enregistrée
                            dans notre système.
                            Veuillez utiliser une autre adresse email ou récupérer votre mot de passe.</p>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button onclick="closeDuplicateModal()"
                                class="w-full sm:w-1/3 px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                                Retour
                            </button>
                            <button onclick="redirectToPasswordReset()"
                                class="flex-1 px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">
                                Récupérer le mot de passe
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="email-pending-account-modal"
                class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 mb-4">
                            <svg class="h-10 w-10 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Compte en attente</h3>
                        <p class="text-gray-600 mb-6">Votre inscription avec cet e-mail est en cours de vérification.
                            Veuillez patienter jusqu'à la fin du processus de validation.</p>
                        <button onclick="closePendingAccountModal()"
                            class="w-full px-6 py-3 bg-yellow-600 text-white font-medium rounded-lg hover:bg-yellow-700 transition-colors">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Server Error Modal -->
            <div id="error-modal"
                class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <svg class="h-10 w-10 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Erreur
                        </h3>
                        <p class="text-gray-500 text-left text-sm mb-2">Veuillez vérifier les erreurs ci-dessous :</p>
                        <ul id="error-list"
                            class="max-w-md space-y-1 text-gray-600 list-disc list-inside text-sm text-left mb-6">
                        </ul>

                        <div class="flex">
                            <button onclick="closeErrorModal()"
                                class="flex-1 px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Checking Modal -->
            <div id="checking-modal"
                class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-orange-100 mb-4">
                            <svg class="animate-spin h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Envoi en cours</h3>
                        <p class="text-gray-600">Veuillez patienter pendant que nous traitons votre demande...</p>
                    </div>
                </div>
            </div>

            <!-- Pending Modal -->
            <div id="pending-modal"
                class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 mb-4">
                            <svg class="h-10 w-10 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Demande en attente</h3>
                        <p class="text-gray-600 mb-6">Votre demande est en cours de traitement. Nous vous
                            confirmerons votre inscription une fois la vérification terminée.</p>
                        <button onclick="closePendingModal()"
                            class="w-full px-6 py-3 bg-yellow-600 text-white font-medium rounded-lg hover:bg-yellow-700 transition-colors">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <form id="pro-form" method="POST" action="{{ route('register.store.user') }}">
        <input type="hidden" id="pro-email" name="email" value="" />
        @csrf
    </form>
@endsection

@section('css')
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
@endsection

@section('js')
    <script>
        // Handle show in app
        const urlParams = new URLSearchParams(window.location.search);
        const isInApp = urlParams.get('inapp') === 'true';
        if (isInApp) {
            document.body.classList.add('in-app-view');

            // Ẩn các elements
            const header = document.querySelector('header');
            const footer = document.querySelector('footer');

            if (header) header.style.display = 'none';
            if (footer) footer.style.display = 'none';
        }

        let currentStep = 1;

        @if ($invCode)
            toggleInviteInput("{{ $invCode }}");
        @endif

        // Phone input
        const phoneInput = document.querySelector("#phone");
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "fr",
            preferredCountries: ["fr", "de", "ch", "be"],
            nationalMode: false,
            formatOnDisplay: true,
            autoPlaceholder: "aggressive",
            separateDialCode: false,
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js"
        });
        phoneInput.addEventListener('blur', function() {
            const value = phoneInput.value.trim();
            if (value && !value.startsWith('+')) {
                const dialCode = iti.getSelectedCountryData().dialCode;
                iti.setNumber('+' + dialCode + value.replace(/\D/g, ''));
            }
        });

        function nextStep(step) {
            // Step 1
            if (step === 1) {
                const email = document.getElementById('email').value;
                const emailInput = document.getElementById('email');
                const nextButton = document.querySelector('#step1-content button');

                const inviteCode = document.getElementById('invite-code').value;

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
                            email: email,
                            inviteCode: inviteCode

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
                        // Enable input and button
                        emailInput.disabled = false;
                        nextButton.disabled = false;
                        nextButton.innerHTML = 'Suivant';

                        if (data.status == 'failed') {
                            // Email error
                            if (data.code == 'email-exist') {
                                showError('email-error', data.message);
                                document.getElementById('email-duplicate-modal').classList.remove('hidden');
                            }

                            if (data.code == 'pending-account') {
                                showError('email-error', data.message);
                                document.getElementById('email-pending-account-modal').classList.remove('hidden');
                            }

                            // Invite code error
                            if (data.code == 'code-invalid' || data.code == 'code-expired') {
                                showError('invite-code-error', data.message);
                            }
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
            }

            // Step 2
            if (step === 2) {
                const name = document.getElementById('name').value;
                const firstName = document.getElementById('first-name').value;
                const phone = iti.getNumber();

                if (!name.trim()) {
                    showError('name-error', "Veuillez saisir votre nom !");
                    return;
                }
                hideError('name-error');

                if (!firstName.trim()) {
                    showError('first-name-error', "Veuillez saisir votre prénom !");
                    return;
                }
                hideError('first-name-error');

                if (phone && !iti.isValidNumber()) {
                    showError('phone-error', "Veuillez saisir un numéro de téléphone valide !");
                    return;
                }
                hideError('phone-error');

                // Prepare init display
                const hasInviteCode = document.getElementById('invite-code').value !== "";
                if (hasInviteCode) {
                    document.getElementById('rpps-field').classList.add('hidden');
                } else {
                    document.getElementById('rpps-field').classList.remove('hidden');
                }
            }

            // step 3
            if (step === 3) {
                const occupation = document.querySelector('input[name="occupation"]:checked');
                const hasInviteCode = document.getElementById('invite-code').value !== "";

                if (!occupation) {
                    showError('occupation-error', "Veuillez choisir votre profession !");
                    return;
                }
                hideError('occupation-error');

                // Professor
                if (occupation.value === 'professor') {
                    const estab = document.getElementById('estab').value;
                    const job = document.getElementById('job').value;
                    const rppsType = document.getElementById('rpps-type').value;
                    const rpps = document.getElementById('rpps').value;

                    // Estab
                    if (!estab) {
                        showError('estab-error', "Veuillez sélectionner une type d'établissement !");
                        return;
                    }
                    hideError('estab-error');

                    if (estab === 'other') {
                        const estabSpecialty = document.getElementById('other-estab').value;
                        if (!estabSpecialty) {
                            showError('other-estab-error', "Veuillez saisir votre type d'établissement !");
                            return;
                        }
                    }
                    hideError('other-estab-error');

                    // Job
                    if (!job) {
                        showError('job-error', "Veuillez sélectionner une fonction professionelle !");
                        return;
                    }
                    hideError('job-error');

                    if (job === 'other') {
                        const jobSpecialty = document.getElementById('other-job').value;
                        if (!jobSpecialty) {
                            showError('other-job-error', "Veuillez saisir votre fonction professionelle !");
                            return;
                        }
                    }
                    hideError('other-job-error');

                    // RPPS
                    if (rppsType !== 'other' && !rpps && !hasInviteCode) {
                        showError('rpps-error', "Veuillez saisir votre numéro professionnel !");
                        return;
                    }
                    hideError('rpps-error');

                    // Student
                } else if (occupation.value === 'student') {
                    const major = document.getElementById('major').value;
                    const certificate = document.getElementById('enrollment-certificate').files[0];

                    // Major
                    if (!major) {
                        showError('major-error', "Veuillez sélectionner une spécialité !");
                        return;
                    }
                    hideError('major-error');

                    if (major === 'other') {
                        const majorSpecialty = document.getElementById('other-major').value;
                        if (!majorSpecialty) {
                            showError('other-major-error', "Veuillez saisir votre spécialité !");
                            return;
                        }
                    }
                    hideError('other-major-error');

                    // Certification
                    if (!certificate && !hasInviteCode) {
                        showError('enrollment-certificate-error', "Veuillez télécharger votre certificat !");
                        return;
                    }
                    hideError('enrollment-certificate-error');
                }

                // Submit if all correct
                disableSubmitButton();
                submitForm();
            }

            // Next step
            if (currentStep !== 3) {
                document.getElementById(`step${step}-content`).classList.add('hidden');
                currentStep = step + 1;
                document.getElementById(`step${currentStep}-content`).classList.remove('hidden');
                updateProgress(currentStep);
            }

            // Set focus
            if (currentStep === 1) {
                setTimeout(() => {
                    document.getElementById('email').focus();
                }, 100);
            }
            if (currentStep === 2) {
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

        // Toogle invite input
        function toggleInviteInput(initValue = "") {
            const container = document.getElementById('invite-container');
            const toggleText = document.getElementById('toggle-invite');

            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                toggleText.classList.add('hidden');
                document.getElementById('invite-code').value = initValue;
            } else {
                container.classList.add('hidden');
                toggleText.classList.remove('hidden');
                hideError('invite-code-error');
                document.getElementById('invite-code').value = initValue;
            }
        }

        function formatInviteCode(input) {
            let value = input.value;
            if (value.length > 15) {
                value = value.substring(0, 15);
            }
            // Only letters and numerics
            value = value.replace(/[^a-zA-Z0-9]/g, '');
            // Uppercase
            input.value = value.toUpperCase();
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
                hideError('enrollment-certificate-error');
            }
        }

        function submitForm() {
            // Show checking modal
            document.getElementById('checking-modal').classList.remove('hidden');

            let form = document.getElementById('register-form');
            let formData = new FormData(form);

            // Ajax to submit
            fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    return response.json();
                })
                .then(data => {
                    // close checking modal
                    document.getElementById('checking-modal').classList.add('hidden');

                    if (data.status == 'success') {
                        document.getElementById('success-modal').classList.remove('hidden');
                    }

                    if (data.status == 'pending') {
                        document.getElementById('pending-modal').classList.remove('hidden');
                    }

                    if (data.status == 'failed') {
                        document.getElementById('checking-modal').classList.add('hidden');

                        if (data.errors) {
                            let errorList = document.getElementById('error-list');
                            errorList.innerHTML = Object.values(data.errors)
                                .flat()
                                .map(message => `<li>${message}</li>`)
                                .join('');
                            document.getElementById('error-modal').classList.remove('hidden');
                        }

                        reEnableSubmitButton();
                    }
                })
                .catch(error => {
                    console.log(error);
                    // close checking modal
                    document.getElementById('checking-modal').classList.add('hidden');

                    // error messages
                    if (error.errors) {
                        let errorList = document.getElementById('error-list');
                        errorList.innerHTML = Object.values(error.errors)
                            .flat()
                            .map(message => `<li>${message}</li>`)
                            .join('');
                    }

                    // show errors
                    document.getElementById('error-modal').classList.remove('hidden');
                    reEnableSubmitButton();
                });
        }

        function closeSuccessModal() {
            if (isInApp) {
                if (window.ReactNativeWebView) {
                    window.ReactNativeWebView.postMessage(JSON.stringify({
                        type: 'CLOSE'
                    }));
                }
            } else {
                window.location.href = "https://orthomaster.fr";
            }
        }

        function closePendingModal() {
            if (isInApp) {
                if (window.ReactNativeWebView) {
                    window.ReactNativeWebView.postMessage(JSON.stringify({
                        type: 'CLOSE'
                    }));
                }
            } else {
                window.location.href = "https://orthomaster.fr";
            }
        }

        function redirectToPasswordReset() {
            if (isInApp) {
                if (window.ReactNativeWebView) {
                    window.ReactNativeWebView.postMessage(JSON.stringify({
                        type: 'FORGOT'
                    }));
                }
            } else {
                const email = document.getElementById('email').value;
                const pwdResetUrl = "{{ route('password.request') }}" + '?email=' + email;
                window.location.href = pwdResetUrl;
            }
        }

        function closeDuplicateModal() {
            document.getElementById('email-duplicate-modal').classList.add('hidden');
        }

        function closePendingAccountModal() {
            document.getElementById('email-pending-account-modal').classList.add('hidden');
        }

        function closeErrorModal() {
            document.getElementById('error-modal').classList.add('hidden');
            reEnableSubmitButton();
        }

        function disableSubmitButton() {
            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span class="flex items-center justify-center"><svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Envoi en cours...</span>';
        }

        function reEnableSubmitButton() {
            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = false;
            submitButton.innerHTML = 'Soumettre';
        }

        function createEspacePro() {
            // document.getElementById('success-modal').classList.add('hidden');
            document.getElementById('checking-modal').classList.remove('hidden');
            document.getElementById('pro-email').value = document.getElementById('email').value;
            document.getElementById('pro-form').submit();
        }

        // Add "enter" to move step
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
                    nextStep(3);
                }
            });
        });
    </script>
@endsection
