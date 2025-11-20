@extends('landingpage.template.index')

@section('title', 'Mentions légales')

@section('content')
    <main class="bg-white flex-grow py-8 px-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 my-10">
            <!-- Header -->
            <div class="mb-10">
                <h1 class="text-3xl font-semibold text-gray-800 mb-0 font-heading">Mentions légales</h1>
                <p class="text-lg text-gray-600">Editeur du site internet et de l’application ORTHOMASTER – QUE CHOISIR.
                </p>
            </div>
            <div class="text-lg text-gray-500">
                <p>Orthomaster SAS au capital social de 25 000 euros.</p>
                <p>10 rue Saint Florent</p>
                <p>67200 Strasbourg</p>
                <p>Siret : 90918632200014</p>
                <p>TVA : FR70909186322</p>
                <p>Contact : <a href="mailto:info@orthomaster.fr">info@orthomaster.fr</a></p>

                <br />
                <p>Directeur de la publication :</p>
                <p class="text-gray-600 pl-2">Guillaume LE en qualité de Président d’OrthoMaster.</p>

                <br />
                <p>Hébergeur :</p>
                <p class="text-gray-600 pl-2">AWS - 31 PL DES COROLLES TOUR CARPE DIEM 92400 COURBEVOIE.</p>

            </div>
        </div>
    </main>
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
    </script>
@endsection
