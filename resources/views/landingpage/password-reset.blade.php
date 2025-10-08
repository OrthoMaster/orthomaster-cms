@extends('landingpage.template.index')

@section('title', 'Réinitialiser le mot de passe')

@section('content')
    <!-- Main Content -->
    <main class="flex-grow py-8 px-4">
        <div class="max-w-md mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto w-16 h-16 bg-primary rounded-full flex items-center justify-center mb-4">
                    <svg class="h-10 w-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-semibold text-gray-800 mb-2 font-heading">Réinitialiser le mot de passe</h1>
                @if ($status == 'valid')
                    <p class="text-md text-gray-500">Veuillez saisir votre nouveau mot de passe</p>
                @endif
            </div>

            @if ($status == 'valid')
                <!-- Form Card -->
                <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                    <form id="reset-password-form" method="POST" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="email" value="{{ $email }}">

                        <div class="space-y-5">
                            <!-- Email (Read-only) -->
                            <div>
                                <label for="email-display" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email
                                </label>
                                <input type="email" id="email-display" value="{{ $email }}" readonly
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600 cursor-not-allowed">
                            </div>

                            <!-- New Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nouveau mot de passe <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" id="password" name="password"
                                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="••••••••" required minlength="8">
                                    <button type="button" onclick="togglePassword('password')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <svg id="password-eye" class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">Le mot de passe doit contenir au moins 8 caractères
                                </p>
                                <p id="password-error" class="mt-2 text-sm text-red-600 hidden"></p>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Confirmer le mot de passe <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" id="password_confirmation" name="password_confirmation"
                                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                        placeholder="••••••••" required minlength="8">
                                    <button type="button" onclick="togglePassword('password_confirmation')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <svg id="password_confirmation-eye" class="w-5 h-5" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                                <p id="password_confirmation-error" class="mt-2 text-sm text-red-600 hidden"></p>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-8">
                            <button onclick="submitForm()" id="submit-button" type="button"
                                class="w-full px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                                Réinitialiser le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </main>

    <!-- Invalid Modal -->
    @if ($status !== 'valid')
        <div id="invalid-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <svg class="h-10 w-10 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                        </svg>

                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Erreur</h3>
                    <p class="text-gray-600 mb-6">Ce lien de réinitialisation est invalide ou a expiré. Veuillez en
                        demander un nouveau.</p>
                    <button onclick="closeInvalidModal()"
                        class="w-full px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    @endif


    <!-- Success Modal -->
    <div id="success-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                        </path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Mot de passe réinitialisé !</h3>
                <p class="text-gray-600 mb-6">Votre mot de passe a été réinitialisé avec succès. Vous pouvez
                    maintenant vous connecter avec votre nouveau mot de passe.</p>
                <button onclick="closeSuccessModal()"
                    class="w-full px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="error-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 md:p-8 transform transition-all">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                    <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Erreur</h3>
                <ul id="error-list" class="max-w-md space-y-1 text-gray-600 list-disc list-inside text-sm text-left mb-6">
                </ul>
                <button onclick="closeErrorModal()"
                    class="w-full px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">
                    Fermer
                </button>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + '-eye');

            if (input.type === 'password') {
                input.type = 'text';
                eye.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                `;
            } else {
                input.type = 'password';
                eye.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                `;
            }
        }

        function submitForm() {
            const password = document.getElementById('password').value;
            const passwordConfirmation = document.getElementById('password_confirmation').value;

            // Clear previous errors
            hideError('password-error');
            hideError('password_confirmation-error');

            // Validation
            if (password.length < 8) {
                showError('password-error', 'Le mot de passe doit contenir au moins 8 caractères');
                return;
            }

            if (password !== passwordConfirmation) {
                showError('password_confirmation-error', 'Les mots de passe ne correspondent pas');
                return;
            }

            // Before submit
            disableSubmitButton();

            let form = document.getElementById('reset-password-form');
            let formData = new FormData(form);

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
                    if (data.status == 'failed') {
                        let errorList = document.getElementById('error-list');
                        if (data.errors) {
                            errorList.innerHTML = Object.values(data.errors)
                                .flat()
                                .map(message => `<li>${message}</li>`)
                                .join('');

                        } else {
                            errorList.innerHTML =
                                `<li>Une erreur système est survenue. Veuillez réessayer plus tard.</li>`
                        }

                        document.getElementById('error-modal').classList.remove('hidden');
                        reEnableSubmitButton();
                    }

                    if (data.status == 'success') {
                        document.getElementById('success-modal').classList.remove('hidden');
                    }
                })
                .catch(error => {
                    // error messages
                    if (error.errors) {
                        console.log(error.errors);
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

        function closeInvalidModal() {
            window.location.href = "{{ route('password.request') }}";
        }

        function closeSuccessModal() {
            window.location.href = "https://orthomaster.fr";
            // document.getElementById('success-modal').classList.add('hidden');
        }

        function closeErrorModal() {
            document.getElementById('error-modal').classList.add('hidden');
        }

        function disableSubmitButton() {
            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span class="flex items-center justify-center"><svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Traitement en cours ...</span>';
        }

        function reEnableSubmitButton() {
            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = false;
            submitButton.innerHTML = 'Réinitialiser le mot de passe';
        }

        // Handle "enter"
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('reset-password-form').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitForm();
                }
            });
        });
    </script>
@endsection
