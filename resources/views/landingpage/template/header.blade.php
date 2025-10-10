<header class="bg-white shadow-sm">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo and Brand -->
            <div class="flex items-center space-x-2">
                <img src="https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/web/logo_orange_no_bg_40.webp"
                    alt="OrthoMaster" width="30" height="30" />
                <span class="text-xl text-primary font-heading">OrthoMaster</span>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="https://orthomaster.fr" class="text-gray-600 hover:text-primary transition-colors">Notre
                    site</a>

                <a href="{{ request()->route()->getName() == 'register.index' ? 'javascript:void(0);' : route('register.index') }}"
                    class="{{ request()->route()->getName() == 'register.index' ? 'text-primary font-medium' : 'text-gray-600 hover:text-primary transition-colors' }}">S'inscrire</a>

                <a href="{{ request()->route()->getName() !== 'register.index' ? 'javascript:void(0);' : route('password.request') }}"
                    class="{{ request()->route()->getName() !== 'register.index' ? 'text-primary font-medium' : 'text-gray-600 hover:text-primary transition-colors' }}">Réinitialiser</a>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden py-4">
            <div class="flex flex-col space-y-3 text-right">
                <a href="https://orthomaster.fr" class="text-gray-600 hover:text-primary transition-colors">Notre
                    site</a>

                <a href="{{ request()->route()->getName() == 'register.index' ? 'javascript:void(0);' : route('register.index') }}"
                    class="{{ request()->route()->getName() == 'register.index' ? 'text-primary font-medium' : 'text-gray-600 hover:text-primary transition-colors' }}">S'inscrire</a>

                <a href="{{ request()->route()->getName() !== 'register.index' ? 'javascript:void(0);' : route('password.request') }}"
                    class="{{ request()->route()->getName() !== 'register.index' ? 'text-primary font-medium' : 'text-gray-600 hover:text-primary transition-colors' }}">Réinitialiser</a>
            </div>
        </div>
    </nav>
</header>
