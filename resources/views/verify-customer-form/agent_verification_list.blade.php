@extends('layouts.main')

@section('title')
     {{ __('Manage Agent Verification') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"> </div>
        </div>
    </div>
@endsection


@section('content')
    <section class="section">
        @if (has_permissions('update', 'approve_agent_verification'))
            <div class="card">
                <div class="card-header">
                    <div class="divider">
                        <div class="divider-text">
                            <h4>{{ __('Settings') }}</h4>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Auto Approve Toggle --}}
                        <div class="col-12 mt-2 form-group mandatory">
                            <label class="form-check-label" for="auto-approve-toggle">{{ __('Auto Approve for Agent and Verified Agent(Properties, Projects and Advertisements)') }}</label>
                            <div class="col-sm-1">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="auto_approve_toggle" id="auto-approve" value="{{ system_setting('agent_auto_approve') == 1 ? 1 : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" {{ system_setting('agent_auto_approve') == '1' ? 'checked' : '' }} id="auto-approve-toggle" data-url="{{ route('agent-verification.auto-approve') }}">
                                    <label class="form-check-label" for="auto-approve-toggle"></label>
                                </div>
                            </div>
                        </div>

                        {{-- Verification Required for Agent Toggle --}}
                        <div class="col-12 mt-2 form-group mandatory">
                            <label class="form-check-label" for="verification-required-for-agent">{{ __('Verification Required for agent and Verified Agent before listing (Properties and Projects)') }}</label>
                            <div class="col-sm-1">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="verification_required_for_agent" id="verification-required-for-agent-value" value="{{ system_setting('verification_required_for_agent') == 1 ? 1 : 0 }}">
                                    <input class="form-check-input" type="checkbox" role="switch" {{ system_setting('verification_required_for_agent') == '1' ? 'checked' : '' }} id="verification-required-for-agent" data-url="{{ route('agent-verification.verification-required-for-agent') }}">
                                    <label class="form-check-label" for="verification-required-for-agent"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <div class="card">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ (isset($form_type) && $form_type == 'verify_agent') ? __('Agent Verification Request List') : __('User Verification Request List') }}</h4>
                    </div>
                </div>
                <div class="row px-4 pb-3">
                    <div class="col-md-4">
                        <label for="table_form_type" class="form-label">{{ __('Filter by Request Type') }}</label>
                        <select id="table_form_type" class="form-select">
                            <option value="become_agent" {{ (isset($form_type) && $form_type == 'become_agent') ? 'selected' : '' }}>{{ __('Become Agent') }}</option>
                            <option value="verify_agent" {{ (isset($form_type) && $form_type == 'verify_agent') ? 'selected' : '' }}>{{ __('Verify Agent Badge') }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                            data-toggle="table" data-url="{{ route('agent-verification.list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                            data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                            data-responsive="true" data-sort-name="updated_at" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="user" data-sortable="false" data-formatter="userNameProfileFormatter">{{ __('Name') }}</th>
                                    <th scope="col" data-field="form_type" data-align="center" data-sortable="true" data-formatter="formTypeFormatter"> {{ __('Request Type') }}</th>
                                    <th scope="col" data-field="user.property_count" data-sortable="false" data-width="5%" data-align="center">{{ __('Total Properties') }}</th>
                                    <th scope="col" data-field="user.projects_count" data-sortable="false" data-width="5%" data-align="center">{{ __('Total Projects') }}</th>
                                    <th scope="col" data-field="raw_view_form_btn" data-align="center" data-sortable="false" data-width="5%"> {{ __('View Submitted Values') }}</th>
                                    <th scope="col" data-field="status" data-align="center" data-sortable="false" data-formatter="requestStatusFormatter"> {{ __('Status') }}</th>
                                    @if (has_permissions('update', 'approve_agent_verification'))
                                        <th scope="col" data-field="operate" data-sortable="false" data-align="center" data-events="actionEvents"> {{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>


<!-- EDIT MODEL MODEL -->
<div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="changeVerificationModal" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-md">
    <div class="modal-content">
        <div class="modal-header">
            <h6 class="modal-title" id="changeVerificationModal">{{ __('Change Verification Status') }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form class="verify-customer-status-form" action="{{ route('agent-verification.change-status') }}" data-success-function="editFormSuccessFunction">
            <div class="modal-body">
                {{ csrf_field() }}

                {!! Form::hidden('edit_id', "", ['id' => 'edit-id']) !!}
                <div class="col-md-12 col-12  form-group  mandatory">
                    <div class="row">
                        {{ Form::label('', __('Status'), ['class' => 'form-label col-12 ']) }}

                        {{-- Approved --}}
                        <div class="col-md-3">
                            {{ Form::radio('edit_status', 'approved', null, [ 'class' => 'form-check-input', 'id' => 'status-approved', 'required' => true ]) }}
                            {{ Form::label('status-approved', __('Approved'), ['class' => 'form-check-label']) }}
                        </div>
                        {{-- Rejected --}}
                        <div class="col-md-3">
                            {{ Form::radio('edit_status', 'rejected', null, [ 'class' => 'form-check-input', 'id' => 'status-rejected', 'required' => true, ]) }}
                            {{ Form::label('status-rejected', __('Rejected'), ['class' => 'form-check-label']) }}
                        </div>
                    </div>
                </div>

                <div class="col-md-12 col-12 form-group mandatory" id="reject_reason_container" style="display: none">
                    {{ Form::label('reject_reason', __('Reject Reason'), ['class' => 'form-label']) }}
                    {{ Form::textarea('reject_reason', null, ['class' => 'form-control', 'placeholder' => __('Reject Reason'), 'rows' => 3, 'id' => 'reject_reason']) }}
                </div>


            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect close-btn" data-bs-dismiss="modal">{{ __('Close') }}</button>
                {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1', 'id' => 'btn_submit']) }}
            </div>
        </form>
    </div>
</div>
</div>
<!-- EDIT MODEL -->

@endsection

@section('script')
    <script>

        $(document).ready(function() {
            $('#table_form_type').on('change', function() {
                $('#table_list').bootstrapTable('refresh');
            });

            // Change Event on Auto Approve Toggle
            let autoApproveState = $("#auto-approve-toggle").is(':checked');
            $("#auto-approve-toggle").on('change', function() {
                // Store the original state of the toggle
                Swal.fire({
                    title: window.trans["Are you sure"],
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: window.trans["Confirm"],
                    cancelButtonText: window.trans["Cancel"],
                }).then((result) => {
                    if (result.isConfirmed) {
                        let newValue = $("#auto-approve-toggle").is(':checked') ? 1 : 0;
                        $("#auto-approve").val(newValue);
                        // Update the value based on the toggle state
                        let url = $("#auto-approve-toggle").data("url");


                        let data = new FormData();
                        data.append('auto_approve', newValue);

                        function successCallback(response) {
                            showSuccessToast(response.message);
                            opt.successCallBack(response);
                        }

                        function errorCallback(response) {
                            $("#auto-approve-toggle").prop('checked', autoApproveState);
                            let newValue = autoApproveState ? 1 : 0;
                            $("#auto-approve").val(newValue);
                            showErrorToast(response.message);
                            opt.errorCallBack(response);
                        }

                        ajaxRequest("POST", url, data, null, successCallback, errorCallback);
                    } else {
                        // Revert the toggle to its original state if not confirmed
                        $("#auto-approve-toggle").prop('checked', autoApproveState);
                        let newValue = autoApproveState ? 1 : 0;
                        $("#auto-approve").val(newValue);
                    }
                });
            });

            // Change Event on Verification Required for agent toggle
            let verificationRequiredState = $("#verification-required-for-agent").is(':checked');
            $("#verification-required-for-agent").on('change', function() {
                // Store the original state of the toggle
                Swal.fire({
                    title: window.trans["Are you sure"],
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: window.trans["Confirm"],
                    cancelButtonText: window.trans["Cancel"],
                }).then((result) => {
                    if (result.isConfirmed) {
                        let newValue = $("#verification-required-for-agent").is(':checked') ? 1 : 0;
                        $("#auto-approve").val(newValue);
                        // Update the value based on the toggle state
                        let url = $("#verification-required-for-agent").data("url");


                        let data = new FormData();
                        data.append('verification_required_for_agent', newValue);

                        function successCallback(response) {
                            showSuccessToast(response.message);
                            opt.successCallBack(response);
                        }

                        function errorCallback(response) {
                            $("#verification-required-for-agent").prop('checked', verificationRequiredState);
                            let newValue = verificationRequiredState ? 1 : 0;
                            $("#verification-required-for-agent-value").val(newValue);
                            showErrorToast(response.message);
                            opt.errorCallBack(response);
                        }

                        ajaxRequest("POST", url, data, null, successCallback, errorCallback);
                    } else {
                        // Revert the toggle to its original state if not confirmed
                        $("#verification-required-for-agent").prop('checked', verificationRequiredState);
                        let newValue = verificationRequiredState ? 1 : 0;
                        $("#verification-required-for-agent-value").val(newValue);
                    }
                });
            });
        });
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                form_type: $('#table_form_type').val()
            };
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $("#edit-id").val(row.id);
                $('input[name=edit_status]').prop('checked', false);
                $("#reject_reason_container").hide();
                $("#reject_reason").val('');
                if(row.status != 'pending'){
                    $(`input[name=edit_status][value="${row.status}"]`).prop('checked', true);
                    if(row.status == 'rejected'){
                        $("#reject_reason_container").show();
                    }
                }
            }
        }

        $('input[name="edit_status"]').on('change', function() {
            if ($(this).val() == 'rejected') {
                $('#reject_reason_container').show();
            } else {
                $('#reject_reason_container').hide();
            }
        });

        function editFormSuccessFunction () {
            $('#table_list').bootstrapTable('refresh');
            setTimeout(function () {
                $('#editModal').modal('hide');
            }, 1000);
        }
    </script>
@endsection
