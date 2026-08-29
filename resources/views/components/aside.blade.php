<aside class="sidebar-wrapper" data-simplebar="true">
    <div class="sidebar-header">
        <a href="{{ route('home') }}" class="sidebar-brand">
            <span class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></span>
            <span class="brand-copy">
                <span class="brand-title">ChatSmart</span>
                <span class="brand-subtitle">WhatsApp Workspace</span>
            </span>
        </a>
        <button type="button" class="toggle-icon ms-auto border-0" aria-label="Kecilkan navigasi">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>

    <div class="sidebar-menu-area">
        <ul class="metismenu" id="menu">
            <li class="menu-label">Workspace</li>
            <li class="{{ request()->routeIs('home') ? 'active' : '' }}">
                <a href="{{ route('home') }}">
                    <span class="parent-icon"><i class="bi bi-grid"></i></span>
                    <span class="menu-title">Dashboard</span>
                </a>
            </li>

            <x-select-device></x-select-device>

            @if (Session::has('selectedDevice'))
                <li class="{{ request()->routeIs('messagetest') ? 'active' : '' }}">
                    <a href="{{ route('messagetest') }}">
                        <span class="parent-icon"><i class="bi bi-send"></i></span>
                        <span class="menu-title">Kirim Pesan</span>
                    </a>
                </li>
                <li class="{{ request()->routeIs('autoreply*') ? 'active' : '' }}">
                    <a href="{{ route('autoreply') }}">
                        <span class="parent-icon"><i class="bi bi-reply"></i></span>
                        <span class="menu-title">Auto Reply</span>
                    </a>
                </li>
                <li class="{{ request()->routeIs('campaign.create') ? 'active' : '' }}">
                    <a href="{{ route('campaign.create') }}">
                        <span class="parent-icon"><i class="bi bi-megaphone"></i></span>
                        <span class="menu-title">Buat Campaign</span>
                    </a>
                </li>
            @endif

            <li class="{{ request()->routeIs('ai-bots*', 'ai-conversations*') ? 'mm-active' : '' }}">
                <a href="javascript:;" class="has-arrow">
                    <span class="parent-icon"><i class="bi bi-stars"></i></span>
                    <span class="menu-title">AI Assistant</span>
                </a>
                <ul>
                    <li class="{{ request()->routeIs('ai-bots*') ? 'active' : '' }}">
                        <a href="{{ route('ai-bots.index') }}"><i class="bi bi-circle"></i>Profil Bot</a>
                    </li>
                    <li class="{{ request()->routeIs('ai-conversations*') ? 'active' : '' }}">
                        <a href="{{ route('ai-conversations.index') }}"><i class="bi bi-circle"></i>Histori Percakapan</a>
                    </li>
                </ul>
            </li>

            <li class="menu-label">Kelola</li>
            <li class="{{ request()->routeIs('phonebook') ? 'active' : '' }}">
                <a href="{{ route('phonebook') }}">
                    <span class="parent-icon"><i class="bi bi-people"></i></span>
                    <span class="menu-title">Kontak</span>
                </a>
            </li>
            <li class="{{ request()->routeIs('campaigns') ? 'active' : '' }}">
                <a href="{{ route('campaigns') }}">
                    <span class="parent-icon"><i class="bi bi-broadcast"></i></span>
                    <span class="menu-title">Campaign</span>
                </a>
            </li>
            <li class="{{ request()->routeIs('messages.history*') ? 'active' : '' }}">
                <a href="{{ route('messages.history') }}">
                    <span class="parent-icon"><i class="bi bi-clock-history"></i></span>
                    <span class="menu-title">Riwayat Pesan</span>
                </a>
            </li>
            <li class="{{ request()->routeIs('file-manager') ? 'active' : '' }}">
                <a href="{{ route('file-manager') }}">
                    <span class="parent-icon"><i class="bi bi-folder2"></i></span>
                    <span class="menu-title">File Manager</span>
                </a>
            </li>

            <li class="menu-label">Integrasi</li>
            <li class="{{ request()->routeIs('rest-api') ? 'active' : '' }}">
                <a href="{{ route('rest-api') }}">
                    <span class="parent-icon"><i class="bi bi-code-square"></i></span>
                    <span class="menu-title">Dokumentasi API</span>
                </a>
            </li>
            <li class="{{ request()->routeIs('user.settings') ? 'active' : '' }}">
                <a href="{{ route('user.settings') }}">
                    <span class="parent-icon"><i class="bi bi-key"></i></span>
                    <span class="menu-title">API Key & Akun</span>
                </a>
            </li>

            @if (Auth::user()->level === 'admin')
                <li class="menu-label">Administrasi</li>
                <li class="{{ request()->routeIs('admin.*') ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow">
                        <span class="parent-icon"><i class="bi bi-gear"></i></span>
                        <span class="menu-title">Pengaturan</span>
                    </a>
                    <ul>
                        <li class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                            <a href="{{ route('admin.settings') }}"><i class="bi bi-circle"></i>Server</a>
                        </li>
                        <li class="{{ request()->routeIs('admin.manage-users') ? 'active' : '' }}">
                            <a href="{{ route('admin.manage-users') }}"><i class="bi bi-circle"></i>Pengguna</a>
                        </li>
                        <li class="{{ request()->routeIs('admin.operational-audit') ? 'active' : '' }}">
                            <a href="{{ route('admin.operational-audit') }}"><i class="bi bi-circle"></i>Audit Operasional</a>
                        </li>
                        <li class="{{ request()->routeIs('admin.database-tools') ? 'active' : '' }}">
                            <a href="{{ route('admin.database-tools') }}"><i class="bi bi-circle"></i>Database</a>
                        </li>
                        <li class="{{ request()->routeIs('admin.update') ? 'active' : '' }}">
                            <a href="{{ route('admin.update') }}"><i class="bi bi-circle"></i>Update</a>
                        </li>
                    </ul>
                </li>
            @endif
        </ul>

        <a href="{{ route('user.settings') }}" class="cs-sidebar-account">
            <img src="{{ asset('assets/images/avatars/avatar-1.png') }}" alt="{{ Auth::user()->username }}">
            <span><strong>{{ Auth::user()->username }}</strong><small>{{ ucfirst(Auth::user()->level) }}</small></span>
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</aside>
