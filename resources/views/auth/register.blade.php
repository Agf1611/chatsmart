<x-layout-auth>
    <main class="authentication-content mt-4">
        <div class="container-fluid">
            @slot('title', __('system.register'))

            <div class="auth-shell">
                <div class="auth-brand">
                    <div class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></div>
                    <h1>ChatSmart v1.0.0</h1>
                    <p>Daftar akun baru terlebih dulu. Setelah itu admin akan meninjau, mengaktifkan akun, dan menentukan masa aktif akses Anda.</p>
                </div>

                @if (session()->has('alert'))
                    <x-alert>
                        @slot('type', session('alert')['type'])
                        @slot('msg', session('alert')['msg'])
                    </x-alert>
                @endif

                <div class="card auth-card shadow overflow-hidden">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('system.register') }}</h5>
                        <p class="card-text mb-4">Akun baru tidak langsung aktif. Silakan daftar, lalu tunggu persetujuan admin.</p>
                        <form class="form-body" action="{{ route('register') }}" method="POST">
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
                                    <label for="username" class="form-label">{{ __('system.email') }}</label>
                                    <div class="ms-auto position-relative">
                                        <div class="position-absolute top-50 translate-middle-y search-icon px-3">
                                            <i class="bi bi-envelope-fill"></i>
                                        </div>
                                        <input type="text"
                                            class="form-control radius-30 ps-5 {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                            id="email" name="email" placeholder="{{ __('system.email') }}" required>
                                    </div>
                                    <p class="text-danger">
                                        @error('email')
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
                                        <button type="submit" class="btn btn-primary radius-30">{{ __('system.register') }}</button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <p class="mb-0">{{ __('system.already_have_account') }} <a
                                            href="{{ route('login') }}">{{ __('system.sign_in_here') }}</a></p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-layout-auth>
