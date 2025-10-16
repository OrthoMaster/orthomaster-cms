@extends('landingpage.template.index')

@section('title', $statusCode)

@section('content')
    <main class="font-body bg-gradient-to-br from-orange-50 to-orange-100 min-h-[80vh] flex items-center justify-center">
        <div class="w-full text-center">
            <div class="relative mb-8">
                <h1
                    class="font-heading text-8xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-primary to-orange-600 leading-none select-none animate-pulse">
                    {{ $statusCode }}
                </h1>
            </div>

            <!-- Message -->
            <div class="mb-8">
                <h2 class="font-heading text-3xl font-bold text-gray-600 mb-4">
                    {{ $message }}
                </h2>
            </div>
        </div>
    </main>
@endsection
