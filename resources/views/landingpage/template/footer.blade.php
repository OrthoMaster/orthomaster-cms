<footer class="bg-gray-800 text-gray-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- About Section -->
            <div>
                <div class="flex items-center space-x-2 mb-4">
                    <img src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/web/logo_white_no_bg_40.webp"
                        alt="OrthoMaster" width="30" height="30" />
                    <span class="text-lg text-white font-heading">OrthoMaster</span>
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
                            class="text-sm hover:text-primary transition-colors">À propos</a>
                    </li>

                    <li><a target="_blank" href="{{ route('web.legal-notice') }}"
                            class="text-sm hover:text-primary transition-colors">Mentions légales</a></li>
                    <li><a target="_blank" href="https://www.orthomaster.fr/terms-conditions"
                            class="text-sm hover:text-primary transition-colors">Conditions générales</a></li>
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
            <p class="text-sm text-gray-400">{{ setting('mail.copyright') }}</p>
        </div>
    </div>
</footer>
