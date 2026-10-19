@extends('layouts.main')

@section('title')
    {{ __('Reorder Fields') }}
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
                            <a href="{{ route('agent-verification-form-sections.index') }}">{{ __('Sections') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Reorder Fields') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        {{-- Section Info Card --}}
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">{{ $section->name }}</h5>
                        <span class="badge bg-primary">{{ __(ucwords(str_replace('_', ' ', $section->form_type))) }}</span>
                        <span class="badge bg-light-secondary text-secondary">{{ $section->agent_verification_forms->count() }} {{ __('Fields') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reorder Card --}}
        <div class="card mt-3">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">{{ __('Drag to Reorder Fields') }}</h5>
                        <small class="text-muted">{{ __('Drag the fields up or down to change their display order') }}</small>
                    </div>
                    <button class="btn btn-primary" id="save-order-btn" {{ $section->agent_verification_forms->count() <= 1 ? 'disabled' : '' }}>
                        <i class="bi bi-check-lg me-1"></i> {{ __('Save Order') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if($section->agent_verification_forms->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">{{ __('No fields in this section yet') }}</p>
                        <a href="{{ route('agent-verification-form-fields.index', ['form_type' => $section->form_type]) }}" class="btn btn-outline-primary btn-sm">
                            {{ __('Go to Fields') }}
                        </a>
                    </div>
                @else
                    <div id="sortable-fields-container">
                        @foreach($section->agent_verification_forms as $index => $field)
                            <div class="reorder-field-card d-flex align-items-center p-3 mb-2 rounded border" data-id="{{ $field->id }}" style="background: #fff;">
                                <div class="drag-grip me-3" {!! $section->agent_verification_forms->count() <= 1 ? 'style="cursor: default; opacity: 0.5;" title="' . __('Reordering requires at least two fields') . '"' : 'style="cursor: grab;"' !!}>
                                    <i class="bi bi-grip-vertical"></i>
                                </div>
                                <div class="reorder-field-number me-3">
                                    <span class="badge bg-light-secondary text-secondary rounded-circle field-number">{{ $index + 1 }}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold">{{ $field->name }}</span>
                                </div>
                                <div>
                                    <span class="badge bg-light-primary text-primary">{{ __(ucfirst($field->field_type)) }}</span>
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
            var container = document.getElementById('sortable-fields-container');
            if (container) {
                if (container.children.length > 1) {
                    var drake = dragula([container]);
                    drake.on('drop', function() {
                        updateNumbers();
                    });
                }
            }

            function updateNumbers() {
                $('#sortable-fields-container .reorder-field-card').each(function(index) {
                    $(this).find('.field-number').text(index + 1);
                });
            }

            $('#save-order-btn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spin me-1"></i> {{ __("Saving...") }}');

                var fields = [];
                $('#sortable-fields-container .reorder-field-card').each(function(index) {
                    fields.push({ id: $(this).data('id'), sort_order: index + 1 });
                });

                $.ajax({
                    url: "{{ route('agent-verification-form-sections.save-field-order') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
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
