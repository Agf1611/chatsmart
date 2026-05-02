@php
    $availableLocales = config('app.available_locales', []);
    $currentLocale = app()->getLocale();
    $currentLocaleMeta = $availableLocales[$currentLocale] ?? $availableLocales[config('app.fallback_locale', 'en')] ?? null;
@endphp

<header class="top-header">
    <nav class="navbar navbar-expand gap-3 align-items-center">
        <div class="mobile-toggle-icon fs-3">
            <i class="bi bi-list"></i>
        </div>

        <form class="searchbar">
            <div class="position-absolute top-50 translate-middle-y search-icon ms-3"><i class="bi bi-search"></i></div>
            <input class="form-control" type="text" placeholder="Search anything...">
            <span class="search-shortcut">Ctrl + K</span>
            <div class="position-absolute top-50 translate-middle-y search-close-icon"><i class="bi bi-x-lg"></i></div>
        </form>

        <div class="top-navbar-right ms-auto">
            <ul class="navbar-nav align-items-center gap-2">
                <li class="nav-item search-toggle-icon">
                    <a class="nav-link top-action" href="#">
                        <i class="bi bi-search"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <button type="button" class="top-action border-0" data-theme-toggle>
                        <i class="bi bi-moon-stars-fill" data-theme-icon></i>
                        <span class="d-none" data-theme-label>Dark</span>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="nav-link top-action position-relative" href="#">
                        <i class="bi bi-bell"></i>
                        <span class="notification-dot">3</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link top-action dropdown-toggle" href="#" id="languageDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-globe2"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                        @foreach ($availableLocales as $locale => $meta)
                            <li>
                                <a class="dropdown-item" href="{{ route('language.switch', $locale) }}">
                                    <span class="flag-icon flag-icon-{{ $meta['flag'] }}"></span>
                                    {{ $meta['label'] }}
                                    @if ($currentLocale === $locale)
                                        <i class="bi bi-check2 ms-2"></i>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
                <li class="nav-item dropdown dropdown-user-setting">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret user-chip" href="#"
                        data-bs-toggle="dropdown">
                        <span class="user-chip__avatar">
                            <img src="{{ asset('assets/images/avatars/avatar-1.png') }}" class="user-img" alt="">
                        </span>
                        <div class="user-chip__meta">
                            <strong>{{ Auth::user()->username }}</strong>
                            <span>{{ ucfirst(Auth::user()->level) }}</span>
                        </div>
                        <span class="user-chip__chevron">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="#">
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('assets/images/avatars/avatar-1.png') }}" alt=""
                                        class="rounded-circle" width="54" height="54">
                                    <div class="ms-3">
                                        <h6 class="mb-0 dropdown-user-name">{{ Auth::user()->username }}</h6>
                                        <small class="mb-0 dropdown-user-designation text-secondary">{{ Auth::user()->level }}</small>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('user.settings') }}">
                                <div class="d-flex align-items-center">
                                    <div class=""><i class="bi bi-gear-fill"></i></div>
                                    <div class="ms-3"><span>{{ __('system.settings') }}</span></div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form action="{{ route('logout') }}" method="post">
                                @csrf
                                <button class="dropdown-item" type="submit">
                                    <div class="d-flex align-items-center">
                                        <div class=""><i class="bi bi-box-arrow-right"></i></div>
                                        <div class="ms-3"><span>{{ __('system.logout') }}</span></div>
                                    </div>
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</header>
