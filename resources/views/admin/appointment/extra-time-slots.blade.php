@extends('layouts.main')

@section('title')
    {{ __('Admin Extra Time Slots') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSlotModal">
                    <i class="fas fa-plus"></i> {{ __('Add Extra Time Slot') }}
                </button>
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
                        <h4 class="card-title">{{ __('Extra Time Slots') }}</h4>
                        <p class="card-text">{{ __('Manage additional time slots outside your regular schedule.') }}</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped"
                                    id="table_list" data-toggle="table" data-url="{{ route('admin.appointment.extra-time-slots.list') }}"
                                    data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                    data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                                    data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                                    data-sort-name="date" data-sort-order="desc" data-pagination-successively-size="3">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th scope="col" data-field="id" data-visible="false">ID</th>
                                            <th scope="col" data-field="date" data-sortable="true">{{ __('Date') }}</th>
                                            <th scope="col" data-field="start_time" data-sortable="true">{{ __('Start Time') }}</th>
                                            <th scope="col" data-field="end_time" data-sortable="true">{{ __('End Time') }}</th>
                                            <th scope="col" data-field="duration" data-sortable="false">{{ __('Duration') }}</th>
                                            @if (has_permissions('delete', 'admin_appointment_schedules'))
                                                <th scope="col" data-field="operate" data-align="center" data-sortable="false">{{ __('Action') }}</th>
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

        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <a href="{{ url('admin/appointment/time-schedule') }}" class="btn btn-secondary">{{ __('Previous: Time Schedule') }}</a>
                                {{-- <a href="{{ url('admin/appointment/unavailability') }}" class="btn btn-info">{{ __('Next: Unavailability') }}</a> --}}
                                <a href="{{ url('admin/appointment/preferences') }}" class="btn btn-success">{{ __('Back to Preferences') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Add Extra Time Slot Modal -->
    <div class="modal fade" id="addSlotModal" tabindex="-1" aria-labelledby="addSlotModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSlotModalLabel">{{ __('Add Extra Time Slot') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                {!! Form::open(['route' => 'admin.appointment.extra-time-slots.store', 'method' => 'POST', 'class' => 'create-form','data-success-function'=> "formSuccessFunction"]) !!}
                    <div class="modal-body">
                        {{ csrf_field() }}
                        
                        <div class="mb-3">
                            <label for="date" class="form-label">{{ __('Date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date" name="date" required min="{{ date('Y-m-d') }}">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">{{ __('Start Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">{{ __('End Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" required>
                                </div>
                            </div>
                        </div>

                        {{-- <div class="mb-3">
                            <label for="reason" class="form-label">{{ __('Reason (Optional)') }}</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                      placeholder="{{ __('e.g., Special consultation hours, Extended availability') }}"></textarea>
                        </div> --}}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Add Slot') }}</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    function formSuccessFunction (response) {
        $("#addSlotModal").modal("hide");
    }
    $(document).ready(function() {
        // Set minimum date to today
        var today = new Date().toISOString().split('T')[0];
        $('#date').attr('min', today);

        // Form validation for the modal
        $('#addSlotModal form').on('submit', function(e) {
            var startTime = $('#start_time').val();
            var endTime = $('#end_time').val();
            
            if (startTime && endTime && startTime >= endTime) {
                alert('{{ __("End time must be after start time") }}');
                e.preventDefault();
                return false;
            }
        });

        // Auto-set end time when start time changes
        $('#start_time').on('change', function() {
            var startTime = $(this).val();
            if (startTime) {
                // Add 1 hour by default
                var start = new Date('2000-01-01T' + startTime);
                var end = new Date(start.getTime() + 60 * 60 * 1000); // Add 1 hour
                var endTimeStr = end.toTimeString().slice(0, 5);
                $('#end_time').val(endTimeStr);
            }
        });

        // Initialize bootstrap table (footer_script prevents auto init until we restore the attribute)
        const $table = $('#table_list');
        if ($table.attr('data-toggle') !== 'table') {
            $table.attr('data-toggle', 'table');
        }
    });
</script>
@endsection
