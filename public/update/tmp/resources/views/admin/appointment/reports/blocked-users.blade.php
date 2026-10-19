@extends('layouts.main')

@section('title')
    {{ __('Blocked Users for Appointments') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('home') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.appointment.index') }}">{{ __('Appointments') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.appointment.reports.index') }}">{{ __('Reports') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Blocked Users') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ __('Blocked Users Management') }}</h5>
                        <div class="card-tools">
                            <a href="{{ route('admin.appointment.reports.index') }}" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-1"></i> {{ __('Back to Reports') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped"
                                    id="table_list" data-toggle="table" data-url="{{ route('admin.appointment.reports.blocked-users.list') }}"
                                    data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                    data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                                    data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                                    data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                            <th scope="col" data-field="user_name" data-sortable="true">{{ __('User') }}</th>
                                            <th scope="col" data-field="agent_name" data-sortable="true">{{ __('Verified User') }}</th>
                                            <th scope="col" data-field="raw_block_type_badge" data-sortable="true">{{ __('Block Type') }}</th>
                                            <th scope="col" data-field="reason" data-sortable="false">{{ __('Reason') }}</th>
                                            <th scope="col" data-field="blocked_by_admin" data-sortable="true">{{ __('Blocked By') }}</th>
                                            <th scope="col" data-field="blocked_at_formatted" data-sortable="true">{{ __('Blocked At') }}</th>
                                            <th scope="col" data-field="operate" data-align="center" data-sortable="false" data-events="actionEvents">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

        <!-- Unblock User Modal -->
        <div id="unblockUserModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="unblockUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="unblockUserModalLabel">{{ __('Unblock User') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>{{ __('Are you sure you want to unblock this user? They will be able to make appointments again.') }}</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>{{ __('Warning:') }}</strong> {{ __('This action will allow the user to make appointments with verified users again.') }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-success" id="confirmUnblock">
                            <i class="bi bi-unlock me-1"></i> {{ __('Unblock User') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
@endsection

@section('script')
    <script>
        let currentBlockId = null;

        // Action events
        window.actionEvents = {
            'click .unblock-appointment-user': function(e, value, row, index) {
                currentBlockId = row.id;
                $('#unblockUserModal').modal('show');
            }
        };

        // Handle confirm unblock
        $('#confirmUnblock').on('click', function() {
            if (currentBlockId) {
                $.ajax({
                    url: "{{ route('admin.appointment.reports.unblock-user') }}",
                    type: "POST",
                    data: {
                        block_id: currentBlockId
                    },
                    success: function(response) {
                        if (response.error === false) {
                            Toastify({
                                text: response.message,
                                duration: 6000,
                                close: true,
                                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                            }).showToast();
                            $('#unblockUserModal').modal('hide');
                            $('#table_list').bootstrapTable('refresh');
                        } else {
                            Toastify({
                                text: response.message,
                                duration: 6000,
                                close: true,
                                backgroundColor: '#dc3545'
                            }).showToast();
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = '{{ __("Something Went Wrong") }}';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        Toastify({
                            text: errorMessage,
                            duration: 6000,
                            close: true,
                            backgroundColor: '#dc3545'
                        }).showToast();
                    }
                });
            }
        });

        // Reset current block ID when modal is hidden
        $('#unblockUserModal').on('hidden.bs.modal', function() {
            currentBlockId = null;
        });
    </script>
@endsection

