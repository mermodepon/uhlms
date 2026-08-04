<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/filament/filament/app.css">
    <style>
        :root { color-scheme: light; --security-green: #00491e; --security-green-hover: #02681e; --security-ink: #172033; --security-muted: #536176; --security-border: #d5dde7; }
        .security-page { box-sizing: border-box; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 2rem 1rem; background: linear-gradient(135deg, #edf5ef 0%, #f8fafc 48%, #fff9e5 100%); color: var(--security-ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .security-card { width: min(100%, 34rem); overflow: hidden; border: 1px solid var(--security-border); border-radius: 1.25rem; background: #fff; box-shadow: 0 24px 60px rgb(23 32 51 / 16%); }
        .security-card__header { padding: 2rem 2rem 1.5rem; border-bottom: 1px solid #e8edf2; text-align: center; }
        .security-card__body { padding: 1.75rem 2rem 2rem; }
        .security-logo { display: block; width: auto; height: 4rem; margin: 0 auto 1rem; border-radius: .75rem; }
        .security-title { margin: 0; color: var(--security-green); font-size: 1.65rem; font-weight: 750; letter-spacing: -.025em; line-height: 1.25; }
        .security-description { max-width: 28rem; margin: .65rem auto 0; color: var(--security-muted); font-size: .95rem; line-height: 1.55; }
        .security-alert { margin-bottom: 1.25rem; padding: .9rem 1rem; border-radius: .75rem; font-size: .9rem; line-height: 1.45; }
        .security-alert--success { border: 1px solid #9ed5b1; background: #effaf2; color: #135c2b; }
        .security-alert--error { border: 1px solid #f1aaa5; background: #fff2f1; color: #a11d18; }
        .security-card label { margin-bottom: .45rem; color: #263449; font-size: .9rem; font-weight: 650; }
        .security-card input { box-sizing: border-box; width: 100%; min-height: 3rem; padding: .7rem .85rem; border: 1px solid #9aa8ba; border-radius: .6rem; background: #fff; color: var(--security-ink); font: inherit; outline: none; }
        .security-card input:focus { border-color: var(--security-green); box-shadow: 0 0 0 3px rgb(0 73 30 / 16%); }
        .security-card form > * + * { margin-top: 1rem; }
        .security-card button[type="submit"] { display: inline-flex; width: 100%; min-height: 3rem; align-items: center; justify-content: center; padding: .7rem 1rem; border: 0; border-radius: .6rem; background: var(--security-green); color: #fff; cursor: pointer; font: inherit; font-weight: 700; line-height: 1.25; }
        .security-card button[type="submit"]:hover { background: var(--security-green-hover); }
        .security-card button[type="submit"]:focus-visible { outline: 3px solid rgb(255 198 0 / 70%); outline-offset: 3px; }
        .security-card a { color: var(--security-green); font-weight: 650; }
        .security-profile-link { display: block; margin-top: 1.5rem; text-align: center; font-size: .9rem; text-decoration: none; }
        .security-profile-link:hover { text-decoration: underline; }
        .security-signout-form { margin-top: 1.75rem !important; padding-top: 1.25rem; border-top: 1px solid #e8edf2; text-align: center; }
        .security-card .security-signout-button { width: auto; min-height: auto; padding: .25rem .5rem; background: transparent; color: #536176; font-size: .85rem; font-weight: 600; text-decoration: underline; }
        .security-card .security-signout-button:hover { background: transparent; color: #172033; }
        @media (max-width: 480px) { .security-page { padding: 1rem; align-items: flex-start; } .security-card__header, .security-card__body { padding-right: 1.25rem; padding-left: 1.25rem; } }
    </style>
</head>
<body>
    <main class="security-page">
        <section class="security-card">
            <div class="security-card__header">
                <img src="/images/uh_logo.jpg" alt="UH Lodging Management System" class="security-logo">
                <h1 class="security-title">{{ $title }}</h1>
                @isset($description)
                    <p class="security-description">{{ $description }}</p>
                @endisset
            </div>

            <div class="security-card__body">
                @if(session('status'))
                    <div class="security-alert security-alert--success">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                    <div class="security-alert security-alert--error">{{ $errors->first() }}</div>
                @endif

                {{ $slot }}
            </div>
        </section>
    </main>
</body>
</html>
