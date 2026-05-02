@php
    $deviceLimit = max((int) $user->limit_device, 1);
    $deviceUsage = $user->limit_device > 0 ? min(100, (int) round(($user->devices_count / $deviceLimit) * 100)) : 0;
    $welcomeFeatures = ['Mudah digunakan', 'Performa tinggi', 'Aman & terpercaya'];
@endphp

<x-layout-dashboard :title="__('system.dashboard')">
    <div class="dashboard-shell">
        @if (session()->has('alert'))
            <x-alert>
                @slot('type', session('alert')['type'])
                @slot('msg', session('alert')['msg'])
            </x-alert>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger surface-card mb-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!empty($operational['alerts']))
            @foreach ($operational['alerts'] as $alert)
                <div class="alert alert-{{ $alert['level'] }} surface-card mb-0">
                    <strong>{{ $alert['title'] }}</strong>
                    <div class="small mt-1">{{ $alert['message'] }}</div>
                </div>
            @endforeach
        @endif

        <section class="surface-card dashboard-welcome-card">
            <button type="button" class="welcome-dismiss" data-dismiss-welcome>
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="welcome-copy">
                <div class="welcome-badge">
                    <i class="bi bi-info-lg"></i>
                </div>
                <div class="welcome-content">
                    <h2>Selamat datang di ChatSmart v1 👋</h2>
                    <p>Platform WhatsApp Gateway modern untuk komunikasi bisnis tanpa batas dengan dashboard yang lebih
                        cepat, rapi, dan nyaman dipakai setiap hari.</p>

                    <div class="welcome-features">
                        @foreach ($welcomeFeatures as $feature)
                            <span><i class="bi bi-check2"></i>{{ $feature }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="welcome-art" aria-hidden="true">
                <div class="chat-bubble one"></div>
                <div class="chat-bubble two"></div>
                <div class="chat-bubble three"></div>
                <div class="welcome-phone"></div>
                <div class="welcome-platform"></div>
            </div>
        </section>

        <section class="row g-4 row-cols-1 row-cols-md-2 row-cols-xl-4">
            <div class="col">
                <div class="surface-card stats-card">
                    <div class="stats-card__row">
                        <div class="stats-card__icon is-green"><i class="bi bi-phone"></i></div>
                        <div class="flex-grow-1">
                            <p class="stats-card__eyebrow">{{ __('system.total_devices') }}</p>
                            <h3 class="stats-card__value">{{ $user->devices_count }}</h3>
                            <p class="stats-card__subtext">{{ __('system.your_device_limit', ['count' => $user->limit_device]) }}</p>
                            <div class="usage-progress"><span style="width: {{ $deviceUsage }}%"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="surface-card stats-card">
                    <div class="stats-card__row">
                        <div class="stats-card__icon is-yellow"><i class="bi bi-broadcast"></i></div>
                        <div class="flex-grow-1">
                            <p class="stats-card__eyebrow">{{ __('system.blast_bulk') }}</p>
                            <div class="stats-badges">
                                <span class="badge bg-warning text-dark">{{ $user->blasts_pending }} {{ __('system.wait') }}</span>
                                <span class="badge bg-success">{{ $user->blasts_success }} {{ __('system.sent') }}</span>
                                <span class="badge bg-danger">{{ $user->blasts_failed }} {{ __('system.fail') }}</span>
                            </div>
                            <p class="stats-card__subtext">{{ __('system.from_campaigns', ['count' => $user->campaigns_count]) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="surface-card stats-card">
                    <div class="stats-card__row">
                        <div class="stats-card__icon is-purple"><i class="bi bi-gem"></i></div>
                        <div class="flex-grow-1">
                            <p class="stats-card__eyebrow">{{ __('system.subscription_status') }}</p>
                            <h3 class="stats-card__value">{{ $user->subscription_status }}</h3>
                            <p class="stats-card__subtext">{{ __('system.expired_on', ['date' => $user->expired_subscription_status]) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="surface-card stats-card">
                    <div class="stats-card__row">
                        <div class="stats-card__icon is-blue"><i class="bi bi-chat-dots"></i></div>
                        <div class="flex-grow-1">
                            <p class="stats-card__eyebrow">{{ __('system.all_messages_sent') }}</p>
                            <h3 class="stats-card__value">{{ $user->message_histories_count }}</h3>
                            <p class="stats-card__subtext">{{ __('system.from_message_histories') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4 row-cols-1 row-cols-md-2 row-cols-xl-4">
            @foreach ($operational['health'] as $healthCard)
                <div class="col">
                    <div class="surface-card stats-card h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <p class="stats-card__eyebrow">{{ $healthCard['label'] }}</p>
                                <h3 class="stats-card__value text-capitalize">{{ $healthCard['status'] }}</h3>
                                <p class="stats-card__subtext mb-0">{{ $healthCard['message'] }}</p>
                            </div>
                            <span class="badge {{ $healthCard['badge_class'] }}">{{ ucfirst($healthCard['status']) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="surface-card">
            <div class="account-shell__header">
                <div>
                    <p class="section-kicker">Operational Snapshot</p>
                    <h3 class="section-title">Ringkasan operasional hari ini</h3>
                    <div class="section-line"></div>
                </div>
                @if (auth()->user()->level === 'admin')
                    <a href="{{ route('admin.operational-audit') }}" class="btn btn-outline-primary chatsmart-btn">
                        <i class="bi bi-clipboard-data"></i> Audit Operasional
                    </a>
                @endif
            </div>
            <div class="row g-3">
                <div class="col-md-4 col-xl-2">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Chat aktif hari ini</div>
                        <div class="fs-4 fw-semibold">{{ $operational['metrics']['incoming_active_chats_today'] }}</div>
                    </div>
                </div>
                <div class="col-md-4 col-xl-2">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Pesan incoming tercatat</div>
                        <div class="fs-4 fw-semibold">{{ $operational['metrics']['incoming_messages_tracked_today'] }}</div>
                    </div>
                </div>
                <div class="col-md-4 col-xl-2">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Auto reply sukses</div>
                        <div class="fs-4 fw-semibold text-success">{{ $operational['metrics']['auto_reply_success_today'] }}</div>
                    </div>
                </div>
                <div class="col-md-4 col-xl-2">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Auto reply gagal</div>
                        <div class="fs-4 fw-semibold text-danger">{{ $operational['metrics']['auto_reply_failed_today'] }}</div>
                    </div>
                </div>
                <div class="col-md-4 col-xl-2">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">AI fallback / event</div>
                        <div class="fs-4 fw-semibold text-warning">{{ $operational['metrics']['ai_fallback_today'] }}</div>
                    </div>
                </div>
                <div class="col-md-4 col-xl-2">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">Chat dipause</div>
                        <div class="fs-4 fw-semibold">{{ $operational['metrics']['paused_conversations'] }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="surface-card account-shell">
            <div class="account-shell__header">
                <div>
                    <p class="section-kicker">Connection Center</p>
                    <h3 class="section-title">{{ __('system.whatsapp_account') }}</h3>
                    <div class="section-line"></div>
                </div>

                <button type="button" class="btn btn-primary chatsmart-btn" data-bs-toggle="modal"
                    data-bs-target="#addDevice">
                    <i class="bi bi-plus-lg"></i> {{ __('system.add_device') }}
                </button>
            </div>

            <div class="table-responsive device-table">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>{{ __('system.number') }}</th>
                            <th class="text-nowrap">Webhook URL</th>
                            <th>{{ __('system.read') }}</th>
                            <th class="text-nowrap">{{ __('system.reject_call') }}</th>
                            <th>{{ __('system.online') }}</th>
                            <th>{{ __('system.typing_wh') }}</th>
                            <th>{{ __('system.sent') }}</th>
                            <th>{{ __('system.status') }}</th>
                            <th class="text-end">{{ __('system.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($numbers->total() == 0)
                            <x-no-data colspan="10" :text="__('system.no_device_added_yet')" />
                        @endif

                        @foreach ($numbers as $number)
                            @php
                                $webhookRead = $number['webhook_read'] ?? false;
                                $webhookRejectCall = $number['webhook_reject_call'] ?? false;
                                $setAvailable = $number['set_available'] ?? false;
                                $webhookTyping = $number['webhook_typing'] ?? false;
                                $isConnected = $number['status'] === 'Connected';
                            @endphp
                            <tr>
                                <td><span class="device-index">{{ $loop->iteration }}</span></td>
                                <td>
                                    <div class="number-stack">
                                        <span class="number-stack__icon"><i class="bi bi-whatsapp"></i></span>
                                        <div>
                                            <strong>{{ $number['body'] }}</strong>
                                            <span>ChatSmart Device</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <form action="" method="post">
                                        @csrf
                                        <input type="text" class="form-control table-input webhook-url-form"
                                            data-id="{{ $number['body'] }}" value="{{ $number['webhook'] }}">
                                    </form>
                                </td>
                                <td>
                                    <div class="form-check form-switch m-0">
                                        <input data-url="{{ route('setHookRead') }}" class="form-check-input toggle-read"
                                            type="checkbox" data-id="{{ $number['body'] }}"
                                            {{ $webhookRead ? 'checked' : '' }} />
                                        <label class="form-check-label">{{ $webhookRead ? __('system.yes') : __('system.no') }}</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch m-0">
                                        <input data-url="{{ route('setHookReject') }}"
                                            class="form-check-input toggle-reject" type="checkbox"
                                            data-id="{{ $number['body'] }}"
                                            {{ $webhookRejectCall ? 'checked' : '' }} />
                                        <label class="form-check-label">{{ $webhookRejectCall ? __('system.yes') : __('system.no') }}</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch m-0">
                                        <input data-url="{{ route('setAvailable') }}"
                                            class="form-check-input toggle-available" type="checkbox"
                                            data-id="{{ $number['body'] }}" {{ $setAvailable ? 'checked' : '' }} />
                                        <label class="form-check-label">{{ $setAvailable ? __('system.yes') : __('system.no') }}</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch m-0">
                                        <input data-url="{{ route('setHookTyping') }}" class="form-check-input toggle-typing"
                                            type="checkbox" data-id="{{ $number['body'] }}"
                                            {{ $webhookTyping ? 'checked' : '' }} />
                                        <label class="form-check-label">{{ $webhookTyping ? __('system.yes') : __('system.no') }}</label>
                                    </div>
                                </td>
                                <td>{{ $number['message_sent'] }}</td>
                                <td>
                                    <span class="status-pill {{ $isConnected ? 'is-connected' : 'is-disconnected' }}">
                                        {{ $number['status'] }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="action-group justify-content-end">
                                        <a href="{{ route('connect-via-code', $number->body) }}"
                                            class="action-btn is-primary" data-bs-toggle="tooltip"
                                            title="{{ __('system.connect_via_code') }}">
                                            <i class="bi bi-phone"></i>
                                        </a>
                                        <a href="{{ route('scan', $number->body) }}" class="action-btn is-dark"
                                            data-bs-toggle="tooltip" title="{{ __('system.connect_via_qr') }}">
                                            <i class="bi bi-qr-code"></i>
                                        </a>
                                        <form action="{{ route('deleteDevice') }}" method="POST">
                                            @method('delete')
                                            @csrf
                                            <input name="deviceId" type="hidden" value="{{ $number['id'] }}">
                                            <button type="submit" name="delete" class="action-btn is-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <nav aria-label="Page navigation example">
                <ul class="pagination">
                    <li class="page-item {{ $numbers->currentPage() == 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $numbers->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a>
                    </li>

                    @for ($i = 1; $i <= $numbers->lastPage(); $i++)
                        <li class="page-item {{ $numbers->currentPage() == $i ? 'active' : '' }}">
                            <a class="page-link" href="{{ $numbers->url($i) }}">{{ $i }}</a>
                        </li>
                    @endfor

                    <li class="page-item {{ $numbers->currentPage() == $numbers->lastPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $numbers->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </section>

    </div>

    <div class="modal fade" id="addDevice" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('system.add_device') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="{{ __('system.close') }}"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('addDevice') }}" method="POST">
                        @csrf
                        <label for="sender" class="form-label">{{ __('system.number') }}</label>
                        <input type="number" name="sender" class="form-control" id="nomor" required>
                        <p class="text-small text-danger mt-2">{{ __('system.use_country_code') }}</p>
                        <label for="urlwebhook" class="form-label mt-2">{{ __('system.link_webhook') }}</label>
                        <input type="text" name="urlwebhook" class="form-control" id="urlwebhook">
                        <p class="text-small text-danger mt-2">{{ __('system.optional') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">{{ __('system.cancel') }}</button>
                    <button type="submit" name="submit" class="btn btn-primary">{{ __('system.save') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-layout-dashboard>
<script>
    var typingTimer;
    var doneTypingInterval = 1000;
    const i18n = {
        webhookUpdated: @json(__('system.webhook_updated')),
        failedToUpdate: @json(__('system.failed_to_update')),
        yes: @json(__('system.yes')),
        no: @json(__('system.no')),
    };

    $('.webhook-url-form').on('keyup', function() {
        clearTimeout(typingTimer);
        let value = $(this).val();
        let number = $(this).data('id');

        typingTimer = setTimeout(function() {
            $.ajax({
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{ route('setHook') }}',
                data: {
                    csrf: $('meta[name="csrf-token"]').attr('content'),
                    number: number,
                    webhook: value
                },
                dataType: 'json',
                success: () => {
                    toastr.success(i18n.webhookUpdated);
                },
                error: (err) => {
                    console.log(err);
                }
            })
        }, doneTypingInterval);
    });

    const bindToggleAction = (selector, fieldName) => {
        $(selector).on("click", function() {
            let dataId = $(this).data("id");
            let isChecked = $(this).is(":checked");
            let url = $(this).data("url");
            $.ajax({
                url: url,
                type: "POST",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                data: {
                    [fieldName]: isChecked ? "1" : "0",
                    id: dataId,
                },
                success: function(result) {
                    let label = $(`${selector}[data-id="${dataId}"]`).parent().find("label");
                    if (result.error) {
                        toastr['error'](result.msg || i18n.failedToUpdate);
                        $(`${selector}[data-id="${dataId}"]`).prop("checked", !isChecked);
                        return;
                    }

                    label.text(isChecked ? i18n.yes : i18n.no);
                    toastr['success'](result.msg);
                },
                error: function() {
                    toastr['error'](i18n.failedToUpdate);
                    $(`${selector}[data-id="${dataId}"]`).prop("checked", !isChecked);
                }
            });
        });
    };

    bindToggleAction(".toggle-read", "webhook_read");
    bindToggleAction(".toggle-reject", "webhook_reject_call");
    bindToggleAction(".toggle-available", "set_available");
    bindToggleAction(".toggle-typing", "webhook_typing");
</script>
