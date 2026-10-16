@extends('layouts.main')

@section('title')
    {{ __('Admin Appointment Preferences') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        {!! Form::open(['route' => 'admin.appointment.preferences.store', 'class' => 'create-form', 'data-success-function'=> "formSuccessFunction"]) !!}
            <div class="form-group row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Appointment Settings') }}</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    {{-- Meeting Duration --}}
                                    <div class="col-sm-12 col-md-6 mt-2">
                                        <label class="form-label" for="meeting_duration_minutes">{{ __('Meeting Duration (minutes)') }} <span class="text-danger">*</span></label>
                                        <input name="meeting_duration_minutes" type="number" class="form-control time-input" id="meeting_duration_minutes" 
                                               placeholder="30" min="15" max="480" required
                                               data-toggle="tooltip" data-placement="top" title=""
                                               value="{{ isset($preferences) && !empty($preferences) && isset($preferences->meeting_duration_minutes) && !empty($preferences->meeting_duration_minutes) ? $preferences->meeting_duration_minutes : '' }}">
                                        <small class="form-text text-muted">{{ __('Duration of each appointment slot (15-480 minutes)') }}</small>
                                    </div>

                                    {{-- Lead Time --}}
                                    <div class="col-sm-12 col-md-6 mt-2">
                                        <label class="form-label" for="lead_time_minutes">{{ __('Minimum Advance Booking Time (minutes)') }} <span class="text-danger">*</span></label>
                                        <input name="lead_time_minutes" type="number" class="form-control time-input" id="lead_time_minutes" 
                                               placeholder="60" min="0" max="10080" required
                                               data-toggle="tooltip" data-placement="top" title=""
                                               value="{{ isset($preferences) && !empty($preferences) && isset($preferences->lead_time_minutes) && (!empty($preferences->lead_time_minutes) || $preferences->lead_time_minutes == 0) ? $preferences->lead_time_minutes : '' }}">
                                        <small class="form-text text-muted">{{ __('Minimum time required before booking (0-10080 minutes)') }}</small>
                                    </div>

                                    {{-- Buffer Time --}}
                                    <div class="col-sm-12 col-md-6 mt-2">
                                        <label class="form-label" for="buffer_time_minutes">{{ __('Buffer Time Between Appointments (minutes)') }} <span class="text-danger">*</span></label>
                                        <input name="buffer_time_minutes" type="number" class="form-control time-input" id="buffer_time_minutes" 
                                               placeholder="15" min="0" max="120" required
                                               data-toggle="tooltip" data-placement="top" title=""
                                               value="{{ isset($preferences) && !empty($preferences) && isset($preferences->buffer_time_minutes) && (!empty($preferences->buffer_time_minutes) || $preferences->buffer_time_minutes == 0) ? $preferences->buffer_time_minutes : '' }}">
                                        <small class="form-text text-muted">{{ __('Time between consecutive appointments (0-120 minutes)') }}</small>
                                    </div>

                                    {{-- Auto Confirm --}}
                                    <div class="col-sm-12 col-md-6 mt-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="auto_confirm" name="auto_confirm" value="1"
                                                   {{ ($preferences->auto_confirm ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="auto_confirm">
                                                {{ __('Auto-confirm appointments') }}
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">{{ __('Automatically confirm appointments without manual approval') }}</small>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    {{-- Cancel/Reschedule Buffer --}}
                                    <div class="col-sm-12 col-md-6 mt-2">
                                        <label class="form-label" for="cancel_reschedule_buffer_minutes">{{ __('Cancel/Reschedule Buffer (minutes)') }}</label>
                                        <input name="cancel_reschedule_buffer_minutes" type="number" class="form-control time-input" id="cancel_reschedule_buffer_minutes" 
                                               placeholder="60" min="0" max="1440"
                                               data-toggle="tooltip" data-placement="top" title=""
                                               value="{{ isset($preferences) && !empty($preferences) && isset($preferences->cancel_reschedule_buffer_minutes) && (!empty($preferences->cancel_reschedule_buffer_minutes) || $preferences->cancel_reschedule_buffer_minutes == 0) ? $preferences->cancel_reschedule_buffer_minutes : '' }}">
                                        <small class="form-text text-muted">{{ __('Minimum time before appointment to cancel/reschedule (0-1440 minutes)') }}</small>
                                    </div>

                                    {{-- Auto Cancel After --}}
                                    <div class="col-sm-12 col-md-6 mt-2">
                                        <label class="form-label" for="auto_cancel_after_minutes">{{ __('Auto-cancel After (minutes)') }} <span class="text-danger">*</span></label>
                                        <input name="auto_cancel_after_minutes" type="number" class="form-control time-input" id="auto_cancel_after_minutes" 
                                               placeholder="1440" min="0" max="10080" required
                                               data-toggle="tooltip" data-placement="top" title=""
                                               value="{{ isset($preferences) && !empty($preferences) && isset($preferences->auto_cancel_after_minutes) && (!empty($preferences->auto_cancel_after_minutes) || $preferences->auto_cancel_after_minutes == 0) ? $preferences->auto_cancel_after_minutes : '' }}">
                                        <small class="form-text text-muted">{{ __('Auto-cancel if not confirmed within this time (0-10080 minutes)') }}</small>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    {{-- Auto Cancel Message --}}
                                    <div class="col-sm-12 mt-2">
                                        <label class="form-label" for="auto_cancel_message">{{ __('Auto-cancel Message') }}</label>
                                        <textarea name="auto_cancel_message" class="form-control" id="auto_cancel_message" rows="3" 
                                                  placeholder="Your appointment has been automatically cancelled due to no confirmation.">{{ isset($preferences) && !empty($preferences) && isset($preferences->auto_cancel_message) && !empty($preferences->auto_cancel_message) ? $preferences->auto_cancel_message : '' }}</textarea>
                                        <small class="form-text text-muted">{{ __('Message sent when appointment is auto-cancelled') }}</small>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    {{-- Daily Booking Limit --}}
                                    <div class="col-sm-12 col-md-6 mt-2">
                                        <label class="form-label" for="daily_booking_limit">{{ __('Daily Booking Limit') }}</label>
                                        <input name="daily_booking_limit" type="number" class="form-control" id="daily_booking_limit" 
                                               placeholder="10" min="1" max="100"
                                               value="{{ isset($preferences) && !empty($preferences) && isset($preferences->daily_booking_limit) && !empty($preferences->daily_booking_limit) ? $preferences->daily_booking_limit : '' }}">
                                        <small class="form-text text-muted">{{ __('Maximum appointments per day (1-100)') }}</small>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    {{-- Availability Types --}}
                                    <div class="col-sm-12 mt-2">
                                        <label class="form-label">{{ __('Available Meeting Types') }}</label>
                                        <div class="row">
                                            @php
                                                $availabilityTypes = isset($preferences) && !empty($preferences) && isset($preferences->availability_types) && !empty($preferences->availability_types) ? explode(',', $preferences->availability_types) : [];
                                            @endphp
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="availability_types[]" value="phone" id="type_phone"
                                                           {{ in_array('phone', $availabilityTypes) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="type_phone">
                                                        {{ __('Phone Call') }}
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="availability_types[]" value="virtual" id="type_virtual"
                                                           {{ in_array('virtual', $availabilityTypes) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="type_virtual">
                                                        {{ __('Virtual Meeting') }}
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="availability_types[]" value="in_person" id="type_in_person"
                                                           {{ in_array('in_person', $availabilityTypes) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="type_in_person">
                                                        {{ __('In-Person Meeting') }}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">{{ __('Select the types of meetings you offer') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">{{ __('Save Preferences') }}</button>
                                    <a href="{{ url('admin/appointment/time-schedule') }}" class="btn btn-secondary">{{ __('Next: Time Schedule') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {!! Form::close() !!}
    </section>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        // Initialize select2 for timezone
        $('.select2').select2();
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Function to convert minutes to human readable format
        function minutesToHumanReadable(minutes) {
            if (minutes === 0) return '0 minutes';
            
            const days = Math.floor(minutes / 1440);
            const hours = Math.floor((minutes % 1440) / 60);
            const mins = minutes % 60;
            
            let result = '';
            
            if (days > 0) {
                result += days + ' day' + (days > 1 ? 's' : '');
                if (hours > 0 || mins > 0) result += ', ';
            }
            
            if (hours > 0) {
                result += hours + ' hour' + (hours > 1 ? 's' : '');
                if (mins > 0) result += ', ';
            }
            
            if (mins > 0 || (days === 0 && hours === 0)) {
                result += mins + ' minute' + (mins > 1 ? 's' : '');
            }
            
            return result;
        }
        
        // Function to update tooltip for time input
        function updateTimeTooltip(input) {
            const minutes = parseInt(input.value) || 0;
            const humanTime = minutesToHumanReadable(minutes);
            input.setAttribute('title', humanTime);
            
            // Update tooltip if it's already shown
            if ($(input).tooltip('instance')) {
                $(input).tooltip('dispose');
                $(input).tooltip();
            }
        }
        
        // Add event listeners to all time inputs
        $('.time-input').on('input change', function() {
            updateTimeTooltip(this);
        });
        
        // Initialize tooltips for existing values
        $('.time-input').each(function() {
            updateTimeTooltip(this);
        });
        
        // Form validation
        $('.create-form').on('submit', function(e) {
            var isValid = true;
            
            // Check if at least one availability type is selected
            if ($('input[name="availability_types[]"]:checked').length === 0) {
                alert('{{ __("Please select at least one meeting type.") }}');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
    function formSuccessFunction() {
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
</script>
@endsection
