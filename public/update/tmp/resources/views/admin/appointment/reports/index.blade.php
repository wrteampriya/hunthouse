@extends('layouts.main')

@section('title')
    {{ __('Appointment Reports') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Appointment Reports') }}</li>
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
                        <h5 class="card-title">{{ __('Appointment Reports Management') }}</h5>
                        <div class="card-tools">
                            <a href="{{ route('admin.appointment.reports.blocked-users') }}" class="btn btn-warning">
                                <i class="bi bi-person-x me-1"></i> {{ __('Blocked Users') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped"
                                    id="table_list" data-toggle="table" data-url="{{ route('admin.appointment.reports.list') }}"
                                    data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                    data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                                    data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                                    data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                            <th scope="col" data-field="agent_name" data-sortable="true">{{ __('Verified User') }}</th>
                                            <th scope="col" data-field="user_name" data-sortable="true">{{ __('User') }}</th>
                                            <th scope="col" data-field="reason" data-sortable="false">{{ __('Reason') }}</th>
                                            <th scope="col" data-field="raw_status_badge" data-sortable="true">{{ __('Status') }}</th>
                                            <th scope="col" data-field="reported_at_formatted" data-sortable="true">{{ __('Reported At') }}</th>
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

        <!-- Block User Modal -->
        <div id="blockUserModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="blockUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="blockUserModalLabel">{{ __('Block User for Appointments') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="blockUserForm">
                        <div class="modal-body">
                            {{ csrf_field() }}
                            <input type="hidden" id="block_report_id" name="report_id">

                            <div class="form-group">
                                <label for="block_type">{{ __('Block Type') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="block_type" name="block_type" required>
                                    <option value="">{{ __('Select Block Type') }}</option>
                                    <option value="agent_specific">{{ __('Verified User Specific (Only for this verified user)') }}</option>
                                    <option value="global">{{ __('Global (For all verified users)') }}</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="block_reason">{{ __('Reason') }}</label>
                                <textarea class="form-control" id="block_reason" name="reason" rows="3" placeholder="{{ __('Enter reason for blocking (optional)') }}"></textarea>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>{{ __('Verified User Specific:') }}</strong> {{ __('User will be blocked only for the specific verified user who reported them.') }}<br>
                                <strong>{{ __('Global:') }}</strong> {{ __('User will be blocked from making appointments with all verified users.') }}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-person-x me-1"></i> {{ __('Block User') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
@endsection

@section('script')
    <script>
        // Action events
        window.actionEvents = {
            'click .approve-report': function(e, value, row, index) {
                updateReportStatus(row.id, 'approved');
            },
            'click .reject-report': function(e, value, row, index) {
                updateReportStatus(row.id, 'rejected');
            },
            'click .block-appointment-user': function(e, value, row, index) {
                $('#block_report_id').val(row.id);
                $('#block_type').val('');
                $('#block_reason').val('');
                $('#blockUserModal').modal('show');
            }
        };

        // Handle block user form submission
        $('#blockUserForm').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                url: "{{ route('admin.appointment.reports.block-user') }}",
                type: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    if (response.error === false) {
                        Toastify({
                            text: response.message,
                            duration: 6000,
                            close: true,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#blockUserModal').modal('hide');
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
        });

        function updateReportStatus(reportId, status) {
            const action = status === 'approved' ? '{{ __("Approve") }}' : '{{ __("Reject") }}';
            const confirmMessage = `{{ __("Are you sure you want to") }} ${action} {{ __("this report?") }}`;
            swal.fire({
                title: confirmMessage,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ __("Yes") }}',
                cancelButtonText: '{{ __("No") }}',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('admin.appointment.reports.update-status') }}",
                        type: "POST",
                        data: {
                            report_id: reportId,
                            status: status
                        },
                        success: function(response) {
                            if (response.error === false) {
                                Toastify({
                                    text: response.message,
                                    duration: 6000,
                                    close: true,
                                    backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                                }).showToast();
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
        }
    </script>
@endsection

