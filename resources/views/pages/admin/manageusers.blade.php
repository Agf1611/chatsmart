<x-layout-dashboard title="Users">
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">

    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Admin</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Users</li>
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
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="section-hero-card mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-lg-center">
            <div>
                <p class="section-kicker mb-2">User Management</p>
                <h3 class="section-title mb-2">Kelola user dashboard ChatSmart</h3>
                <p class="hero-meta mb-0">Atur akun admin dan user, batas device, serta status subscription dengan
                    panel yang lebih rapi dan konsisten.</p>
                <p class="hero-meta mt-2 mb-0">Pending approval saat ini: <strong>{{ $pendingApprovals }}</strong></p>
            </div>
            <div class="toolbar-actions">
                <button type="button" class="btn btn-primary" onclick="addUser()">Add User</button>
            </div>
        </div>
    </section>

    <div class="smart-list-card">
        <div class="card-header d-flex justify-content-between align-items-center px-4 py-3">
            <h5 class="card-title mb-0">Users</h5>
            <span class="text-muted small">{{ $users->total() }} user</span>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive mt-3">
                <table class="table align-middle">
                    <thead class="table-secondary">
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status Akun</th>
                            <th>Total Device</th>
                            <th>Limit Device</th>
                            <th>Subscription</th>
                            <th>Expired subscription</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="fw-semibold">{{ $user->username }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @php
                                        $approvalBadge = $user->is_pending_approval ? 'warning text-dark' : 'success';
                                    @endphp
                                    <span class="badge bg-{{ $approvalBadge }}">{{ $user->approval_label }}</span>
                                </td>
                                <td>{{ $user->total_device }}</td>
                                <td>{{ $user->limit_device }}</td>
                                <td>
                                    @php
                                        $badge = $user->is_pending_approval ? 'secondary' : ($user->is_expired_subscription ? 'danger' : 'success');
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ $user->active_subscription }}</span>
                                </td>

                                <td>
                                    @php
                                        if ($user->is_pending_approval) {
                                            echo '<span class="badge bg-warning text-dark">Tunggu approval</span>';
                                        } elseif ($user->is_expired_subscription) {
                                            echo '<span class="badge bg-danger">-</span>';
                                        } else {
                                            if ($user->active_subscription == 'active') {
                                                echo optional($user->subscription_expired)->format('Y-m-d H:i');
                                            } else {
                                                echo '<span class="badge bg-danger">-</span>';
                                            }
                                        }
                                    @endphp
                                </td>
                                <td>
                                    <div class="table-actions d-flex align-items-center gap-3 fs-6">
                                        <a onclick="editUser({{ $user->id }})" href="javascript:;" class="text-primary"
                                            data-bs-toggle="tooltip" title="Edit user"><i class="bx bxs-edit"></i></a>

                                        <form action="{{ route('user.delete', $user->id) }}" method="POST"
                                            onsubmit="return confirm('Are you sure will delete this user ? all data user also will deleted')">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="id" value="{{ $user->id }}">
                                            <button type="submit" name="delete" class="btn text-sm btn-sm text-danger">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    <tfoot></tfoot>
                </table>
            </div>
            <nav aria-label="Page navigation example">
                <ul class="pagination">
                    <li class="page-item {{ $users->currentPage() == 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $users->previousPageUrl() }}">{{ __('system.previous') }}</a>
                    </li>

                    @for ($i = 1; $i <= $users->lastPage(); $i++)
                        <li class="page-item {{ $users->currentPage() == $i ? 'active' : '' }}">
                            <a class="page-link" href="{{ $users->url($i) }}">{{ $i }}</a>
                        </li>
                    @endfor

                    <li class="page-item {{ $users->currentPage() == $users->lastPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $users->nextPageUrl() }}">{{ __('system.next') }}</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="modal fade" id="modalUser" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" enctype="multipart/form-data" id="formUser">
                        @csrf
                        <input type="hidden" id="iduser" name="id">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" value="">
                        <label for="email" class="form-label mt-3">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="">
                        <label for="password" class="form-label mt-3" id="labelpassword">Password</label>
                        <input type="password" name="password" id="password" class="form-control" value="">
                        <label for="status" class="form-label mt-3">Status Akun</label><br>
                        <select name="status" id="status" class="form-control">
                            <option value="inactive" selected>Pending Approval</option>
                            <option value="active">Approved / Active</option>
                        </select><br>
                        <label for="limit_device" class="form-label mt-3">Limit Device</label>
                        <input type="number" name="limit_device" id="limit_device" class="form-control" value="">
                        <label for="active_subscription" class="form-label mt-3">Active Subscription</label><br>
                        <select name="active_subscription" id="active_subscription" class="form-control">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="lifetime">Lifetime</option>
                        </select><br>
                        <label for="subscription_expired" class="form-label">Subscription Expired</label>
                        <input type="date" name="subscription_expired" id="subscription_expired" class="form-control" value="">
                        <small class="text-muted d-block mt-2" id="approvalHelp">
                            Akun pending tidak bisa login sebelum status akun diubah menjadi approved / active oleh admin.
                        </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="modalButton" name="submit" class="btn btn-primary">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function syncUserFormState() {
            const status = $('#status').val();
            const subscription = $('#active_subscription').val();
            const isPending = status !== 'active';
            const needsExpiry = status === 'active' && subscription === 'active';

            if (isPending) {
                $('#active_subscription').val('inactive');
                $('#subscription_expired').val('');
            }

            $('#active_subscription').prop('disabled', isPending);
            $('#subscription_expired').prop('disabled', !needsExpiry);
        }

        function addUser() {
            $('#modalLabel').html('Add User');
            $('#modalButton').html('Add');
            $('#formUser').attr('action', '{{ route('user.store') }}');
            $('#labelpassword').html('Password');
            $('#iduser').val('');
            $('#username').val('');
            $('#email').val('');
            $('#password').val('');
            $('#status').val('inactive');
            $('#limit_device').val(0);
            $('#active_subscription').val('inactive');
            $('#subscription_expired').val('');
            syncUserFormState();
            $('#modalUser').modal('show');
        }

        function editUser(id) {
            $('#modalLabel').html('Edit User');
            $('#modalButton').html('Edit');
            $('#formUser').attr('action', '{{ route('user.update') }}');
            $('#modalUser').modal('show');
            $.ajax({
                url: "{{ route('user.edit') }}",
                type: "GET",
                data: {
                    id: id
                },
                dataType: "JSON",
                success: function(data) {
                    $('#labelpassword').html('Password *(leave blank if not change)');
                    $('#username').val(data.username);
                    $('#email').val(data.email);
                    $('#password').val('');
                    $('#status').val(data.status);
                    $('#limit_device').val(data.limit_device);
                    $('#active_subscription').val(data.active_subscription);
                    $('#subscription_expired').val(data.subscription_expired ? data.subscription_expired.substring(0, 10) : '');
                    $('#iduser').val(data.id);
                    syncUserFormState();
                }
            });
        }

        $('#status, #active_subscription').on('change', syncUserFormState);
    </script>
</x-layout-dashboard>
