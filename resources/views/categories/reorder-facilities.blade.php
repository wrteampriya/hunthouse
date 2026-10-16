@extends('layouts.main')

@section('title')
    {{ __('Reorder Parameters') }}
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
                            <a href="{{ route('categories.index') }}">{{ __('Categories') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Reorder Parameters') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        {{-- Category Info Card --}}
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    @if($category->image)
                        <img src="{{ $category->image }}" alt="{{ $category->category }}" width="48" height="48" class="rounded">
                    @endif
                    <div>
                        <h5 class="mb-1">{{ $category->category }}</h5>
                        <span class="badge bg-light-secondary text-secondary">{{ $parameters->count() }} {{ __('Parameters') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reorder Card --}}
        <div class="card mt-3">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">{{ __('Drag to Reorder Parameters') }}</h5>
                        <small class="text-muted">{{ __('Drag parameters up or down to change the order they appear on property forms') }}</small>
                    </div>
                    <button class="btn btn-primary" id="save-order-btn" {{ $parameters->isEmpty() ? 'disabled' : '' }}>
                        <i class="bi bi-check-lg me-1"></i> {{ __('Save Order') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if($parameters->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">{{ __('No parameters assigned to this category yet') }}</p>
                        <a href="{{ route('categories.edit', $category->id) }}" class="btn btn-outline-primary btn-sm">
                            {{ __('Go to Edit Category') }}
                        </a>
                    </div>
                @else
                    <div id="sortable-parameters-container">
                        @foreach($parameters as $index => $parameter)
                            <div class="reorder-parameter-card d-flex align-items-center p-3 mb-2 rounded border"
                                 data-id="{{ $parameter->id }}"
                                 style="background: #fff; cursor: grab;">
                                <div class="drag-grip me-3 text-muted">
                                    <i class="bi bi-grip-vertical fs-5"></i>
                                </div>
                                <div class="reorder-parameter-number me-3">
                                    <span class="badge bg-light-secondary text-secondary rounded-circle parameter-number"
                                          style="width:28px;height:28px;line-height:20px;display:inline-flex;align-items:center;justify-content:center;">
                                        {{ $index + 1 }}
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold">{{ $parameter->name }}</span>
                                </div>
                                <div>
                                    <span class="badge bg-light-primary text-primary">{{ __(ucfirst($parameter->type_of_parameter ?? 'text')) }}</span>
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
            var container = document.getElementById('sortable-parameters-container');
            if (container) {
                var drake = dragula([container]);
                drake.on('drop', function() {
                    updateNumbers();
                });
            }

            function updateNumbers() {
                $('#sortable-parameters-container .reorder-parameter-card').each(function(index) {
                    $(this).find('.parameter-number').text(index + 1);
                });
            }

            $('#save-order-btn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spin me-1"></i> {{ __("Saving...") }}');

                var fields = [];
                $('#sortable-parameters-container .reorder-parameter-card').each(function(index) {
                    fields.push({ id: $(this).data('id'), sort_order: index + 1 });
                });

                $.ajax({
                    url: "{{ route('categories.save-facility-order') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        category_id: {{ $category->id }},
                        fields: fields
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
