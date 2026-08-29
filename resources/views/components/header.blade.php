@php
    $availableLocales = config('app.available_locales', []);
    $currentLocale = app()->getLocale();
    $isAdmin = Auth::check() && Auth::user()->level === 'admin';
    $pendingApprovalUsers = $isAdmin
        ? \App\Models\User::where('level', 'user')->where('status', 'inactive')->latest()->take(5)->get()
        : collect();
    $pendingApprovalCount = $isAdmin
        ? \App\Models\User::where('level', 'user')->where('status', 'inactive')->count()
        : 0;

    $pageLabel = 'Workspace';
    if (request()->routeIs('home')) $pageLabel = 'Dashboard';
    elseif (request()->routeIs('autoreply*')) $pageLabel = 'Auto Reply';
    elseif (request()->routeIs('ai-bots*')) $pageLabel = 'AI Bot';
    elseif (request()->routeIs('ai-conversations*')) $pageLabel = 'Histori AI';
    elseif (request()->routeIs('campaign*')) $pageLabel = 'Campaign';
    elseif (request()->routeIs('messages.*')) $pageLabel = 'Riwayat Pesan';
    elseif (request()->routeIs('phonebook')) $pageLabel = 'Kontak';
    elseif (request()->routeIs('file-manager')) $pageLabel = 'File Manager';
@endphp

<header class="top-header">
    <nav class="navbar navbar-expand gap-3 align-items-center">
        <button type="button" class="mobile-toggle-icon border-0" aria-label="Buka navigasi">
            <i class="bi bi-list"></i>
        </button>

        <div class="cs-header-context">
            <strong>{{ $pageLabel }}</strong>
            <span>
                @if (session()->has('selectedDevice'))
                    <i class="bi bi-phone"></i>{{ session('selectedDevice.device_body') }}
                @else
                    ChatSmart Workspace
                @endif
            </span>
        </div>

        <div class="top-navbar-right ms-auto">
            <ul class="navbar-nav align-items-center gap-2">
                <li class="nav-item">
                    <button type="button" class="top-action border-0" data-theme-toggle>
                        <i class="bi bi-moon-stars" data-theme-icon></i>
                        <span class="d-none" data-theme-label>Dark</span>
                    </button>
                </li>

                @if ($isAdmin)
                    <li class="nav-item dropdown">
                        <a class="nav-link top-action position-relative dropdown-toggle dropdown-toggle-nocaret" href="#"
                            id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                            aria-label="Notifikasi">
                            <i class="bi bi-bell"></i>
                            @if ($pendingApprovalCount > 0)
                                <span class="notification-dot">{{ $pendingApprovalCount > 9 ? '9+' : $pendingApprovalCount }}</span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                            @forelse ($pendingApprovalUsers as $pendingUser)
                                <li>
                                    <a class="dropdown-item" href="{{ route('admin.manage-users') }}">
                                        <div class="fw-semibold">{{ $pendingUser->username }}</div>
                                        <small class="text-secondary">Menunggu persetujuan admin</small>
                                    </a>
                                </li>
                            @empty
                                <li><span class="dropdown-item-text text-secondary">Tidak ada notifikasi baru.</span></li>
                            @endforelse
                            @if ($pendingApprovalUsers->isNotEmpty())
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-primary" href="{{ route('admin.manage-users') }}">Kelola pengguna</a></li>
                            @endif
                        </ul>
                    </li>
                @endif

                @if (count($availableLocales) > 1)
                    <li class="nav-item dropdown cs-language-menu">
                        <a class="nav-link top-action dropdown-toggle dropdown-toggle-nocaret" href="#"
                            id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                            aria-label="Pilih bahasa">
                            <i class="bi bi-globe2"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                            @foreach ($availableLocales as $locale => $meta)
                                <li>
                                    <a class="dropdown-item" href="{{ route('language.switch', $locale) }}">
                                        {{ $meta['label'] }}
                                        @if ($currentLocale === $locale)<i class="bi bi-check2 ms-2"></i>@endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif

                <li class="nav-item dropdown dropdown-user-setting">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret user-chip" href="#"
                        data-bs-toggle="dropdown" aria-label="Buka menu pengguna">
                        <span class="user-chip__avatar">
                            <img src="{{ asset('assets/images/avatars/avatar-1.png') }}" class="user-img"
                                alt="{{ Auth::user()->username }}">
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="cs-user-menu-heading">
                            <strong>{{ Auth::user()->username }}</strong>
                            <span>{{ ucfirst(Auth::user()->level) }}</span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('user.settings') }}">
                                <i class="bi bi-gear"></i> Pengaturan akun
                            </a>
                        </li>
                        <li>
                            <form action="{{ route('logout') }}" method="post">
                                @csrf
                                <button class="dropdown-item text-danger" type="submit">
                                    <i class="bi bi-box-arrow-right"></i> Keluar
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</header>
