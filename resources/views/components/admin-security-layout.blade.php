<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/filament/filament/app.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-950 dark:bg-gray-950 dark:text-white">
    <main class="mx-auto flex min-h-screen w-full max-w-xl items-center px-4 py-10">
        <section class="w-full rounded-2xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-8">
            <div class="mb-6 text-center">
                <img src="/images/uh_logo.jpg" alt="UH Lodging Management System" class="mx-auto mb-4 h-16 w-auto rounded-lg">
                <h1 class="text-2xl font-bold">{{ $title }}</h1>
                @isset($description)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
                @endisset
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-lg bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            {{ $slot }}
        </section>
    </main>
</body>
</html>
