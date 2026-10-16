@extends('layouts.main')

@section('title')
    {{ __('Manage Agent Verification') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"> </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <a href="{{ route('agent-verification.index') }}" class="btn btn-primary">{{ __('Back') }}</a>
        <div class="card mt-3">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ __('Agent Verification Details') }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="row">
                        {{-- Agent Id --}}
                        <div class="col-lg-4">
                            {{ Form::label('agent-id', __('Agent Id'), ['class' => 'form-label text-center']) }}
                            {{ Form::text('agent-id', $customerVerification->user->id, [ 'class' => 'form-control', 'readonly','disabled' => true]) }}
                        </div>

                        {{-- Agent Name --}}
                        <div class="col-lg-4">
                            {{ Form::label('agent-name', __('Agent Name'), ['class' => 'form-label text-center']) }}
                            {{ Form::text('agent-name', $customerVerification->user->name, [ 'class' => 'form-control', 'readonly','disabled' => true]) }}
                        </div>

                        {{-- Agent Verification Status --}}
                        <div class="col-lg-4">
                            {{ Form::label('verification-status', __('Verification Status'), ['class' => 'form-label d-block']) }}
                            @php
                                if($customerVerification->status == 'approved'){
                                    $btnClass = 'btn btn-success';
                                }else if($customerVerification->status == 'rejected'){
                                    $btnClass = 'btn btn-danger';
                                } else {
                                    $btnClass = 'btn btn-warning';
                                }
                            @endphp
                            {{ Form::text('verification-status', ucfirst(__($customerVerification->status)), [ 'class' => $btnClass, 'placeholder' => trans('Question'),'readonly','disabled' => true]) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>


        @foreach ($sections as $section)
            <div class="card mt-3">
                <div class="card-header">
                    <div class="divider">
                        <div class="divider-text">
                            <h4>{{ $section['name'] }}</h4>
                        </div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="row">
                            @foreach ($section['values'] as $customerFormValue)
                                <div class="col-lg-4">
                                    @switch($customerFormValue->verify_form->field_type)
                                        @case('text')
                                            <div class="form-group">
                                                <label>{{ $customerFormValue->verify_form->translated_name }}</label>
                                                <input type="text" class="form-control" value="{{ $customerFormValue->value }}" disabled>
                                            </div>
                                            @break

                                        @case('textarea')
                                            <div class="form-group">
                                                <label>{{ $customerFormValue->verify_form->translated_name }}</label>
                                                <textarea class="form-control" disabled>{{ $customerFormValue->value }}</textarea>
                                            </div>
                                            @break

                                        @case('number')
                                            <div class="form-group">
                                                <label>{{ $customerFormValue->verify_form->translated_name }}</label>
                                                <input type="number" class="form-control" value="{{ $customerFormValue->value }}" disabled>
                                            </div>
                                            @break

                                        @case('checkbox')
                                            <div class="form-group">
                                                <label>{{ $customerFormValue->verify_form->translated_name }}</label>
                                                @php
                                                    $selectedValues = is_array($customerFormValue->value) ? $customerFormValue->value : explode(',', $customerFormValue->value);
                                                @endphp
                                                @foreach ($customerFormValue->verify_form->form_fields_values as $option)
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            @if(in_array($option->value, $selectedValues))
                                                                checked
                                                            @endif
                                                            disabled>
                                                        <label class="form-check-label">{{ $option->translated_value }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @break

                                        @case('dropdown')
                                            <div class="form-group">
                                                <label>{{ $customerFormValue->verify_form->translated_name }}</label>
                                                <select class="form-select form-control-sm" disabled>
                                                    @foreach ($customerFormValue->verify_form->form_fields_values as $option)
                                                        <option value="{{ $option->value }}" {{ $customerFormValue->value == $option->value ? 'selected' : '' }}>{{ $option->translated_value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @break

                                        @case('radio')
                                            <div class="form-group">
                                                <label>{{ $customerFormValue->verify_form->translated_name }}</label>
                                                @foreach ($customerFormValue->verify_form->form_fields_values as $option)
                                                    <div class="form-check">
                                                        <input type="radio" class="form-check-input" name="{{ $customerFormValue->verify_form->name }}" value="{{ $option->value }}" {{ $customerFormValue->value == $option->value ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label">{{ $option->translated_value }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @break

                                        @case('file')
                                            @php
                                                $file_type = $customerFormValue->file_type;
                                            @endphp
                                            @switch($file_type)
                                            @case('image')
                                                <div class="form-group">
                                                    <label>{{ $customerFormValue->verify_form->translated_name }} :- </label>
                                                    @if(!empty($customerFormValue->value))
                                                        <a href="{{ $customerFormValue->value }}" target="_blank">{{ __('View File') }}</a>
                                                    @endif
                                                </div>
                                                @break

                                            @case('pdf')
                                            @case('txt')
                                            @case('doc')
                                            @case('docx')
                                                <div class="form-group">
                                                    <label>{{ $customerFormValue->verify_form->translated_name }} :- </label>
                                                    @if(!empty($customerFormValue->value))
                                                        <a href="{{ $customerFormValue->value }}" target="_blank" download>{{ __('Download File') }}</a>
                                                    @endif
                                                </div>
                                                @break
                                            @endswitch
                                        @default
                                            @break
                                    @endswitch
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </section>
@endsection
