@extends('layouts.main')

@section('title')
    {{ __('Demo Data Management') }}
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
                        <li class="breadcrumb-item">
                            <a href="{{ url('/home') }}">{{ __('Dashboard') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ __('Demo Data') }}
                        </li>
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
                        <h4 class="card-title">{{ __('Demo Data Management') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5 class="alert-heading">{{ __('About Demo Data') }}</h5>
                            <p>{{ __('This tool allows you to quickly populate your database with demo data for testing and demonstration purposes.') }}
                            </p>
                            <hr>
                            <p class="mb-0">
                                <strong>{{ __('What gets seeded:') }}</strong>
                            </p>
                            <p class="mb-0">
                                <span> -> {{ __('5 Facilities/Parameters (Bedroom, Bathroom, Kitchen, Parking, Area)') }}</span>
                                <br>
                                <span> -> {{ __('5 Categories (Villa, House, Apartment, Commercial, Plot)') }}</span>
                                <br>
                                <span> -> {{ __('8 Properties (Various types added by admin)') }}</span>
                            </p>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">{{ __('Facilities') }}</h5>
                                        <h2 class="text-primary">{{ $stats['parameters'] }}</h2>
                                        <p class="text-muted">{{ __('Total Demo Facilities') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">{{ __('Categories') }}</h5>
                                        <h2 class="text-success">{{ $stats['categories'] }}</h2>
                                        <p class="text-muted">{{ __('Total Demo Categories') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">{{ __('Properties') }}</h5>
                                        <h2 class="text-warning">{{ $stats['properties'] }}</h2>
                                        <p class="text-muted">{{ __('Total Demo Properties') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-primary btn-lg" id="seedDemoData">
                                        <i class="bi bi-database-fill-add"></i> {{ __('Seed Demo Data') }}
                                    </button>
                                    <button type="button" class="btn btn-warning btn-lg" id="resetDemoData">
                                        <i class="bi bi-arrow-clockwise"></i> {{ __('Reset Demo Data') }}
                                    </button>
                                    <button type="button" class="btn btn-danger btn-lg" id="clearDemoData">
                                        <i class="bi bi-trash"></i> {{ __('Clear Demo Data') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning mt-4">
                            <strong>{{ __('Note:') }}</strong>
                            <ul class="mb-0">
                                <li>{{ __('Seed Demo Data: Adds demo data to the database (can be run multiple times for properties)') }}</li>
                                <li>{{ __('Reset Demo Data: Clears existing demo properties and reseeds fresh data') }}</li>
                                <li>{{ __('Clear Demo Data: Removes all admin-added properties') }}</li>
                                <li>{{ __('Images: Currently using placeholder filenames. Upload actual images to the properties directory.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); z-index: 9999; justify-content: center; align-items: center;">
        <div style="text-align: center; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); min-width: 300px;">
            <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem; border-width: 0.4em;">
                <span class="visually-hidden">{{ __("Loading...") }}</span>
            </div>
            <h4 class="mt-4 mb-2" id="loadingTitle">{{ __('Processing...') }}</h4>
            <p class="text-muted mb-0" id="loadingMessage">{{ __('Please wait while we process your request') }}</p>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function () {
            // Seed Demo Data
            $('#seedDemoData').click(function () {
                if (confirm('{{ __("Are you sure you want to seed demo data? This will add new data to your database.") }}')) {
                    showLoader('{{ __("Seeding Demo Data...") }}', '{{ __("Please wait while we populate your database with demo data") }}');
                    $.ajax({
                        url: '{{ route('demo-data.seed') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            hideLoader();
                            if (!response.error) {
                                showSuccessToast(response.message);
                                setTimeout(function () {
                                    location.reload();
                                }, 1500);
                            }else{
                                showErrorToast(response.message);
                            }
                        },
                        error: function (xhr) {
                            hideLoader();
                            showErrorToast(xhr.responseJSON?.message || '{{ __("Something Went Wrong") }}');
                        }
                    });
                }
            });

            // Reset Demo Data
            $('#resetDemoData').click(function () {
                if (confirm('{{ __("Are you sure you want to reset demo data? This will clear existing demo properties and reseed fresh data.") }}')) {
                    showLoader('{{ __("Resetting Demo Data...") }}', '{{ __("Clearing existing data and reseeding fresh demo data") }}');
                    $.ajax({
                        url: '{{ route('demo-data.reset') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            hideLoader();
                            if (!response.error) {
                                showSuccessToast(response.message);
                                setTimeout(function () {
                                    location.reload();
                                }, 1500);
                            }else{
                                showErrorToast(response.message);
                            }
                        },
                        error: function (xhr) {
                            hideLoader();
                            showErrorToast(xhr.responseJSON?.message || '{{ __("Something Went Wrong") }}');
                        }
                    });
                }
            });

            // Clear Demo Data
            $('#clearDemoData').click(function () {
                if (confirm('{{ __("Are you sure you want to clear all demo data? This action cannot be undone.") }}')) {
                    showLoader('{{ __("Clearing Demo Data...") }}', '{{ __("Removing all demo data from your database") }}');
                    $.ajax({
                        url: '{{ route('demo-data.clear') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            hideLoader();
                            if (!response.error) {
                                showSuccessToast(response.message);
                                setTimeout(function () {
                                    location.reload();
                                }, 1500);
                            }else{
                                showErrorToast(response.message);
                            }
                        },
                        error: function (xhr) {
                            hideLoader();
                            showErrorToast(xhr.responseJSON?.message || '{{ __("Something Went Wrong") }}');
                        }
                    });
                }
            });
            
            function showLoader(title = '{{ __("Processing...") }}', message = '{{ __("Please wait while we process your request") }}') {
                $('#loadingTitle').text(title);
                $('#loadingMessage').text(message);
                $('#loadingOverlay').css('display', 'flex').hide().fadeIn(300);
            }

            function hideLoader() {
                $('#loadingOverlay').fadeOut(300);
            }
        });
    </script>
@endsection