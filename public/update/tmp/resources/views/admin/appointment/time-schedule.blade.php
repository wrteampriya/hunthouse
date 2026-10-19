@extends('layouts.main')

@section('title')
    {{ __('Admin Time Schedule') }}
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
        {!! Form::open(['route' => 'admin.appointment.time-schedule.store', 'class' => 'create-form', 'data-success-function'=> "formSuccessFunction"]) !!}
            <div class="form-group row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Weekly Availability Schedule') }}
                                    <i class="fas fa-info-circle ms-2 text-muted" data-bs-toggle="tooltip" data-bs-placement="right" title='{{ __("Set the time ranges when available for appointments.Example: 09:00-12:00 and 15:00-18:00 on the same day.") }}'></i>
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-4">{{ __('Set your weekly availability schedule in ranges. Leave time fields empty for days you are not available.') }}</p>
                                
                                @foreach($days as $day)
                                    @php
                                        $daySchedules = $schedules->get($day, collect());
                                        $dayName = ucfirst($day);
                                        $dayName = trans($dayName);
                                    @endphp
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="form-check form-switch me-3">
                                                    @php
                                                        $hasActiveSlots = $daySchedules->where('is_active', 1)->isNotEmpty();
                                                        $hasAnySlots = $daySchedules->isNotEmpty();
                                                    @endphp
                                                    <input class="form-check-input day-toggle" type="checkbox" id="day_{{ $day }}" data-day="{{ $day }}" {{ $hasActiveSlots ? 'checked' : '' }}>
                                                    <input type="hidden" class="has-existing-slot-ids" value="{{ $hasAnySlots ? '1' : '0' }}">
                                                </div>
                                                <div class="me-auto">
                                                    <label class="form-label fw-bold mb-0" for="day_{{ $day }}">{{ $dayName }}</label>
                                                </div>
                                            </div>

                                            <input type="hidden" name="schedule[{{ $loop->index }}][day]" value="{{ $day }}">

                                            <div class="time-slots-repeater" data-day="{{ $day }}">
                                                <div data-repeater-list="schedule[{{ $loop->index }}][slots]">
                                                    @if($hasActiveSlots)
                                                        @foreach($daySchedules->where('is_active', 1) as $slot)
                                                            <div data-repeater-item class="row g-2 align-items-end mb-2">
                                                                <input type="hidden" name="id" value="{{ $slot->id }}">
                                                                <div class="col-md-4">
                                                                    <label class="form-label">{{ __('Start Time') }}</label>
                                                                    <input type="time" name="start_time" class="form-control schedule-time start-time" value="{{ $slot->start_time }}" data-day="{{ $day }}">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">{{ __('End Time') }}</label>
                                                                    <input type="time" name="end_time" class="form-control schedule-time end-time" value="{{ $slot->end_time }}" data-day="{{ $day }}">
                                                                </div>
                                                                <div class="col-md-2">
                                                                    <button type="button" data-repeater-delete class="btn btn-outline-danger w-100 remove-slot" data-id="{{ $slot->id }}" data-url="{{ route('admin.appointment.time-schedule.remove', $slot->id) }}">{{ __('Remove') }}</button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div data-repeater-item class="row g-2 align-items-end mb-2">
                                                            <input type="hidden" name="id" value="">
                                                            <div class="col-md-4">
                                                                <label class="form-label">{{ __('Start Time') }}</label>
                                                                <input type="time" name="start_time" class="form-control schedule-time start-time" data-day="{{ $day }}" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">{{ __('End Time') }}</label>
                                                                <input type="time" name="end_time" class="form-control schedule-time end-time" data-day="{{ $day }}" required>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <button type="button" data-repeater-delete class="btn btn-outline-danger w-100 remove-slot">{{ __('Remove') }}</button>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="mt-2">
                                                    <button type="button" data-repeater-create class="btn btn-outline-primary add-slot">{{ __('Add Slot') }}</button>
                                                </div>
                                            </div>

                                            <div class="closed-placeholder" data-day="{{ $day }}">
                                                <input type="text" class="form-control" value="{{ __('Closed') }}" disabled>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>{{ __('Note:') }}</strong> 
                                            {{ __('Time slots will be created based on your meeting duration and buffer time settings from the preferences page.') }}
                                        </div>
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
                                <div class="col-12 time-schedule-actions">
                                    <a href="{{ url('admin/appointment/preferences') }}" class="btn btn-secondary">{{ __('Previous: Preferences') }}</a>
                                    <button type="submit" class="btn btn-primary mt-0">{{ __('Save Schedule') }}</button>
                                    <a href="{{ url('admin/appointment/extra-time-slots') }}" class="btn btn-info">{{ __('Next: Extra Time Slots') }}</a>
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
        // Enable Bootstrap tooltips
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        // Initialize repeater for each day's slots
        $('.time-slots-repeater').each(function(){
            $(this).repeater({
                initEmpty: false,
                show: function(){
                    $(this).slideDown();
                    $(this).find('.remove-slot').attr('data-id', '');
                    $(this).find('.start-time').attr('required', true);
                    $(this).find('.end-time').attr('required', true);
                    toggleFirstRemove($(this).closest('.time-slots-repeater'));
                },
                hide: function(deleteElement){
                    let id = $(this).find('.remove-slot').attr('data-id');
                    if(id){
                        let url = $(this).find('.remove-slot').attr('data-url');
                        ShowSwalConfirmationForDeletion(url);
                    }else{
                        $(this).slideUp(deleteElement, function(){
                            $(this).remove();
                            toggleFirstRemove($(this).closest('.time-slots-repeater'));
                        });
                    }
                }
            });
        });

        function ShowSwalConfirmationForDeletion(url){
            Swal.fire({
                title: 'All your appointments will be cancelled. Are you sure you want to continue?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, continue',
                cancelButtonText: 'No, cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    if(typeof url !== 'undefined' && url){
                        showDeletePopupModal(url, {
                            successCallBack: function (response) {
                                setTimeout(() => {
                                    window.location.reload();
                                }, 500);
                            }, errorCallBack: function (response) {
                            }
                        });
                    }
                }
            });
        }

        // Initialize day toggles
        function toggleDay(dayToggle, isChanged = false){
            let day = $(dayToggle).data('day');
            let container = $('.time-slots-repeater[data-day="' + day + '"]');
            let closed = $('.closed-placeholder[data-day="' + day + '"]');
            let inputs = container.find('input[type="time"]');
            let hasExistingSlotIds = $(dayToggle).closest('.row').find('.has-existing-slot-ids').val();
            if ($(dayToggle).is(':checked')) {
                if(hasExistingSlotIds == 1 && isChanged == true){
                    dayStatusToggle(day, 1, dayToggle);
                }else{
                    inputs.prop('disabled', false);
                    container.find('[data-repeater-create]').prop('disabled', false);
                    container.css('opacity', 1);
                    container.show();
                    container.find('.start-time').attr('required', true);
                    container.find('.end-time').attr('required', true);
                    closed.hide();
                }
            } else {
                if(hasExistingSlotIds == 1 && isChanged == true){
                    dayStatusToggle(day, 0, dayToggle);

                }else{
                    inputs.prop('disabled', true).val('');
                    container.find('[data-repeater-create]').prop('disabled', true);
                    container.css('opacity', 0.6);
                    container.hide();
                    container.find('.start-time').removeAttr('required');
                    container.find('.end-time').removeAttr('required');
                    closed.show();
                }
            }
        }

        $('.day-toggle').each(function(){ toggleDay(this); });

        // Handle day toggle changes
        $('.day-toggle').on('change', function(){ toggleDay(this, true); });

        // Form validation and data preparation
        $('.create-form').on('submit', function(e) {
            var isValid = true;
            var hasSchedule = false;
            
            // First, disable all form inputs for unchecked days to exclude them from submission
            $('.day-toggle').each(function() {
                var day = $(this).data('day');
                var container = $('.time-slots-repeater[data-day="' + day + '"]');
                var dayInput = $('input[name*="[day]"][value="' + day + '"]');
                
                if (!$(this).is(':checked')) {
                    // Disable all inputs for inactive days to exclude them from form submission
                    container.find('input, select, textarea').prop('disabled', true);
                    dayInput.prop('disabled', true);
                } else {
                    // Enable inputs for active days
                    container.find('input, select, textarea').prop('disabled', false);
                    dayInput.prop('disabled', false);
                }
            });
            
            // Validate only checked days
            $('.day-toggle:checked').each(function() {
                var day = $(this).data('day');
                var container = $('.time-slots-repeater[data-day="' + day + '"]');
                var items = container.find('[data-repeater-item]');
                if(items.length > 0){ hasSchedule = true; }

                var slotOverlap = false;
                var times = [];
                items.each(function(){
                    var startTime = $(this).find('input[name="start_time"]').val();
                    var endTime = $(this).find('input[name="end_time"]').val();
                    if ((startTime && !endTime) || (!startTime && endTime)){
                        alert('{{ __("Please fill both start and end time for") }} ' + day.charAt(0).toUpperCase() + day.slice(1));
                        isValid = false;
                        return false;
                    }
                    if(startTime && endTime){
                        if(startTime >= endTime){
                            alert('End time must be after start time for ' + day.charAt(0).toUpperCase() + day.slice(1));
                            isValid = false;
                            return false;
                        }
                        times.push({s:startTime,e:endTime});
                    }
                });

                // Check overlaps within the day
                times.sort(function(a,b){ return a.s.localeCompare(b.s); });
                for(var i=1;i<times.length;i++){
                    if(times[i].s < times[i-1].e){
                        slotOverlap = true; break;
                    }
                }
                if(slotOverlap){
                    alert('Time slots overlap on ' + day.charAt(0).toUpperCase() + day.slice(1));
                    isValid = false;
                    return false;
                }
            });
            
            if (!hasSchedule) {
                alert('{{ __("Please set at least one day of availability.") }}');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });

        // Ensure first slot cannot be removed
        function toggleFirstRemove(container){
            var items = container.find('[data-repeater-item]');
            items.find('.remove-slot').prop('disabled', false).removeClass('d-none');
            if(items.length > 0){
                items.first().find('.remove-slot').prop('disabled', true).addClass('d-none');
            }
        }
        $('.time-slots-repeater').each(function(){ toggleFirstRemove($(this)); });
        $(document).on('click','[data-repeater-create]',function(){
            var container = $(this).closest('.time-slots-repeater');
            setTimeout(function(){ toggleFirstRemove(container); }, 120);
        });
    });

    function dayStatusToggle(day,status, dayToggle){
        let title = '';
        let confirmButtonText = '';
        let cancelButtonText = "{{ __('No, cancel') }}";
        if(status == 1){
            title = "{{ __('You have existing slots. Are you sure you want to enable this day?') }}";
            confirmButtonText = "{{ __('Yes, enable') }}";
        }else{
            title = "{{ __('You have existing slots. Are you sure you want to disable this day?') }}";
            confirmButtonText = "{{ __('Yes, disable') }}";
        }
        Swal.fire({
                title: title,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmButtonText,
                cancelButtonText: cancelButtonText
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: "POST",
                        url: "{{ route('admin.appointment.time-schedule.toggle-day') }}",
                        data: {
                            _token: "{{ csrf_token() }}",
                            day: day,
                            active: status
                        },
                        dataType: "json",
                        success: function (response) {
                            if(response && response.error == false){
                                showSuccessToast(response.message);
                                setTimeout(() => { window.location.reload(); }, 1000);
                            }else{
                                showErrorToast(response && response.message ? response.message : 'Something went wrong', 'error');
                                // revert checkbox state on failure
                                if(dayToggle){ $(dayToggle).prop('checked', !Boolean(status)); }
                            }
                        },
                        error: function(){
                            showErrorToast('Request failed', 'error');
                            if(dayToggle){ $(dayToggle).prop('checked', !Boolean(status)); }
                        }
                    });
                }else{
                    // user cancelled -> revert checkbox to previous state
                    if(dayToggle){ $(dayToggle).prop('checked', !Boolean(status)); }
                }
            });
    }
    let formSuccessFunction = () => {
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
</script>
@endsection
