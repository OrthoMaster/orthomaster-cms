@extends('landingpage.template.index')

@section('title', 'Inscription OrthoMaster')

@section('content')
    <main class="flex-grow py-8 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-3xl font-semibold text-gray-800 mb-0 font-heading">Inscription OrthoMaster</h1>
                <p class="text-gray-400">Veuillez compléter les informations de votre établissement et vos équipe</p>
            </div>

            <form id="group-reg-form" method="post" action="{{ route('register.company.draft', $company->invite_code) }}"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="invite_code" value="{{ $company->invite_code }}" />
                <input type="hidden" id="members" name="members" />

                <!-- Progress Steps -->
                <div class="mb-8">
                    <div class="flex items-center justify-between relative">
                        <!-- Step 1 -->
                        <div class="flex flex-col items-center relative z-10 flex-1">
                            <div id="step1-circle"
                                class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-semibold mb-2 transition-all duration-300">
                                1
                            </div>
                            <span id="step1-text" class="text-sm font-medium text-primary text-center">Établissement</span>
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
                            <span id="step2-text" class="text-sm text-gray-500 text-center">Équipe</span>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                    <!-- Step 1: Company -->
                    <div id="step1-content" class="step-content">
                        <h2 class="text-lg font-semibold text-gray-800 mb-6 font-heading">Étape 1 : Informations de
                            l'établissement</h2>

                        <div class="space-y-4">
                            <div>
                                <label for="company-name" class="block text-sm text-gray-500 mb-2">
                                    Nom de l'établissement <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="company-name" name="company_name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                    placeholder="Nom de l'établissement" maxlength="200" autofocus required
                                    value="{{ $company->name }}">
                                <p id="company-name-error" class="mt-2 text-sm text-red-600 hidden"></p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="email" class="block text-sm text-gray-500 mb-2">
                                        Email
                                    </label>
                                    <input type="email" id="company-email" name="company_email" maxlength="100"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="email@exemple.com" value="{{ $company->email }}">
                                    <p id="company-email-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <div>
                                    <label for="siret" class="block text-sm text-gray-500 mb-2">
                                        Numéro SIRET
                                    </label>
                                    <input type="text" id="siret" name="siret"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="SIRET" maxlength="14" value="{{ $company->siret }}">
                                    <p id="siret-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <div>
                                    <label for="phone" class="block text-sm text-gray-500 mb-2">
                                        Numéro de téléphone
                                    </label>
                                    <input type="tel" id="phone" name="phone" maxlength="50"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        value="{{ $company->phone }}">
                                    <p id="phone-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                            </div>

                            <div>
                                <label for="address" class="block text-sm text-gray-500 mb-2">
                                    Adresse <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="address" name="address"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                    placeholder="Adresse complète" maxlength="255" required value="{{ $company->address }}">
                                <p id="address-error" class="mt-2 text-sm text-red-600 hidden"></p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="postal-code" class="block text-sm text-gray-500 mb-2">
                                        Code postal <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="postal-code" name="postal_code"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Code postal" maxlength="10" required
                                        value="{{ $company->postal_code }}">
                                    <p id="postal-code-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                                <div>
                                    <label for="city" class="block text-sm text-gray-500 mb-2">
                                        Ville <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="city" name="city"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Ville" maxlength="100" required value="{{ $company->city }}">
                                    <p id="city-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <div>
                                    <label for="country" class="block text-sm text-gray-500 mb-2">
                                        Pays <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="country" name="country"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Pays" maxlength="100" required value="{{ $company->country }}">
                                    <p id="country-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end">
                            <button id="nextBtn" onclick="nextStep(1)" type="button"
                                class="px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                Suivant
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Membres -->
                    <div id="step2-content" class="step-content hidden">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-semibold text-gray-800 font-heading">Étape 2 : Membres de l'équipe</h2>
                        </div>

                        <!-- Members List -->
                        <div id="members-list" class="space-y-4 mb-6">
                            <!-- Empty State -->
                            <div class="text-center text-gray-400 py-12" id="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <p>Aucun membre ajouté</p>
                                <p class="text-sm">Cliquez sur "Ajouter" pour commencer</p>
                            </div>

                            <!-- Members Cards Container -->
                            <div id="members-cards" class="space-y-4"></div>
                        </div>

                        <!-- Button Add -->
                        @if (!$company->status == App\Status::PENDING->value)
                            <button onclick="openAddMemberModal()" type="button"
                                class="w-full px-4 py-2 border border-2 border-gray-100 text-gray-400 rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Ajouter
                            </button>
                        @endif
                        <p id="members-error" class="mt-2 text-sm text-red-600 hidden"></p>

                        <div class="mt-4 flex justify-between">
                            <button onclick="prevStep(2)" type="button"
                                class="px-6 py-3 bg-gray-200 text-gray-500 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 19.5 8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            @if (!$company->status == App\Status::PENDING->value)
                                <button id="submit-button" onclick="submitForm()" type="button"
                                    class="px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                    Soumettre
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            <!-- Add/Edit Member Modal -->
            <div id="add-member-modal"
                class="hidden h-screen fixed inset-0 bg-black/50 bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-lg w-full transform transition-all h-[95vh] flex flex-col">
                    <div class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                        <h3 id="modal-title" class="text-xl font-semibold text-gray-800 font-heading">Ajouter un membre
                        </h3>
                        <button onclick="closeAddMemberModal()" type="button" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div id="modal-body" class="p-6 space-y-4 flex-1 overflow-y-auto min-h-0">
                        <!-- Informations personnelles -->
                        <div class="border border-gray-200 rounded-lg">
                            <button type="button" onclick="toggleSection('info-perso')"
                                class="w-full rounded-tl-lg rounded-tr-lg flex items-center justify-between p-4 bg-gray-50 transition-colors">
                                <span class="font-medium text-gray-800">Informations personnelles</span>
                                <svg id="info-perso-icon" xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-gray-500 transition-transform" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="info-perso-content" class="px-4 pb-4 space-y-4">
                                <div class="mt-4">
                                    <label for="member-name" class="block text-sm text-gray-500 mb-2">
                                        Nom <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="member-name"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Nom" maxlength="100">
                                    <p id="member-name-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <div>
                                    <label for="member-firstname" class="block text-sm text-gray-500 mb-2">
                                        Prénom <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="member-firstname"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Prénom" maxlength="100">
                                    <p id="member-firstname-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <div>
                                    <label for="member-email" class="block text-sm text-gray-500 mb-2">
                                        Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" id="member-email"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="email@exemple.com">
                                    <p id="member-email-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <!-- Type -->
                                <div>
                                    <label for="member-organization" class="block text-sm text-gray-500 mb-2">
                                        Organisme de formation
                                    </label>
                                    <select id="member-organization" onchange="toggleOpcoEp()"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white">
                                        @foreach ($organizations as $organization)
                                            <option value="{{ $organization->id }}"
                                                data-code="{{ $organization->code }}">
                                                {{ $organization->name }}</option>
                                        @endforeach
                                        <option value="" data-code="other">Autre / Je ne sais pas</option>
                                    </select>
                                </div>

                                <!-- First name of Birth (only for OPCO-EP) -->
                                <div id="first-name-birth-field" class="">
                                    <label for="member-firstname-birth" class="block text-sm text-gray-500 mb-2">
                                        Nom jeune fille
                                    </label>
                                    <input type="text" id="member-firstname-birth"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="Nom jeune fille" maxlength="100">
                                </div>

                                <!-- Date of Birth (only for OPCO-EP) -->
                                <div id="dob-field" class="">
                                    <label for="member-dob" class="block text-sm text-gray-500 mb-2">
                                        Date de naissance <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" id="member-dob"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
                                    <p id="member-dob-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Profession -->
                        <div class="border border-gray-200 rounded-lg">
                            <button type="button" onclick="toggleSection('profession')"
                                class="w-full rounded-tl-lg rounded-tr-lg flex items-center justify-between p-4 bg-gray-50 transition-colors">
                                <span class="font-medium text-gray-800">Profession</span>
                                <svg id="profession-icon" xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-gray-500 transition-transform" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="profession-content" class="px-4 pb-4 space-y-4">
                                <div class="mt-4">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg pb-4">
                                        <div>
                                            <label class="text-md text-gray-700">Étudiant</label>
                                        </div>
                                        <button type="button" id="student-toggle" onclick="toggleStudentSwitch()"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full bg-gray-300 transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                            <span id="student-toggle-circle"
                                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-1"></span>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label for="member-job" class="block text-sm text-gray-500 mb-2">
                                        Fonction professionelle <span class="text-red-500">*</span>
                                    </label>
                                    <select id="member-job"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white">
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
                                    <p id="member-job-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <div>
                                    <label for="member-rpps" class="block text-sm text-gray-500 mb-2">
                                        Numéro professionnel
                                    </label>
                                    <div class="flex flex-col sm:flex-row sm:gap-2 gap-3">
                                        <select id="member-rpps-type"
                                            class="w-full sm:w-48 px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all bg-white">
                                            @foreach ($noProfTypes as $npt)
                                                <option value="{{ $npt->id }}">{{ $npt->name }}</option>
                                            @endforeach
                                            <option value="other">Autre</option>
                                        </select>
                                        <input type="text" id="member-rpps" maxlength="100"
                                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                            placeholder="N° professionnel">
                                    </div>
                                    <p id="member-rpps-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>

                                <div id="secu-field" class="">
                                    <label for="member-secu" class="block text-sm text-gray-500 mb-2">
                                        Numéro SECU <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="member-secu"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="1 00 10 00 100 010">
                                    <p id="member-secu-error" class="mt-2 text-sm text-red-600 hidden"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Rôle -->
                        <div
                            class="flex items-center justify-between p-4 bg-gray-50 border border-gray-200 rounded-lg pb-4">
                            <div>
                                <label class="text-md text-gray-700">Responsable</label>
                                <p class="text-xs text-gray-400">Définir comme responsable pédagogique</p>
                            </div>
                            <button type="button" id="leader-toggle" onclick="toggleLeaderSwitch()"
                                class="relative inline-flex h-6 w-11 items-center rounded-full bg-gray-300 transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                <span id="leader-toggle-circle"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-1"></span>
                            </button>
                        </div>
                    </div>

                    <div class="bg-white border-t border-gray-200 px-6 py-4 flex flex-col sm:flex-row sm:gap-2 gap-3">
                        <button id="save-member-btn" onclick="saveMember(true)" type="button"
                            class="flex-1 px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors">
                            Vérifier & Enregistrer
                        </button>
                        <button onclick="saveMember(false)" type="button"
                            class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                            Enregister brouillon
                        </button>
                    </div>
                </div>
            </div>

            <!-- Success Modal -->
            <div id="success-modal"
                class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                            <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Soumettre avec succès !</h3>
                        <p class="text-gray-600 mb-6">Votre inscription a été enregistrée avec succès.</p>
                        <button onclick="closeSuccessModal()"
                            class="w-full px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Error Modal -->
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
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Erreur</h3>
                        <p class="text-gray-500 text-left text-sm mb-2">Veuillez vérifier les erreurs ci-dessous :</p>
                        <ul id="error-list"
                            class="max-w-md space-y-1 text-gray-600 list-disc list-inside text-sm text-left mb-6"></ul>
                        <button onclick="closeErrorModal()"
                            class="w-full px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">
                            Fermer
                        </button>
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
        </div>
    </main>
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
        @if ($company->status == App\Status::PENDING->value)
            document.querySelectorAll('input, textarea').forEach(element => {
                element.disabled = true;
            });
            document.querySelectorAll('select').forEach(select => {
                select.disabled = true;
            });
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

        function scrollToAndFocus(elementId, inPopup = true) {
            const el = document.getElementById(elementId);
            if (inPopup) { // Scroll in popup
                toggleSection('info-perso', true);
                toggleSection('profession', true);

                const container = document.getElementById('modal-body');
                const y = el.getBoundingClientRect().top -
                    container.getBoundingClientRect().top +
                    container.scrollTop - 50;

                container.scrollTo({
                    top: y,
                    behavior: 'smooth'
                });
            } else { // Scroll in page
                window.scrollTo({
                    top: el.offsetTop - 100,
                    behavior: 'smooth'
                });
            }

            // setTimeout(() => el.focus(), 300);
        }

        let currentStep = 1;
        let members = @json($company->users_temp ?? []);
        let memberIdCounter = members.length;
        let editingMemberId = null;
        let isLeader = false;
        let isStudent = false;

        function nextStep(step) {
            // Step 1 - Company
            if (step === 1) {
                const companyName = document.getElementById('company-name').value.trim();
                const companyEmail = document.getElementById('company-email').value.trim();
                const siret = document.getElementById('siret').value.trim();

                const address = document.getElementById('address').value.trim();
                const postalCode = document.getElementById('postal-code').value.trim();
                const city = document.getElementById('city').value.trim();

                let hasError = false;
                let firstError = false;

                if (!companyName) {
                    showError('company-name-error', "Veuillez saisir le nom de l'établissement !");
                    hasError = true;
                    firstError = true;

                    scrollToAndFocus('company-name', false);
                } else {
                    hideError('company-name-error');
                }

                if (companyEmail && !validateEmail(companyEmail)) {
                    showError('company-email-error', "Veuillez saisir un email valide !");
                    hasError = true;
                    if (!firstError) {
                        firstError = true;
                        scrollToAndFocus('company-email', false);
                    }

                    scrollToAndFocus('company-email', false);
                } else {
                    hideError('company-email-error');
                }

                if (siret && !validateSIRET(siret)) {
                    showError('siret-error', "Le numéro SIRET doit contenir 14 chiffres !");
                    hasError = true;
                    if (!firstError) {
                        firstError = true;
                        scrollToAndFocus('siret', false);
                    }
                } else {
                    hideError('siret-error');
                }

                if (!address) {
                    showError('address-error', "Veuillez saisir l'adresse !");
                    hasError = true;
                    if (!firstError) {
                        firstError = true;
                        scrollToAndFocus('address', false);
                    }
                } else {
                    hideError('address-error');
                }

                if (!postalCode) {
                    showError('postal-code-error', "Veuillez saisir le code postal !");
                    hasError = true;
                    if (!firstError) {
                        firstError = true;
                        scrollToAndFocus('postal-code', false);
                    }
                } else {
                    hideError('postal-code-error');
                }

                if (!city) {
                    showError('city-error', "Veuillez saisir la ville !");
                    hasError = true;
                    if (!firstError) {
                        firstError = true;
                        scrollToAndFocus('city', false);
                    }
                } else {
                    hideError('city-error');
                }

                if (hasError) return;
            }

            // Next step
            document.getElementById(`step${step}-content`).classList.add('hidden');
            currentStep = step + 1;
            document.getElementById(`step${currentStep}-content`).classList.remove('hidden');
            updateProgress(currentStep);
            renderMembers();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function prevStep(step) {
            document.getElementById(`step${step}-content`).classList.add('hidden');
            currentStep = step - 1;
            document.getElementById(`step${currentStep}-content`).classList.remove('hidden');
            updateProgress(currentStep);
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function updateProgress(step) {
            for (let i = 1; i <= 2; i++) {
                const circle = document.getElementById(`step${i}-circle`);
                const text = document.getElementById(`step${i}-text`);

                if (i <= step) {
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
        }

        function toggleSection(sectionId, forceOpen = false) {
            const content = document.getElementById(`${sectionId}-content`);
            const icon = document.getElementById(`${sectionId}-icon`);

            if (content.classList.contains('hidden') || forceOpen) {
                content.classList.remove('hidden');
                icon.classList.remove('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.add('rotate-180');
            }
        }

        function toggleOpcoEp() {
            const memberOrganization = document.getElementById('member-organization');
            const dataCode = memberOrganization.selectedOptions[0].dataset.code;

            const dobField = document.getElementById('dob-field');
            const fnbField = document.getElementById('first-name-birth-field');
            const secuField = document.getElementById('secu-field');

            if (dataCode === 'opco-ep' || dataCode === 'other') {
                dobField.classList.remove('hidden');
                fnbField.classList.remove('hidden');
                secuField.classList.remove('hidden');
            } else {
                dobField.classList.add('hidden');
                fnbField.classList.add('hidden');
                secuField.classList.add('hidden');


                document.getElementById('member-dob').value = '';
                document.getElementById('member-secu').value = '';

                hideError('member-dob-error');
                hideError('member-secu-error');
            }
        }

        function openAddMemberModal() {
            @if ($company->status == App\Status::PENDING->value)
                return;
            @endif

            editingMemberId = null;
            isLeader = false;
            isStudent = false;
            document.getElementById('modal-title').textContent = 'Ajouter un membre';
            document.getElementById('save-member-btn').textContent = 'Vérifier & Ajouter';

            toggleOpcoEp();
            updateLeaderToggle();
            document.getElementById('add-member-modal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('member-email').focus();
            }, 100);
        }

        function openEditMemberModal(memberId) {
            @if ($company->status == App\Status::PENDING->value)
                return;
            @endif

            const member = members.find(m => m.id === memberId);
            if (!member) return;

            editingMemberId = memberId;
            isLeader = member.isLeader;
            isStudent = member.isStudent;

            document.getElementById('modal-title').textContent = 'Modifier le membre';
            document.getElementById('save-member-btn').textContent = 'Vérifier & Enregistrer';

            document.getElementById('member-organization').value = member.organization;

            document.getElementById('member-email').value = member.email;
            document.getElementById('member-name').value = member.name;
            document.getElementById('member-firstname').value = member.firstname;
            document.getElementById('member-firstname-birth').value = member.firstnameBirth;
            document.getElementById('member-dob').value = member.dob || '';

            document.getElementById('member-job').value = member.job;
            document.getElementById('member-rpps-type').value = member.rppsType;
            document.getElementById('member-rpps').value = member.rpps;
            document.getElementById('member-secu').value = member.secu;

            toggleOpcoEp();
            updateLeaderToggle();
            updateStudentToggle();
            document.getElementById('add-member-modal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('member-email').focus();
            }, 100);
        }

        function toggleLeaderSwitch() {
            isLeader = !isLeader;
            updateLeaderToggle();
        }

        function updateLeaderToggle() {
            const toggle = document.getElementById('leader-toggle');
            const circle = document.getElementById('leader-toggle-circle');

            if (isLeader) {
                toggle.classList.remove('bg-gray-300');
                toggle.classList.add('bg-primary');
                circle.classList.remove('translate-x-1');
                circle.classList.add('translate-x-6');
            } else {
                toggle.classList.remove('bg-primary');
                toggle.classList.add('bg-gray-300');
                circle.classList.remove('translate-x-6');
                circle.classList.add('translate-x-1');
            }
        }

        function toggleStudentSwitch() {
            isStudent = !isStudent;
            updateStudentToggle();
        }

        function updateStudentToggle() {
            const toggle = document.getElementById('student-toggle');
            const circle = document.getElementById('student-toggle-circle');

            if (isStudent) {
                toggle.classList.remove('bg-gray-300');
                toggle.classList.add('bg-primary');
                circle.classList.remove('translate-x-1');
                circle.classList.add('translate-x-6');
            } else {
                toggle.classList.remove('bg-primary');
                toggle.classList.add('bg-gray-300');
                circle.classList.remove('translate-x-6');
                circle.classList.add('translate-x-1');
            }
        }

        function closeAddMemberModal() {
            document.getElementById('add-member-modal').classList.add('hidden');
            clearMemberForm();
        }

        function clearMemberForm() {
            editingMemberId = null;
            isLeader = false;
            isStudent = false;
            document.getElementById('member-email').value = '';
            document.getElementById('member-name').value = '';
            document.getElementById('member-firstname').value = '';
            document.getElementById('member-firstname-birth').value = '';
            document.getElementById('member-dob').value = '';
            document.getElementById('member-job').value = '';
            document.getElementById('member-rpps-type').selectedIndex = 0;
            document.getElementById('member-rpps').value = '';
            document.getElementById('member-secu').value = '';

            toggleOpcoEp();
            updateLeaderToggle();
            updateStudentToggle();
            hideError('member-email-error');
            hideError('member-name-error');
            hideError('member-firstname-error');
            hideError('member-dob-error');
            hideError('member-job-error');
            hideError('member-rpps-error');
            hideError('member-secu-error');
        }

        async function saveMember(hasReturn = true) {
            let hasError = false;
            let firstError = false;
            let errorMessage = '';

            const email = document.getElementById('member-email').value.trim();
            const name = document.getElementById('member-name').value.trim();
            const firstname = document.getElementById('member-firstname').value.trim();
            const firstnameBirth = document.getElementById('member-firstname-birth').value;

            // Email
            if (!email) {
                hasError = true;
                firstError = true;
                showError('member-email-error', "Veuillez saisir l'email !");
                scrollToAndFocus('member-email');
                return;
            } else if (!validateEmail(email)) {
                // Check syntax
                hasError = true;
                firstError = true;
                showError('member-email-error', "Veuillez saisir un email valide !");
                scrollToAndFocus('member-email');
                return;
            } else if (members.filter(m => (m.email === email && m.id !== editingMemberId)).length > 0) {
                // Check exist in list
                hasError = true;
                firstError = true;
                showError('member-email-error', "Un autre membre a utilisé cet email.");
                scrollToAndFocus('member-email');
                return;
            } else { // Check exist in database
                const isEmailValid = await checkEmailWithDB(email);
                if (!isEmailValid) {
                    hasError = true;
                    firstError = true;
                    showError('member-email-error', "Cet email est déjà utilisé.");
                    scrollToAndFocus('member-email');
                    return;
                }
                hideError('member-email-error');
            }

            // Name
            if (!name) {
                hasError = true;
                showError('member-name-error', "Veuillez saisir le nom !");
                if (!firstError) {
                    firstError = true;
                    scrollToAndFocus('member-name');
                }
                return;
            } else {
                hideError('member-name-error');
            }

            // First name
            if (!firstname) {
                hasError = true;
                showError('member-firstname-error', "Veuillez saisir le prénom !");

                if (!firstError) {
                    firstError = true;
                    scrollToAndFocus('member-firstname');
                }
                return;
            } else {
                hideError('member-firstname-error');
            }

            // Check with OPCO-EP
            const memberOrganization = document.getElementById('member-organization');
            const dataCode = memberOrganization.selectedOptions[0].dataset.code;

            // Dob
            const dob = document.getElementById('member-dob').value;
            if (dataCode == 'opco-ep' || dataCode == 'other') {
                if (!dob || !isDateInPast(dob)) {
                    hasError = true;
                    errorMessage += (errorMessage ? ', ' : '') + 'Date de naissance';

                    if (hasReturn) {
                        showError('member-dob-error', "Veuillez saisir une date de naissance valide !");
                        if (!firstError) {
                            firstError = true;
                            scrollToAndFocus('member-dob');
                        }
                    }
                } else {
                    hideError('member-dob-error');
                }
            }

            // Job
            const job = document.getElementById('member-job').value;
            if (!job) {
                hasError = true;
                errorMessage += (errorMessage ? ', ' : '') + 'Fonction professionelle';

                if (hasReturn) {
                    showError('member-job-error', "Veuillez sélectionner une fonction professionelle !");
                    if (!firstError) {
                        firstError = true;
                        scrollToAndFocus('member-job');
                    }
                }
            } else {
                hideError('member-job-error');
            }

            // RPPS
            const rppsType = document.getElementById('member-rpps-type').value;
            const rpps = document.getElementById('member-rpps').value;
            // if (rppsType !== 'other' && !rpps) {
            //     hasError = true;
            //     errorMessage += (errorMessage ? ', ' : '') + 'N° professionnel';

            //     if (hasReturn) {
            //         showError('member-rpps-error', "Veuillez saisir le numéro professionnel !");
            //         if (!firstError) {
            //             firstError = true;
            //             scrollToAndFocus('member-rpps');
            //         }
            //     }
            // } else {
            //     hideError('member-rpps-error');
            // }

            // SECU
            const secu = document.getElementById('member-secu').value;
            if (dataCode == 'opco-ep' || dataCode == 'other') {
                if (!secu) {
                    hasError = true;
                    errorMessage += (errorMessage ? ', ' : '') + 'N° SECU';

                    if (hasReturn) {
                        showError('member-secu-error', "Veuillez saisir le numéro SECU !");
                        if (!firstError) {
                            firstError = true;
                            scrollToAndFocus('member-secu');
                        }
                    }
                } else {
                    hideError('member-secu-error');
                }
            }

            if (hasReturn && hasError) {
                return;
            }

            if (editingMemberId) {
                // Edit existing member
                const member = members.find(m => m.id === editingMemberId);
                if (member) {
                    member.hasError = hasError;
                    member.errorMessage = errorMessage;

                    member.organization = memberOrganization.value;
                    member.organizationLabel = document.getElementById('member-organization').selectedOptions[0].text;

                    member.email = email;
                    member.name = name;
                    member.firstname = firstname;
                    member.firstnameBirth = firstnameBirth
                    member.dob = dob;

                    member.job = job;
                    member.jobLabel = document.getElementById('member-job').selectedOptions[0].text;

                    member.rppsType = rppsType;
                    member.rppsTypeLabel = document.getElementById('member-rpps-type').selectedOptions[0].text;
                    member.rpps = rpps;
                    member.secu = secu;

                    member.isLeader = isLeader;
                    member.isStudent = isStudent;
                }
            } else {
                // Add new member
                const member = {
                    id: ++memberIdCounter,

                    hasError: hasError,
                    errorMessage: errorMessage,

                    organization: memberOrganization.value,
                    organizationLabel: document.getElementById('member-organization').selectedOptions[0].text,

                    email: email,
                    name: name,
                    firstname: firstname,
                    firstnameBirth: firstnameBirth,
                    dob: dob,

                    job: job,
                    jobLabel: document.getElementById('member-job').selectedOptions[0].text,

                    rppsType: rppsType,
                    rppsTypeLabel: document.getElementById('member-rpps-type').selectedOptions[0].text,
                    rpps: rpps,
                    secu: secu,

                    isLeader: isLeader,
                    isStudent: isStudent
                };
                members.push(member);
            }

            renderMembers();
            saveAsDraft();
            closeAddMemberModal();
        }

        function renderMembers() {
            const emptyState = document.getElementById('empty-state');
            const membersCards = document.getElementById('members-cards');

            if (members.length === 0) {
                emptyState.classList.remove('hidden');
                membersCards.innerHTML = '';
                return;
            }

            emptyState.classList.add('hidden');

            membersCards.innerHTML = members.map(member => {
                // Border class
                let borderClass = 'border-2 border-gray-100';
                if (member.hasError) {
                    borderClass = 'border border-yellow-500';
                }

                return `
                    <div class="bg-white ${borderClass} rounded-lg pl-4 p-2 hover:shadow-md transition-shadow cursor-pointer relative" onclick="openEditMemberModal(${member.id})">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="mb-1">
                                    ${member.isLeader ? '<span class="px-2 py-1 bg-blue-500 text-white text-[10px] rounded-full">Responsable</span>' : ''}
                                    ${member.isStudent ? '<span class="px-2 py-1 bg-green-600 text-white text-[10px] rounded-full">Étudiant</span>' : ''}
                                </div>
                                <h3 class="text-md font-semibold text-gray-800 mb-0">${member.name} ${member.firstname}</h3>
                                <div class="text-xs text-gray-500 mb-2">${member.email || '<em class="text-gray-400">Email non renseigné</em>'}</div>
                            </div>
                            <div class="ml-4" onclick="event.stopPropagation()">
                                <button onclick="removeMember(${member.id})" type="button"
                                    class="p-2 text-red-600 bg-red-50 rounded-lg transition-colors"
                                    title="Supprimer">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        ${member.hasError ? `
                        <div class="space-y-1 text-xs text-yellow-500 mt-2">
                            <div class="flex gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="text-xs"><small> Manquant: ${member.errorMessage}</small></span>
                            </div>
                        </div>
                    ` : ''}
                    </div>
                `;
            }).join('');
        }

        function removeMember(memberId) {
            @if (!$company->status == App\Status::PENDING->value)
                members = members.filter(m => m.id !== memberId);
                renderMembers();
            @endif
        }

        function saveAsDraft() {
            document.getElementById('members').value = JSON.stringify(members);
            let groupRegForm = document.getElementById('group-reg-form');
            let groupRegFormData = new FormData(groupRegForm);

            // Ajax submit
            fetch(groupRegForm.action, {
                    method: 'POST',
                    body: groupRegFormData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    return response.json();
                })
                .then(data => {
                    if (data.status == 'failed') {
                        let errorList = document.getElementById('error-list');
                        errorList.innerHTML = `<li>${data.message || 'Une erreur est survenue'}</li>`;
                        document.getElementById('error-modal').classList.remove('hidden');
                    }
                })
                .catch(error => {
                    let errorList = document.getElementById('error-list');
                    if (error.errors) {
                        errorList.innerHTML = Object.values(error.errors)
                            .flat()
                            .map(message => `<li>${message}</li>`)
                            .join('');
                    } else {
                        errorList.innerHTML = '<li>Une erreur est survenue lors de l\'envoi</li>';
                    }

                    document.getElementById('error-modal').classList.remove('hidden');
                });
        }

        function submitForm() {
            // No member
            if (members.length === 0) {
                showError('members-error', "Veuillez ajouter au moins un membre !");
                return;
            }
            hideError('members-error');

            // Has error
            const totalError = members.filter(m => m.hasError).length;
            if (totalError > 0) {
                showError('members-error', "Veuillez vérifier les informations complètes des membres de l'équipe !");
                return;
            }
            hideError('members-error');

            // No manager
            const totalLeader = members.filter(m => m.isLeader).length;
            if (totalLeader <= 0) {
                showError('members-error', "Veuillez définir au moins une personne responsable pédagogique !");
                return;
            }
            hideError('members-error');

            // Disable submit button
            disableSubmitButton();

            // Show checking modal
            document.getElementById('checking-modal').classList.remove('hidden');

            // Add members
            document.getElementById('members').value = JSON.stringify(members);
            let groupRegForm = document.getElementById('group-reg-form');
            let groupRegFormData = new FormData(groupRegForm);
            // Add submit flag
            groupRegFormData.append('isSubmitted', true);

            // Ajax submit
            fetch(groupRegForm.action, {
                    method: 'POST',
                    body: groupRegFormData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    return response.json();
                })
                .then(data => {
                    document.getElementById('checking-modal').classList.add('hidden');

                    if (data.status === 'success') {
                        document.getElementById('success-modal').classList.remove('hidden');
                    }

                    if (data.status == 'failed') {
                        let errorList = document.getElementById('error-list');
                        errorList.innerHTML = `<li>${data.message || 'Une erreur est survenue'}</li>`;
                        document.getElementById('error-modal').classList.remove('hidden');
                        reEnableSubmitButton();
                    }
                })
                .catch(error => {
                    document.getElementById('checking-modal').classList.add('hidden');

                    let errorList = document.getElementById('error-list');
                    if (error.errors) {
                        errorList.innerHTML = Object.values(error.errors)
                            .flat()
                            .map(message => `<li>${message}</li>`)
                            .join('');
                    } else {
                        errorList.innerHTML = '<li>Une erreur est survenue lors de l\'envoi</li>';
                    }

                    document.getElementById('error-modal').classList.remove('hidden');
                    reEnableSubmitButton();
                });
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

        function closeSuccessModal() {
            window.location.href = "https://orthomaster.fr";
        }

        function closeErrorModal() {
            document.getElementById('error-modal').classList.add('hidden');
        }

        // Validation functions
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function validateSIRET(siret) {
            const cleanSiret = siret.replace(/\s/g, '');
            return /^\d{14}$/.test(cleanSiret);
        }

        function isDateInPast(dateString) {
            const selectedDate = new Date(dateString);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to compare only dates
            return selectedDate < today;
        }

        async function checkEmailWithDB(email) {
            return await fetch("{{ route('register.email.check') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        email: email
                    }),
                })
                .then(response => {
                    if (response.status === 200) {
                        return response.json();
                    }
                })
                .then(data => {
                    if (data.status == 'failed') {
                        return false;
                    };

                    return true;
                })
                .catch(error => {
                    return false;
                });
        }

        // Enter key navigation
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('step1-content').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    nextStep(1);
                }
            });

            // Modal - close on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAddMemberModal();
                }
            });
        });
    </script>
@endsection
