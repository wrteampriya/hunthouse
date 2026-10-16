@extends('layouts.main')

@section('title')
    {{ __('Appointment Management') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Appointment Management') }}</li>
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
                        <div class="mb-3">
                            <div class="btn-group" role="group" id="appointment-filter">
                                <button type="button" class="btn btn-outline-primary filter-btn active" data-filter="all">{{ __('All Appointments') }}</button>
                                <button type="button" class="btn btn-outline-primary filter-btn" data-filter="admin">{{ __('Admin Appointments') }}</button>
                                <button type="button" class="btn btn-outline-primary filter-btn" data-filter="other">{{ __('Other Appointments') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped"
                                    id="table_list" data-toggle="table" data-url="{{ route('appointment-management.list') }}"
                                    data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                    data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                                    data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                                    data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3"
                                    data-query-params="queryParams">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                            <th scope="col" data-field="appointment_type" data-sortable="true" data-formatter="appointmentTypeFormatter">{{ __('Type') }}</th>
                                            <th scope="col" data-field="property_title" data-sortable="false">{{ __('Property') }}</th>
                                            <th scope="col" data-field="agent_name" data-sortable="false">{{ __('Verified User') }}</th>
                                            <th scope="col" data-field="user_name" data-sortable="false">{{ __('User') }}</th>
                                            <th scope="col" data-field="agent_timezone" data-sortable="false">{{ __('Verified User Timezone') }}</th>
                                            <th scope="col" data-field="meeting_type" data-sortable="true" data-formatter="appointmentMeetingTypeFormatter">{{ __('Meeting Type') }}</th>
                                            <th scope="col" data-field="start_at_formatted" data-sortable="true">{{ __('Start Time') }}</th>
                                            <th scope="col" data-field="end_at_formatted" data-sortable="true">{{ __('End Time') }}</th>
                                            <th scope="col" data-field="reason" data-sortable="false" data-visible="false">{{ __('Reason') }}</th>
                                            <th scope="col" data-field="status" data-sortable="true" data-formatter="appointmentStatusFormatter">{{ __('Status') }}</th>
                                            <th scope="col" data-field="notes" data-sortable="false">{{ __('Notes') }}</th>
                                            @if (has_permissions('update', 'appointment_management') || has_permissions('delete', 'appointment_management'))
                                                <th scope="col" data-field="operate" data-align="center" data-sortable="false" data-events="actionEvents">{{ __('Action') }}</th>
                                            @endif
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Update Modal -->
        <div id="statusModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="statusModalLabel">{{ __('Update Appointment Status') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="statusUpdateForm">
                        <div class="modal-body">
                            {{ csrf_field() }}
                            <input type="hidden" id="appointment_id" name="id">
                            <div class="form-group">
                                <label for="status_select">{{ __('Status') }}</label>
                                <select class="form-control" id="status_select" name="status" required>
                                    <option value="">{{ __('Select Status') }}</option>
                                    <option value="pending">{{ __('Pending') }}</option>
                                    <option value="confirmed">{{ __('Confirmed') }}</option>
                                    <option value="cancelled">{{ __('Cancelled') }}</option>
                                    <option value="completed">{{ __('Completed') }}</option>
                                    <option value="rescheduled">{{ __('Rescheduled') }}</option>
                                </select>
                            </div>
                            
                            <!-- Reason field for cancelled and rescheduled -->
                            <div class="form-group" id="reason_field" style="display: none;">
                                <label for="reason">{{ __('Reason') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="{{ __('Enter reason for status change...') }}"></textarea>
                            </div>
                            
                            <!-- Meeting type field -->
                            <div class="form-group" id="meeting_type_field" style="display: none;">
                                <label for="meeting_type">{{ __('Meeting Type') }}</label>
                                <select class="form-control" id="meeting_type" name="meeting_type">
                                    <option value="">{{ __('Select Meeting Type') }}</option>
                                    <option value="phone">{{ __('Phone') }}</option>
                                    <option value="virtual">{{ __('Virtual') }}</option>
                                    <option value="in_person">{{ __('In Person') }}</option>
                                </select>
                            </div>
                            
                            <!-- Date and time fields for rescheduling -->
                            <div id="reschedule_fields" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="new_date">{{ __('New Date') }} <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="new_date" name="new_date" min="{{ date('Y-m-d') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="slot_selection">{{ __('Available Time Slots') }} <span class="text-danger">*</span></label>
                                            <select class="form-control" id="slot_selection" name="slot_selection">
                                                <option value="">{{ __('Select a time slot...') }}</option>
                                            </select>
                                            <small class="form-text text-muted">{{ __('Select a date first to see available slots') }}</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields for start and end time -->
                                <input type="hidden" id="new_start_time" name="new_start_time">
                                <input type="hidden" id="new_end_time" name="new_end_time">
                                
                                <!-- Loading indicator -->
                                <div id="slots_loading" style="display: none;" class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="sr-only">{{ __('Loading...') }}</span>
                                    </div>
                                    <span class="ml-2">{{ __('Loading available slots...') }}</span>
                                </div>
                                
                                <!-- No slots message -->
                                <div id="no_slots_message" style="display: none;" class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> {{ __('No available slots for the selected date') }}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Update Status') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        let currentFilter = 'all';

        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                filter: currentFilter
            };
        }

        // Filter buttons functionality
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            $('#table_list').bootstrapTable('refresh');
        });

        // Appointment type formatter
        function appointmentTypeFormatter(value, row, index) {
            if (row.is_admin_appointment) {
                return `<span class="badge bg-primary">{{ __('Admin') }}</span>`;
            } else {
                return `<span class="badge bg-info">{{ __('Verified User') }}</span>`;
            }
        }

        // Action events
        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $('#appointment_id').val(row.id);
                $('#status_select').val('').trigger('change');
                
                // Store current appointment data for reference
                window.currentAppointment = row;
                
                // Reset form fields
                $('#reason').val('');
                $('#reason').removeAttr('required');
                
                // Hide options in #meeting_type that are not available for this agent/admin
                if(row.agent_meeting_types){
                    $.each(row.agent_meeting_types, function(meetingType) {
                    var availableTypes = row.agent_meeting_types && Array.isArray(row.agent_meeting_types) ? row.agent_meeting_types : [];
                        $('#meeting_type option').each(function() {
                            var val = $(this).val();
                            if (val === '' || availableTypes.includes(val)) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    });
                }
                $('#meeting_type').val('');
                $('#new_date').val('');
                $('#new_start_time').val('');
                $('#new_end_time').val('');
                $('#slot_selection').html('<option value="">{{ __("Select a time slot...") }}</option>');
                $('#no_slots_message').hide();
                
                
                $('#statusModal').modal('show');
            }
        };

        // Function to toggle fields based on status
        function toggleFieldsBasedOnStatus(status) {
            // Hide all conditional fields first
            $('#reason_field').hide();
            $('#meeting_type_field').hide();
            $('#reschedule_fields').hide();
            $('#reason').removeAttr('required');
            
            // Show fields based on status
            if (status === 'cancelled' || status === 'rescheduled') {
                $('#reason_field').show();
                $('#reason').attr('required', true);
            }else{
                $('#reason').removeAttr('required');
            }
            
            if (status === 'rescheduled') {
                $('#meeting_type_field').show();
                $('#reschedule_fields').show();
                
                // Pre-populate current appointment data if available
                if (window.currentAppointment) {
                    const appointment = window.currentAppointment;
                    
                    // Pre-populate meeting type if available
                    if (appointment.meeting_type) {
                        $('#meeting_type').val(appointment.meeting_type);
                    }
                }
            }
        }
        
        // Handle status change event
        $('#status_select').on('change', function() {
            const selectedStatus = $(this).val();
            toggleFieldsBasedOnStatus(selectedStatus);
        });
        
        // Handle date change event to fetch available slots
        $('#new_date').on('change', function() {
            const selectedDate = $(this).val();
            const appointmentId = $('#appointment_id').val();
            
            if (selectedDate && appointmentId) {
                fetchAvailableSlots(appointmentId, selectedDate);
            } else {
                $('#slot_selection').html('<option value="">{{ __("Select a time slot...") }}</option>');
                $('#no_slots_message').hide();
            }
        });
        
        // Handle slot selection change
        $('#slot_selection').on('change', function() {
            const selectedSlot = $(this).find('option:selected');
            const startAt = selectedSlot.data('start-at');
            const endAt = selectedSlot.data('end-at');
            if (startAt && endAt) {
                $('#new_start_time').val(startAt);
                $('#new_end_time').val(endAt);
            } else {
                $('#new_start_time').val('');
                $('#new_end_time').val('');
            }
        });
        
        // Function to fetch available slots
        function fetchAvailableSlots(appointmentId, date) {
            $('#slots_loading').show();
            $('#no_slots_message').hide();
            $('#slot_selection').html('<option value="">{{ __("Loading slots...") }}</option>');
            
            $.ajax({
                url: "{{ route('appointment-management.available-slots') }}",
                type: "POST",
                data: {
                    _token: '{{ csrf_token() }}',
                    appointment_id: appointmentId,
                    date: date
                },
                success: function(response) {
                    $('#slots_loading').hide();
                    
                    if (response.error === false && response.data.available_slots.length > 0) {
                        $('#slot_selection').html('<option value="">{{ __("Select a time slot...") }}</option>');
                        
                        response.data.available_slots.forEach(function(slot) {
                            $('#slot_selection').append(
                                '<option data-start-at="' + slot.start_time + '" data-end-at="' + slot.end_time + '">' + slot.display + '</option>'
                            );
                        });
                    } else {
                        $('#slot_selection').html('<option value="" data-start-at="" data-end-at="">{{ __("No slots available") }}</option>');
                        $('#no_slots_message').show();
                    }
                },
                error: function(xhr) {
                    $('#slots_loading').hide();
                    $('#slot_selection').html('<option value="" data-start-at="" data-end-at="">{{ __("Error loading slots") }}</option>');
                    
                    let errorMessage = 'Failed to load available slots';
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
        
        // Clear form when modal is hidden
        $('#statusModal').on('hidden.bs.modal', function() {
            $('#statusUpdateForm')[0].reset();
            $('#reason_field').hide();
            $('#meeting_type_field').hide();
            $('#reschedule_fields').hide();
            $('#no_slots_message').hide();
            window.currentAppointment = null;
        });
        
        // Status update form submission
        $('#statusUpdateForm').on('submit', function(e) {
            e.preventDefault();
            
            // Validate required fields for rescheduling
            const selectedStatus = $('#status_select').val();
            if (selectedStatus === 'rescheduled') {
                const newDate = $('#new_date').val();
                const selectedSlot = $('#slot_selection').val();
                
                if (!newDate || !selectedSlot) {
                    Toastify({
                        text: 'Please select a date and time slot for rescheduling',
                        duration: 6000,
                        close: true,
                        backgroundColor: '#dc3545'
                    }).showToast();
                    return;
                }
            }
            
            $.ajax({
                url: "{{ route('appointment-management.update-status') }}",
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
                        $('#statusModal').modal('hide');
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
                    let errorMessage = 'Something went wrong';
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
    </script>
@endsection
