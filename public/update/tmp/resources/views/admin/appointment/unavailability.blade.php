@extends('layouts.main')

@section('title')
    {{ __('Admin Unavailability') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUnavailabilityModal">
                    <i class="fas fa-plus"></i> {{ __('Add Unavailability') }}
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
                        <h4 class="card-title">{{ __('Unavailability Settings') }}</h4>
                        <p class="card-text">{{ __('Set dates and times when you are not available for appointments.') }}</p>
                    </div>
                    <div class="card-body">
                        @if($unavailabilities->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Type') }}</th>
                                            <th>{{ __('Time Range') }}</th>
                                            <th>{{ __('Reason') }}</th>
                                            <th>{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($unavailabilities as $unavailability)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($unavailability->date)->format('M d, Y') }}</td>
                                                <td>
                                                    @if($unavailability->unavailability_type === 'full_day')
                                                        <span class="badge bg-danger">{{ __('Full Day') }}</span>
                                                    @else
                                                        <span class="badge bg-warning">{{ __('Specific Time') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($unavailability->unavailability_type === 'specific_time')
                                                        {{ \Carbon\Carbon::parse($unavailability->start_time)->format('g:i A') }} - 
                                                        {{ \Carbon\Carbon::parse($unavailability->end_time)->format('g:i A') }}
                                                    @else
                                                        <span class="text-muted">{{ __('All Day') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($unavailability->reason)
                                                        <span class="text-muted">{{ Str::limit($unavailability->reason, 50) }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="deleteUnavailability({{ $unavailability->id }})">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-center mt-3">
                                {{ $unavailabilities->links() }}
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">{{ __('No Unavailability Set') }}</h5>
                                <p class="text-muted">{{ __('You have not set any unavailability dates yet.') }}</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUnavailabilityModal">
                                    <i class="fas fa-plus"></i> {{ __('Set Unavailability') }}
                                </button>
                            </div>
                        @endif
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
                                <a href="{{ url('admin/appointment/extra-time-slots') }}" class="btn btn-secondary">{{ __('Previous: Extra Time Slots') }}</a>
                                <a href="{{ url('admin/appointment/preferences') }}" class="btn btn-success">{{ __('Back to Preferences') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Add Unavailability Modal -->
    <div class="modal fade" id="addUnavailabilityModal" tabindex="-1" aria-labelledby="addUnavailabilityModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUnavailabilityModalLabel">{{ __('Add Unavailability') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                {!! Form::open(['route' => 'admin.appointment.unavailability.store', 'method' => 'POST']) !!}
                    <div class="modal-body">
                        {{ csrf_field() }}
                        
                        <div class="mb-3">
                            <label for="date" class="form-label">{{ __('Date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date" name="date" required min="{{ date('Y-m-d') }}">
                        </div>

                        <div class="mb-3">
                            <label for="unavailability_type" class="form-label">{{ __('Type') }} <span class="text-danger">*</span></label>
                            <select class="form-select" id="unavailability_type" name="unavailability_type" required>
                                <option value="">{{ __('Select Type') }}</option>
                                <option value="full_day">{{ __('Full Day') }}</option>
                                <option value="specific_time">{{ __('Specific Time') }}</option>
                            </select>
                        </div>

                        <div id="timeFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_time" class="form-label">{{ __('Start Time') }} <span class="text-danger">*</span></label>
                                        <input type="time" class="form-control" id="start_time" name="start_time">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_time" class="form-label">{{ __('End Time') }} <span class="text-danger">*</span></label>
                                        <input type="time" class="form-control" id="end_time" name="end_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">{{ __('Reason (Optional)') }}</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                      placeholder="{{ __('e.g., Holiday, Personal time, Maintenance') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Add Unavailability') }}</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function deleteUnavailability(unavailabilityId) {
        if (confirm('{{ __("Are you sure you want to delete this unavailability?") }}')) {
            window.location.href = '{{ url("admin/appointment/unavailability") }}/' + unavailabilityId + '/delete';
        }
    }

    $(document).ready(function() {
        // Set minimum date to today
        var today = new Date().toISOString().split('T')[0];
        $('#date').attr('min', today);

        // Handle unavailability type change
        $('#unavailability_type').on('change', function() {
            var type = $(this).val();
            var timeFields = $('#timeFields');
            var startTime = $('#start_time');
            var endTime = $('#end_time');
            
            if (type === 'specific_time') {
                timeFields.show();
                startTime.prop('required', true);
                endTime.prop('required', true);
            } else {
                timeFields.hide();
                startTime.prop('required', false);
                endTime.prop('required', false);
                startTime.val('');
                endTime.val('');
            }
        });

        // Form validation for the modal
        $('#addUnavailabilityModal form').on('submit', function(e) {
            var type = $('#unavailability_type').val();
            
            if (type === 'specific_time') {
                var startTime = $('#start_time').val();
                var endTime = $('#end_time').val();
                
                if (!startTime || !endTime) {
                    alert('{{ __("Please fill both start and end time for specific time unavailability") }}');
                    e.preventDefault();
                    return false;
                }
                
                if (startTime >= endTime) {
                    alert('{{ __("End time must be after start time") }}');
                    e.preventDefault();
                    return false;
                }
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
    });
</script>
@endpush
