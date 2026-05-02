<x-layout-auth>
    @slot('title', __('system.login'))

    <main class="authentication-content mt-4">
        <div class="container-fluid">
            <div class="auth-shell">
                <div class="auth-brand">
                    <div class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></div>
                    <h1>ChatSmart v1.0.0</h1>
                    <p>Modern WhatsApp gateway untuk tim yang butuh dashboard lebih cepat, lebih rapi, dan lebih pintar.</p>
                </div>

                <div class="card auth-card shadow overflow-hidden">
                    <div class="card-body">
                        @if (session()->has('alert'))
                            <x-alert>
                                @slot('type', session('alert')['type'])
                                @slot('msg', session('alert')['msg'])
                            </x-alert>
                        @endif
                        <h5 class="card-title">{{ __('system.sign_in') }}</h5>
                        <p class="card-text mb-4">{{ __('system.auth_welcome') }}</p>
                        <form class="form-body" action="{{ route('login') }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="username" class="form-label">{{ __('system.username') }}</label>
                                    <div class="ms-auto position-relative">
                                        <div class="position-absolute top-50 translate-middle-y search-icon px-3">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                        <input type="text"
                                            class="form-control radius-30 ps-5 {{ $errors->has('username') ? 'is-invalid' : '' }}"
                                            id="username" name="username" placeholder="{{ __('system.username') }}" required>
                                    </div>
                                    <p class="text-danger">
                                        @error('username')
                                            {{ $message }}
                                        @enderror
                                    </p>
                                </div>
                                <div class="col-12">
                                    <label for="password" class="form-label">{{ __('system.enter_password') }}</label>
                                    <div class="ms-auto position-relative">
                                        <div class="position-absolute top-50 translate-middle-y search-icon px-3">
                                            <i class="bi bi-lock-fill"></i>
                                        </div>
                                        <input type="password" name="password" class="form-control radius-30 ps-5"
                                            id="password" placeholder="{{ __('system.enter_password') }}" required>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary radius-30">{{ __('system.sign_in') }}</button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <p class="mb-0">{{ __('system.no_account_yet') }} <a
                                            href="{{ route('register') }}">{{ __('system.sign_up_here') }}</a></p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-layout-auth>
