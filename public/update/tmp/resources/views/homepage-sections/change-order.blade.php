@extends('layouts.main')

@section('title')
    {{ __('Change Homepage Section Order') }}
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
                            <a href="{{ route('homepage-sections.index') }}">{{ __('Homepage Sections') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Change Order') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">{{ __('Homepage Sections') }}</h5>
                        <span class="badge bg-light-secondary text-secondary">{{ $sections->count() }} {{ __('Sections') }}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('homepage-sections.index') }}" class="btn btn-light-secondary">
                            <i class="bi bi-arrow-left me-1"></i> {{ __('Back') }}
                        </a>
                        <button class="btn btn-primary" id="save-order-btn" {{ $sections->count() <= 1 ? 'disabled' : '' }}>
                            <i class="bi bi-check-lg me-1"></i> {{ __('Save Order') }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                @if($sections->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">{{ __('No homepage sections found') }}</p>
                    </div>
                @else
                    <div id="sortable-homepage-sections-container">
                        @foreach($sections as $index => $section)
                            <div class="reorder-homepage-section-card d-flex align-items-center p-3 mb-2 rounded border"
                                data-id="{{ $section->id }}"
                                style="background: #fff;">
                                <div class="drag-grip me-3" {!! $sections->count() <= 1 ? 'style="cursor: default; opacity: 0.5;"' : 'style="cursor: grab;"' !!}>
                                    <i class="bi bi-grip-vertical fs-5"></i>
                                </div>
                                <div class="me-3">
                                    <span class="badge bg-light-secondary text-secondary rounded-circle homepage-section-number"
                                        style="width:28px;height:28px;line-height:20px;display:inline-flex;align-items:center;justify-content:center;">
                                        {{ $index + 1 }}
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ $section->title }}</div>
                                    <small class="text-muted">{{ $sectionTypes[$section->section_type] ?? $section->section_type }}</small>
                                </div>
                                <div>
                                    @if($section->is_active)
                                        <span class="badge bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ __('Deactive') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            var container = document.getElementById('sortable-homepage-sections-container');
            if (container && container.children.length > 1) {
                var drake = dragula([container]);
                drake.on('drop', function() {
                    updateNumbers();
                });
            }

            function updateNumbers() {
                $('#sortable-homepage-sections-container .reorder-homepage-section-card').each(function(index) {
                    $(this).find('.homepage-section-number').text(index + 1);
                });
            }

            $('#save-order-btn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spin me-1"></i> {{ __("Saving...") }}');

                var sections = [];
                $('#sortable-homepage-sections-container .reorder-homepage-section-card').each(function(index) {
                    sections.push({
                        id: $(this).data('id'),
                        sort_order: index + 1
                    });
                });

                $.ajax({
                    url: "{{ route('homepage-sections.update-order') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        sections: sections
                    },
                    success: function(response) {
                        if (response.error) {
                            showErrorToast(response.message);
                        } else {
                            showSuccessToast(response.message);
                        }
                        btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> {{ __("Save Order") }}');
                    },
                    error: function() {
                        showErrorToast("{{ __('Error updating order') }}");
                        btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> {{ __("Save Order") }}');
                    }
                });
            });
        });
    </script>
@endsection
