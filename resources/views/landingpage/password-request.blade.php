@extends('landingpage.template.index')

@section('title', 'Mot de passe oublié')

@section('content')
    <!-- Main Content -->
    <main class="flex-grow py-8 px-4">
        <div class="max-w-md mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto w-16 h-16 bg-primary rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                        </path>
                    </svg>
                </div>
                <h1 class="text-3xl font-semibold text-gray-800 mb-2 font-heading">Mot de passe oublié ?</h1>
                <p class="text-md text-gray-500">Entrez votre adresse email et nous vous enverrons un lien pour
                    réinitialiser votre mot de passe</p>
            </div>

            <!-- Form Card -->
            <form id="forgot-password-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
                    <div class="space-y-5">
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Votre email
                            </label>
                            <input type="email" id="email" name="email"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                                placeholder="votre-email@email.com" value="{{ $email ?? '' }}" autofocus required>
                            <p id="email-error" class="mt-2 text-sm text-red-600 hidden"></p>
                        </div>
                        <!-- Honeypot -->
                        <input type="text" name="website" style="display:none">
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button id="submit-button" onclick="submitForm()" type="button"
                            class="w-full px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-orange-600 transition-colors focus:ring-4 focus:ring-orange-200">
                            Envoyer le lien de réinitialisation
                        </button>
                    </div>
                </div>
            </form>

            <!-- Info Box -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">Vérifiez votre boîte de réception</p>
                        <p>Si votre adresse email est enregistrée dans notre système, vous recevrez un email avec un
                            lien de réinitialisation dans quelques minutes.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
                <h3 class="text-xl font-semibold text-gray-800 mb-2 font-heading">Email envoyé !</h3>
                <p class="text-gray-600 mb-6">Un email contenant un lien de réinitialisation a été envoyé à votre
                    adresse. Veuillez vérifier votre boîte de réception.</p>
                <button onclick="closeSuccessModal()"
                    class="w-full px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors">
                    Compris
                </button>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        function submitForm() {
            // Validate
            const email = document.getElementById('email').value;
            if (!email || !validateEmail(email)) {
                showError('email-error', "Veuillez saisir une adresse email valide !");
                return;
            }
            hideError('email-error');

            // Before submit
            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = true;
            submitButton.innerHTML =
                    '<span class="flex items-center justify-center"><svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Traitement en cours ...</span>';

            let form = document.getElementById('forgot-password-form');
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
                    document.getElementById('success-modal').classList.remove('hidden');
                })
                .catch(error => {
                    document.getElementById('success-modal').classList.remove('hidden');
                });
        }

        function closeSuccessModal() {
            document.getElementById('success-modal').classList.add('hidden');
            window.location.href = "https://orthomaster.fr";
        }

        // Handle "enter"
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('forgot-password-form').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitForm();
                }
            });
        });
    </script>
@endsection
