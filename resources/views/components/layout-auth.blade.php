<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ChatSmart v1.0.0 modern WhatsApp gateway dashboard">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="author" content="ChatSmart Team">
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('chatsmart-theme');
                var theme = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark-theme' : 'light-theme');
                document.documentElement.classList.remove('light-theme', 'dark-theme', 'semi-dark', 'minimal-theme');
                document.documentElement.classList.add(theme);
            } catch (e) {
                document.documentElement.classList.add('light-theme');
            }
        })();
    </script>

    <title>{{ $title }} | ChatSmart v1.0.0</title>

    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/dark-theme.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/light-theme.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/chatsmart-theme.css') }}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
</head>

<body class="bg-login">
    @php
        $availableLocales = config('app.available_locales', []);
        $currentLocale = app()->getLocale();
        $currentLocaleMeta = $availableLocales[$currentLocale] ?? $availableLocales[config('app.fallback_locale', 'en')] ?? null;
    @endphp

    <div class="wrapper">
        <div class="container-fluid pt-3">
            <div class="d-flex justify-content-end">
                <div class="dropdown">
                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="authLanguageDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-globe"></i>
                        {{ $currentLocaleMeta['label'] ?? __('system.language') }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="authLanguageDropdown">
                        @foreach ($availableLocales as $locale => $meta)
                            <li>
                                <a class="dropdown-item" href="{{ route('language.switch', $locale) }}">
                                    <span class="flag-icon flag-icon-{{ $meta['flag'] }}"></span>
                                    {{ $meta['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{ $slot }}
    </div>

    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/chatsmart-theme.js') }}"></script>
    <script src="{{ asset('assets/js/pace.min.js') }}"></script>
</body>

</html>
