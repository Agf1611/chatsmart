<li class="device-switcher">
    <label for="device_idd">Active Device</label>
    <select class="form-select" id="device_idd" name="device_id">
        <option value="" disabled selected>{{ __('system.select_device') }}</option>
        @foreach ($numbers as $device)
            @if (Session::has('selectedDevice') && Session::get('selectedDevice')['device_body'] == $device->body)
                <option value="{{ $device->id }}" selected>{{ $device->body }} ({{ $device->status }})</option>
            @else
                <option value="{{ $device->id }}">{{ $device->body }} ({{ $device->status }})</option>
            @endif
        @endforeach
    </select>
</li>

<script>
    $('#device_idd').on('change', function() {
        var device = $(this).val();
        $.ajax({
            url: "{{ route('home.setSessionSelectedDevice') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                device: device
            },
            success: function(data) {
                if (data.error) {
                    toastr.error(data.msg);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.success(data.msg);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }
        });
    });
</script>
