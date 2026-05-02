<x-layout-dashboard title="Test Messages">
    @php
        $allowedTypes = ['text', 'media', 'poll', 'list', 'button', 'template'];
        $selectedType = old('type');
    @endphp

    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Message</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Test</li>
                </ol>
            </nav>
        </div>
    </div>

    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif

    @if ($errors->any())
        <div class="alert border-0 bg-light-danger alert-dismissible fade show py-2">
            <div class="d-flex align-items-center">
                <div class="fs-3 text-danger"><i class="bi bi-exclamation-circle-fill"></i></div>
                <div class="ms-3">
                    <p>The given data was invalid.</p>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="section-hero-card mb-4">
        <p class="section-kicker mb-2">Message Lab</p>
        <h3 class="section-title mb-2">Uji pesan sebelum dikirim ke banyak nomor</h3>
        <p class="hero-meta mb-0">Gunakan halaman ini untuk mengetes berbagai tipe pesan dengan device yang sedang aktif
            sebelum dipakai di campaign atau auto reply.</p>
    </section>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="smart-form-card p-4">
                <div class="d-sm-flex align-items-center mb-4">
                    <h5 class="mb-0">Test Message</h5>
                </div>
                @if (!session()->has('selectedDevice') || !session()->has('selectedDevice'))
                    <div class="alert alert-danger mb-0">
                        <ul class="mb-0">
                            <li>Please select a device and a message to test</li>
                        </ul>
                    </div>
                @else
                    <div class="card shadow-none border-0 bg-transparent">
                        <div class="card-body p-0">
                            <form class="row g-3" action="{{ route('messagetest') }}" method="POST">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label">Sender</label>
                                    <input name="sender" value="{{ session()->get('selectedDevice')['device_body'] }}"
                                        type="text" class="form-control" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Receiver Number</label>
                                    <textarea placeholder="628xxx|628xxx|628xxx" class="form-control" name="number" cols="20" rows="2">{{ old('number') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label for="type" class="form-label">Type Message</label>
                                    <select name="type" id="type" class="js-states form-control" tabindex="-1" required>
                                        <option value="" {{ $selectedType ? '' : 'selected' }} disabled>Select One</option>
                                        <option value="text" {{ $selectedType === 'text' ? 'selected' : '' }}>Text Message</option>
                                        <option value="media" {{ $selectedType === 'media' ? 'selected' : '' }}>Media Message</option>
                                        <option value="poll" {{ $selectedType === 'poll' ? 'selected' : '' }}>Poll Message</option>
                                        <option value="list" {{ $selectedType === 'list' ? 'selected' : '' }}>List Message</option>
                                        <option value="button" {{ $selectedType === 'button' ? 'selected' : '' }}>Button Message ( Deprecated )</option>
                                        <option value="template" {{ $selectedType === 'template' ? 'selected' : '' }}>Template Message ( Deprecated )</option>
                                    </select>
                                </div>
                                <div class="col-12 ajaxplace">
                                    @if (in_array($selectedType, $allowedTypes, true))
                                        @includeIf('ajax.messages.form' . $selectedType)
                                    @endif
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary px-5">Send Message</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

</x-layout-dashboard>
<script>
    const formMessageUrlTemplate = @json(route('formMessage', ['type' => '__TYPE__']));

    const loadMessageTypeForm = (type) => {
        if (!type) {
            $(".ajaxplace").html('');
            return;
        }

        $.ajax({
            url: formMessageUrlTemplate.replace('__TYPE__', type),
            type: "GET",
            dataType: "html",
            success: (result) => {
                $(".ajaxplace").html(result);
            },
            error: (error) => {
                console.log(error);
            },
        })
    };

    $('#type').on('change', () => {
        loadMessageTypeForm($('#type').val());
    });

    $(function() {
        const selectedType = $('#type').val();
        if (selectedType && !$.trim($('.ajaxplace').html())) {
            loadMessageTypeForm(selectedType);
        }
    });
</script>
