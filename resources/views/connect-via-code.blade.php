<x-layout-dashboard title="Scan {{ $number->body }}">

    <h4 class="">Whatsapp Account {{ $number->body }}</h4>

    <div class="alert border-0 {{ $number->status === 'Connected' ? 'bg-light-success' : 'bg-light-info' }} alert-dismissible fade show py-2 connection-summary">
        <div class="d-flex align-items-center">
            <div class="fs-3 {{ $number->status === 'Connected' ? 'text-success' : 'text-info' }} connection-summary-icon">
                {{-- icon info --}}
                <i class="bi {{ $number->status === 'Connected' ? 'bi-check-circle-fill' : 'bi-info-circle-fill' }}"></i>
            </div>
            <div class="ms-3">
                <div class="{{ $number->status === 'Connected' ? 'text-success' : 'text-info' }} connection-summary-text">
                    {{ $number->status === 'Connected' ? 'WhatsApp already connected successfully.' : 'Dont leave your phone before connencted' }}
                </div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <div class="row">
        <div class="col-xl-12">
            <div class="card widget widget-stats-large">
                <div class="row">
                    <div class="col-xl-8">
                        <div class="widget-stats-large-chart-container">
                            <div class="card-header logoutbutton">


                            </div>
                            <div class="card-body">
                                <div id="apex-earnings"></div>
                                <div class="imageee text-center">
                                    @if (Auth::user()->is_expired_subscription)
                                        {{-- text --}}
                                        <img src="{{ asset('images/other/expired.png') }}" height="300px"
                                            alt="">
                                    @else
                                        <img src="{{ asset('assets/images/waiting.jpg') }}" height="300px"
                                            alt="">
                                    @endif
                                </div>
                                <div class="statusss text-center">
                                    @if (Auth::user()->is_expired_subscription)
                                        <button class="btn btn-danger   " type="button" disabled>
                                            Your subscription is expired. Please renew your subscription.
                                        </button>
                                    @else
                                        <button class="btn btn-primary" type="button" disabled>
                                            <span class="spinner-grow spinner-grow-sm" role="status"
                                                aria-hidden="true"></span>
                                            Witing For node server..
                                        </button>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="widget-stats-large-info-container">
                            <div class="card-header">
                                <h5 class="card-title">Whatsapp Info<span
                                        class="badge badge-info badge-style-light">Updated 5 min ago</span>
                                </h5>
                            </div>
                            <div class="card-body account">

                                <ul class="list-group account list-group-flush">
                                    <li class="list-group-item name">Nama : </li>
                                    <li class="list-group-item number">Nomor : </li>
                                    <li class="list-group-item device">Device : </li>
                                    <li class="list-group-item connection-state">Status : {{ $number->status ?: 'Waiting' }}</li>

                                </ul>
                                {{-- <div class="card bg-dark text-white">
                                    <div class="card-body" style="height: 300px; overflow-y: scroll;">
                                        <p class="card-text">Log :</p>
                                        <!-- Tambahkan log entry baru di sini -->
                                    </div>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layout-dashboard>
<script src="https://cdn.socket.io/4.6.0/socket.io.min.js"
    integrity="sha384-c79GN5VsunZvi+Q/WObgk2in0CbZsHnjEqvFxC5DxHn9lTfNce2WW6h2pH6u/kF+" crossorigin="anonymous">
</script>
<script>
    // if subscription not expired
    const is_expired_subscription = '{{ Auth::user()->is_expired_subscription }}';
    if (!is_expired_subscription) {
        const socketRuntime = @json($socketRuntime);
        const device = '{{ $number->body }}';
        const currentOrigin = window.location.origin;
        const currentHost = window.location.hostname;
        const configuredNodeUrl = (socketRuntime.nodeUrl || '').replace(/\/+$/, '');
        const localNodeUrl = (socketRuntime.localNodeUrl || '').replace(/\/+$/, '');
        const isLocalHost = /^(localhost|127\.0\.0\.1)$/i.test(currentHost);
        const socketEndpoint = isLocalHost
            ? (localNodeUrl || configuredNodeUrl || currentOrigin)
            : (configuredNodeUrl || currentOrigin);

        const socket = io(socketEndpoint, {
            path: '/socket.io',
            transports: ['polling', 'websocket'],
            upgrade: true,
            rememberUpgrade: false,
            tryAllTransports: true,
            timeout: 20000,
            reconnection: true,
            reconnectionAttempts: 5,
        });
        let isConnected = {{ $number->status === 'Connected' ? 'true' : 'false' }};

        function renderStatus(variant, text) {
            const icon = variant === 'success'
                ? 'bi-check-circle-fill'
                : (variant === 'danger'
                    ? 'bi-x-circle-fill'
                    : (variant === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'));

            $('.statusss').html(`
                <button class="btn btn-${variant}" type="button" disabled>
                    <i class="bi ${icon} me-1"></i>
                    ${text}
                </button>
            `);
        }

        function renderSummary(variant, text) {
            const wrapperClass = variant === 'success' ? 'bg-light-success' : (variant === 'danger' ? 'bg-light-danger' : 'bg-light-info');
            const textClass = variant === 'success' ? 'text-success' : (variant === 'danger' ? 'text-danger' : 'text-info');
            const icon = variant === 'success' ? 'bi-check-circle-fill' : (variant === 'danger' ? 'bi-x-circle-fill' : 'bi-info-circle-fill');

            $('.connection-summary')
                .removeClass('bg-light-success bg-light-danger bg-light-info')
                .addClass(wrapperClass);
            $('.connection-summary-icon')
                .removeClass('text-success text-danger text-info')
                .addClass(textClass)
                .html(`<i class="bi ${icon}"></i>`);
            $('.connection-summary-text')
                .removeClass('text-success text-danger text-info')
                .addClass(textClass)
                .text(text);
        }

        function renderConnectionState(text) {
            $('.connection-state').html(`Status : ${text}`);
        }

        socket.on('connect', () => {
            if (!isConnected) {
                renderStatus('info', 'Terhubung ke runtime Node, menunggu pairing code...');
                renderSummary('info', 'Runtime Node aktif. Tunggu kode pairing lalu masukkan ke WhatsApp Anda.');
                renderConnectionState('Waiting for pairing code');
            }
        });

        socket.on('connect_error', (error) => {
            isConnected = false;
            renderStatus('danger', `Gagal konek ke Node (${socketEndpoint}): ${error.message}`);
            renderSummary('danger', 'Runtime Node tidak bisa dihubungi. Periksa server Node atau konfigurasi URL.');
            renderConnectionState('Node unreachable');
        });

        socket.on('disconnect', (reason) => {
            if (!isConnected) {
                renderStatus('warning', `Koneksi Node terputus: ${reason}`);
                renderConnectionState('Disconnected');
            }
        });


        socket.emit('ConnectViaCode', '{{ $number->body }}')
        socket.on('code', ({
            token,
            data,
            message
        }) => {
            if (token == device) {
                isConnected = false;
                let code = data
                $('.imageee').html(` <h2 >${code}</h2>`)
                renderStatus('warning', `${message}`);
                renderSummary('info', 'Kode pairing sudah siap. Masukkan kode ini di WhatsApp sampai status berubah menjadi connected.');
                renderConnectionState('Pairing code ready');

            }

        })


        socket.on('connection-open', ({
            token,
            user,
            ppUrl
        }) => {
            if (token == device) {
                isConnected = true;

                $('.name').html(`Nama : ${user.name}`)
                $('.number').html(`Number : ${user.id}`)
                $('.device').html(`Device / Token : Not detected - ${token}`)
                $('.imageee').html(` <img src="${ppUrl}" height="300px" alt="">`)
                renderStatus('success', 'WhatsApp berhasil connected.');
                renderSummary('success', 'WhatsApp sudah connected dengan sukses. Anda sekarang bisa memakai device ini.');
                renderConnectionState('Connected');
                $('.logoutbutton').html(` <button class="btn btn-danger" class="logout"  id="logout"  onclick="logout({{ $number->body }})">
                                                   Logout
                                               </button>`)
            }
        })

        socket.on('Unauthorized', ({
            token
        }) => {
            if (token == device) {
                isConnected = false;
                renderStatus('danger', 'Unauthorized');
                renderSummary('danger', 'Sesi WhatsApp tidak valid. Anda perlu menghubungkan ulang device ini.');
                renderConnectionState('Unauthorized');
            }

        })
        socket.on('message', ({
            token,
            message
        }) => {
            if (token == device) {
                const isClosed = message.includes('Connection closed');
                const isLost = message.includes('Connection was lost');
                const variant = isClosed ? 'danger' : (isLost ? 'warning' : 'info');

                if (isClosed || isLost) {
                    isConnected = false;
                    renderConnectionState('Disconnected');
                }

                renderStatus(variant, message);
                renderSummary(variant === 'info' ? 'info' : variant, message);
                //if there is text connection close in message
                if (isClosed) {
                    // count 5 second
                    let count = 5;
                    //set interval
                    let interval = setInterval(() => {
                        //if count is 0
                        if (count == 0) {
                            //clear interval
                            clearInterval(interval);
                            //reload page
                            location.reload();
                        }
                        //change text
                        renderStatus('danger', `${message} in ${count} second`);
                        //count down
                        count--;
                    }, 1000);

                }
            }



        });




        function logout(device) {
            socket.emit('LogoutDevice', device)
        }
    }
</script>
