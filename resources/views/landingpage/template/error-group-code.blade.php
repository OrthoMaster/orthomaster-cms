@extends('landingpage.template.index')

@section('title', $statusCode)

@section('content')
    <main class="font-body bg-gradient-to-br from-orange-50 to-orange-100 min-h-[80vh] flex items-center justify-center">
        <div class="w-full text-center">
            <div class="relative mb-6">
                <h1
                    class="font-heading text-5xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-primary to-orange-600 leading-none select-none animate-pulse">
                    OUPS ...
                </h1>
                <h2 class="font-heading text-2xl font-bold text-gray-600">
                    Ce lien n’est plus valable.
                </h2>
            </div>

            <!-- Message -->
            <div class="mb-4">
                <p class="font-sans text-base text-lg text-gray-500">Si vous souhaitez modifier les accès à OrthoMaster
                    <br />comme ajouter / retirer un collaborateur ou modifier des coordonnées,
                    <br />rendez-vous sur <b>Mon espace</b> dans l’application mobile.
                </p>
                <p class="font-sans text-base text-lg text-gray-500 mt-4">
                    Ou contactez-nous : <a href="mailto:info@orthomaster.fr" class="font-semibold">info@orthomaster.fr</a></p>
            </div>
        </div>
    </main>
@endsection
