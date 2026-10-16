@extends('layouts.main')

@section('title')
    {{ __('Admin Appointment Settings') }}
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
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('Appointment Management') }}</h4>
                        <p class="card-text">{{ __('Configure your appointment settings and availability schedule.') }}</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Preferences Card -->
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-cog fa-3x text-primary mb-3"></i>
                                        <h5 class="card-title">{{ __('Preferences') }}</h5>
                                        <p class="card-text">{{ __('Set meeting duration, buffer times, and general appointment rules.') }}</p>
                                        <a href="{{ route('admin.appointment.preferences.index') }}" class="btn btn-primary">
                                            {{ __('Configure') }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Schedule Card -->
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                                        <h5 class="card-title">{{ __('Time Schedule') }}</h5>
                                        <p class="card-text">{{ __('Set your weekly availability schedule and working hours.') }}</p>
                                        <a href="{{ route('admin.appointment.time-schedule.index') }}" class="btn btn-success">
                                            {{ __('Set Schedule') }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Extra Time Slots Card -->
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clock fa-3x text-info mb-3"></i>
                                        <h5 class="card-title">{{ __('Extra Time Slots') }}</h5>
                                        <p class="card-text">{{ __('Add additional availability outside your regular schedule.') }}</p>
                                        <a href="{{ route('admin.appointment.extra-time-slots.index') }}" class="btn btn-info">
                                            {{ __('Manage Slots') }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Unavailability Card -->
                            {{-- <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card h-100 border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                                        <h5 class="card-title">{{ __('Unavailability') }}</h5>
                                        <p class="card-text">{{ __('Set dates and times when you are not available.') }}</p>
                                        <a href="{{ route('admin.appointment.unavailability.index') }}" class="btn btn-warning">
                                            {{ __('Set Unavailable') }}
                                        </a>
                                    </div>
                                </div>
                            </div> --}}
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>{{ __('Getting Started:') }}</strong>
                                    <ol class="mb-0 mt-2">
                                        <li>{{ __('Start with Preferences to set your basic appointment rules') }}</li>
                                        <li>{{ __('Configure your Time Schedule for regular weekly availability') }}</li>
                                        <li>{{ __('Add Extra Time Slots for special occasions') }}</li>
                                        {{-- <li>{{ __('Set Unavailability for holidays and time off') }}</li> --}}
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
