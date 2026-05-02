<!--start sidebar -->
<aside class="sidebar-wrapper" data-simplebar="true">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></div>
            <div class="brand-copy">
                <h4 class="brand-title">ChatSmart <small>v1.0.0</small></h4>
                <p class="brand-subtitle">WhatsApp Gateway</p>
            </div>
        </div>
        <div class="toggle-icon ms-auto"><i class="bi bi-list"></i></div>
    </div>

    <div class="sidebar-menu-area">
        <ul class="metismenu" id="menu">
            <li class="menu-label">Main Menu</li>
            <li class="{{ request()->is('home') ? 'active' : '' }}">
                <a href="{{ route('home') }}">
                    <div class="parent-icon"><i class="bi bi-house-door"></i></div>
                    <div class="menu-title">{{ __('system.dashboard') }}</div>
                </a>
            </li>
            <li class="{{ request()->is('file-manager') ? 'active' : '' }}">
                <a href="{{ route('file-manager') }}">
                    <div class="parent-icon"><i class="bi bi-folder2-open"></i></div>
                    <div class="menu-title">{{ __('system.file_manager') }}</div>
                </a>
            </li>
            <li class="{{ request()->is('phonebook') ? 'active' : '' }}">
                <a href="{{ route('phonebook') }}">
                    <div class="parent-icon"><i class="bi bi-people"></i></div>
                    <div class="menu-title">{{ __('system.phone_book') }}</div>
                </a>
            </li>
            <li>
                <a href="javascript:;" class="has-arrow">
                    <div class="parent-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="menu-title">{{ __('system.reports') }}</div>
                </a>
                <ul>
                    <li class="{{ request()->is('campaigns') ? 'active' : '' }}">
                        <a href="{{ route('campaigns') }}"><i class="bi bi-circle"></i>{{ __('system.campaign_blast') }}</a>
                    </li>
                    <li class="{{ request()->is('messages.history') ? 'active' : '' }}">
                        <a href="{{ route('messages.history') }}"><i class="bi bi-circle"></i>{{ __('system.messages_history') }}</a>
                    </li>
                </ul>
            </li>

            <x-select-device></x-select-device>

            @if (Session::has('selectedDevice'))
                <li class="{{ request()->is('autoreply*') ? 'active' : '' }}">
                    <a href="{{ route('autoreply') }}">
                        <div class="parent-icon"><i class="bi bi-chat-left-dots"></i></div>
                        <div class="menu-title">{{ __('system.autoreply') }}</div>
                    </a>
                </li>
                <li>
                    <a href="javascript:;" class="has-arrow">
                        <div class="parent-icon"><i class="bi bi-stars"></i></div>
                        <div class="menu-title">AI Bot</div>
                    </a>
                    <ul>
                        <li class="{{ request()->is('ai-bots*') ? 'active' : '' }}">
                            <a href="{{ route('ai-bots.index') }}"><i class="bi bi-circle"></i>Bot Profiles</a>
                        </li>
                        <li class="{{ request()->is('ai-conversations*') ? 'active' : '' }}">
                            <a href="{{ route('ai-conversations.index') }}"><i class="bi bi-circle"></i>AI Conversations</a>
                        </li>
                    </ul>
                </li>
                <li class="{{ url()->current() == route('campaign.create') ? 'mm-active' : '' }}">
                    <a href="{{ route('campaign.create') }}">
                        <div class="parent-icon"><i class="bi bi-plus-circle"></i></div>
                        <div class="menu-title">{{ __('system.create_campaign') }}</div>
                    </a>
                </li>
                <li class="{{ url()->current() == route('messagetest') ? 'mm-active' : '' }}">
                    <a href="{{ route('messagetest') }}">
                        <div class="parent-icon"><i class="bi bi-send"></i></div>
                        <div class="menu-title">{{ __('system.test_message') }}</div>
                    </a>
                </li>
            @endif

            <li class="menu-label">Developer</li>
            <li class="{{ url()->current() == route('rest-api') ? 'mm-active' : '' }}">
                <a href="{{ route('rest-api') }}">
                    <div class="parent-icon"><i class="bi bi-code-square"></i></div>
                    <div class="menu-title">{{ __('system.api_docs') }}</div>
                </a>
            </li>

            @if (Auth::user()->level == 'admin')
                <li class="menu-label">Settings</li>
                <li>
                    <a href="javascript:;" class="has-arrow">
                        <div class="parent-icon"><i class="bi bi-gear"></i></div>
                        <div class="menu-title">{{ __('system.admin') }}</div>
                    </a>
                    <ul>
                        <li class="{{ request()->is('admin.settings') ? 'active' : '' }}">
                            <a href="{{ route('admin.settings') }}"><i class="bi bi-circle"></i>{{ __('system.server_settings') }}</a>
                        </li>
                        <li class="{{ request()->is('admin/update') ? 'active' : '' }}">
                            <a href="{{ route('admin.update') }}"><i class="bi bi-circle"></i>{{ __('system.update') }}</a>
                        </li>
                        <li class="{{ request()->is('admin/operational-audit*') ? 'active' : '' }}">
                            <a href="{{ route('admin.operational-audit') }}"><i class="bi bi-circle"></i>Operational Audit</a>
                        </li>
                        <li class="{{ request()->is('admin/database-tools*') ? 'active' : '' }}">
                            <a href="{{ route('admin.database-tools') }}"><i class="bi bi-circle"></i>{{ __('system.database_tools') }}</a>
                        </li>
                        <li class="{{ request()->is('admin.manage-users') ? 'active' : '' }}">
                            <a href="{{ route('admin.manage-users') }}"><i class="bi bi-circle"></i>{{ __('system.manage_user') }}</a>
                        </li>
                    </ul>
                </li>
            @endif
        </ul>

        <div class="sidebar-promo">
            <div class="promo-orb"></div>
            <h6>Tingkatkan Performa</h6>
            <p>Dapatkan pengalaman ChatSmart yang lebih stabil, modern, dan siap dipakai untuk operasional harian.</p>
            <a href="{{ route('admin.update') }}" class="btn btn-primary btn-sm">Kelola Update</a>
        </div>

        <div class="sidebar-user-panel">
            <img src="{{ asset('assets/images/avatars/avatar-1.png') }}" alt="{{ Auth::user()->username }}">
            <div class="meta">
                <strong>{{ Auth::user()->username }}</strong>
                <span>{{ Auth::user()->level }} | ChatSmart</span>
            </div>
            <i class="bi bi-chevron-down"></i>
        </div>
    </div>
</aside>
<!--end sidebar -->
