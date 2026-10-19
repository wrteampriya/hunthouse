@extends('layouts.main')

@section('title')
    {{ __('FAQ') }}
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
        {{-- Create FAQ Section --}}
        <div class="card add-category mt-3">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ __('Create FAQ') }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="row">
                        {!! Form::open(['url' => route('faqs.store'), 'data-parsley-validate', 'class' => 'create-form']) !!}
                        <div class=" row">
                            <div class="col-lg-12 form-group mandatory">
                                {{ Form::label('user_type', __('Target Audience'), ['class' => 'form-label text-center']) }}
                                <select name="user_type" class="form-select form-control-sm" data-parsley-required="true">
                                    <option value="">{{ __('Select Target Audience') }}</option>
                                    <option value="user">{{ __('User') }}</option>
                                    <option value="agent">{{ __('Agent') }}</option>
                                </select>
                            </div>
                            {{-- Question --}}
                            <div class="col-lg-12 col-xl-6 form-group mandatory">
                                {{ Form::label('question', __('Question'), ['class' => 'form-label text-center']) }}
                                {{ Form::textarea('question', '', [ 'class' => 'form-control', 'placeholder' => trans('Question'), 'data-parsley-required' => 'true', 'id' => 'question', 'rows' => 2]) }}
                            </div>

                            {{-- Answer --}}
                            <div class="col-lg-12 col-xl-6 form-group mandatory">
                                {{ Form::label('answer', __('Answer'), ['class' => 'form-label text-center']) }}
                                {{ Form::textarea('answer', '', [ 'class' => 'form-control', 'placeholder' => trans('Answer'), 'data-parsley-required' => 'true', 'id' => 'answer', 'rows' => 2]) }}
                            </div>

                            {{-- Country --}}
                            <div class="col-lg-12 form-group">
                                {{ Form::label('country_id', __('Country'), ['class' => 'form-label text-center']) }}
                                <select name="country_id" class="choosen-select form-select form-control-sm">
                                    <option value="">{{ __('Global (all countries)') }}</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if(isset($languages) && $languages->count() > 0)
                                {{-- Translations Div --}}
                                <div class="translation-div mt-4">
                                    <div class="card">
                                        <div class="col-12">
                                            <div class="divider">
                                                <div class="divider-text">
                                                    <h5>{{ __('Translations for FAQ') }}</h5>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <ul class="nav nav-tabs" id="faqTranslationTabs" role="tablist">
                                                @foreach($languages as $key => $language)
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#translation-tab-{{ $language->id }}" type="button" role="tab">
                                                            {{ $language->name }}
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <div class="tab-content p-3" id="faqTranslationTabsContent">
                                                @foreach($languages as $key =>$language)
                                                    <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="translation-tab-{{ $language->id }}" role="tabpanel">
                                                        <label for="translation-question-{{ $language->id }}">{{ __('Question') }}</label>
                                                        <div class="form-group">
                                                            <input type="hidden" name="translations[{{ $key }}][question][language_id]" value="{{ $language->id }}">
                                                            <textarea name="translations[{{ $key }}][question][value]" id="translation-question-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Question') }}"></textarea>
                                                        </div>
                                                        <label for="translation-answer-{{ $language->id }}">{{ __('Answer') }}</label>
                                                        <div class="form-group">
                                                            <input type="hidden" name="translations[{{ $key }}][answer][language_id]" value="{{ $language->id }}">
                                                            <textarea name="translations[{{ $key }}][answer][value]" id="translation-answer-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Answer') }}"></textarea>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Save --}}
                            <div class="col-sm-12 col-md-12 text-end" style="margin-top:2%;">
                                {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>

    </section>

    <section class="section">
        <div class="card">
            <div class="card-body">
                {{-- User/Agent Filter Tabs --}}
                <div class="mb-3">
                    <div class="btn-group" role="group" id="user-type-filter">
                        <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="user">{{ __('User') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="agent">{{ __('Agent') }}</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped"
                            id="table_list" data-toggle="table" data-url="{{ route('faqs.show',1) }}"
                            data-click-to-select="true" data-responsive="true" data-side-pagination="server"
                            data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-trim-on-search="false" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams"
                            data-response-handler="globalTableResponseHandler">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="user_type" data-sortable="true" data-align="center" data-formatter="addedAsTagFormatter">{{ __('Target Audience') }}</th>
                                    <th scope="col" data-field="question" data-formatter="questionFormatter" data-sortable="true" style="max-width: 300px;">{{ __('Question') }}</th>
                                    <th scope="col" data-field="answer" data-formatter="answerFormatter" data-sortable="true" style="max-width: 300px;">{{ __('Answer') }}</th>
                                    <th scope="col" data-field="country.name" data-sortable="false" data-align="center" data-formatter="countryFormatter">{{ __('Country') }}</th>
                                    @if (has_permissions('update', 'faqs'))
                                        <th scope="col" data-field="status" data-sortable="false" data-align="center" data-width="5%" data-formatter="enableDisableSwitchFormatter"> {{ __('Enable/Disable') }}</th>
                                    @else
                                        <th scope="col" data-field="status" data-sortable="false" data-align="center" data-width="5%" data-formatter="yesNoStatusFormatter"> {{ __('Is Active ?') }}</th>
                                    @endif
                                    @if (has_permissions('update', 'faqs') || has_permissions('delete', 'faqs'))
                                        <th scope="col" data-field="operate" data-sortable="false" data-align="center" data-events="actionEvents"> {{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <!-- EDIT MODEL MODEL -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="FaqEditModal"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="FaqEditModal">{{ __('Edit FAQ') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal edit-form" action="{{ url('faqs') }}" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <input type="hidden" id="edit-id" name="edit_id">
                        {{-- User Type --}}
                        <div class="col-lg-12 form-group mandatory">
                            {{ Form::label('edit_user_type', __('User Type'), ['class' => 'form-label text-center']) }}
                            <select name="edit_user_type" id="edit-user-type" class="form-select form-control-sm" required>
                                <option value="user">{{ __('User') }}</option>
                                <option value="agent">{{ __('Agent') }}</option>
                            </select>
                        </div>
                        {{-- Question --}}
                        <div class="col-lg-12 form-group">
                            {{ Form::label('edit-question', __('Question'), ['class' => 'form-label text-center']) }}
                            {{ Form::textarea('edit_question', '', [ 'class' => 'form-control', 'placeholder' => trans('Question'), 'required' => true, 'id' => 'edit-question', 'rows' => 2]) }}
                        </div>

                        {{-- Answer --}}
                        <div class="col-lg-12 form-group">
                            {{ Form::label('edit-answer', __('Answer'), ['class' => 'form-label text-center']) }}
                            {{ Form::textarea('edit_answer', '', [ 'class' => 'form-control', 'placeholder' => trans('Answer'), 'required' => true, 'id' => 'edit-answer', 'rows' => 2]) }}
                        </div>

                        {{-- Country --}}
                        <div class="col-lg-12 form-group">
                            {{ Form::label('edit-country_id', __('Country'), ['class' => 'form-label text-center']) }}
                            <select name="edit_country_id" id="edit-country_id" class="choosen-select form-select form-control-sm">
                                <option value="">{{ __('Global (all countries)') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations Div --}}
                            <div class="translation-div mt-4">
                                <div class="card">
                                    <div class="col-12">
                                        <div class="divider">
                                            <div class="divider-text">
                                                <h5>{{ __('Translations for FAQ') }}</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="nav nav-tabs" id="faqEditTranslationTabs" role="tablist">
                                            @foreach($languages as $key => $language)
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link {{ $key === 0 ? 'active' : '' }}" id="edit-translation-tab-btn-{{ $language->id }}" data-bs-toggle="tab" data-bs-target="#edit-translation-tab-{{ $language->id }}" type="button" role="tab">
                                                        {{ $language->name }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="tab-content p-3" id="faqEditTranslationTabsContent">
                                            @foreach($languages as $key =>$language)
                                                <div class="tab-pane fade {{ $key === 0 ? 'show active' : '' }}" id="edit-translation-tab-{{ $language->id }}" role="tabpanel">
                                                    <label for="translation-question-{{ $language->id }}">{{ __('Question') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][question][id]" id="edit-translation-question-id-{{ $language->id }}" class="edit-question-translations">
                                                        <input type="hidden" name="translations[{{ $key }}][question][language_id]" value="{{ $language->id }}" id="edit-translation-question-id-{{ $language->id }}">
                                                        <textarea name="translations[{{ $key }}][question][value]" id="edit-translation-question-{{ $language->id }}" class="form-control edit-question-translations" value="" placeholder="{{ __('Enter Question') }}"></textarea>
                                                    </div>
                                                    <label for="translation-answer-{{ $language->id }}">{{ __('Answer') }}</label>
                                                    <div class="form-group">
                                                        <input type="hidden" name="translations[{{ $key }}][answer][id]" id="edit-translation-answer-id-{{ $language->id }}" class="edit-answer-translations">
                                                        <input type="hidden" name="translations[{{ $key }}][answer][language_id]" value="{{ $language->id }}" id="edit-translation-answer-id-{{ $language->id }}">
                                                        <textarea name="translations[{{ $key }}][answer][value]" id="edit-translation-answer-{{ $language->id }}" class="form-control edit-answer-translations" value="" placeholder="{{ __('Enter Answer') }}"></textarea>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary waves-effect waves-light" id="btn_submit">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- EDIT MODEL -->

    <!-- Question Modal -->
    <div class="modal fade" id="questionModal" tabindex="-1" aria-labelledby="questionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="questionModalLabel">{{ __('FAQ Question') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalQuestionContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Answer Modal -->
    <div class="modal fade" id="answerModal" tabindex="-1" aria-labelledby="answerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="answerModalLabel">{{ __('FAQ Answer') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalAnswerContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/dragula/3.6.6/dragula.min.js"
        integrity="sha512-MrA7WH8h42LMq8GWxQGmWjrtalBjrfIzCQ+i2EZA26cZ7OBiBd/Uct5S3NP9IBqKx5b+MMNH1PhzTsk6J9nPQQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script> --}}
    <script src=https://bevacqua.github.io/dragula/dist/dragula.js></script>
    <script>
        $('#user-type-filter button').on('click', function() {
            $('#user-type-filter button').removeClass('active');
            $(this).addClass('active');
            $('#table_list').bootstrapTable('refresh');
        });

        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                user_type: $('#user-type-filter button.active').data('value')
            };
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $("#edit-id").val(row.id);
                $("#edit-user-type").val(row.user_type || 'user');
                $("#edit-question").val(row.question);
                $("#edit-answer").val(row.answer);
                $("#edit-country_id").val(row.country_id ?? '').trigger('change');
                $(".edit-question-translations").val("");
                $(".edit-answer-translations").val("");
                if(row.translations.length > 0){
                    row.translations.forEach(translation => {
                        if(translation.key == 'question'){
                            $("#edit-translation-question-id-" + translation.language_id).val(translation.id);
                            $("#edit-translation-question-" + translation.language_id).val(translation.value);
                        }else if(translation.key == 'answer'){
                            $("#edit-translation-answer-id-" + translation.language_id).val(translation.id);
                            $("#edit-translation-answer-" + translation.language_id).val(translation.value);
                        }
                    });
                }
            }
        }

        function countryFormatter(value, row) {
            return row.country && row.country.name ? row.country.name : "{{ __('Global') }}";
        }

        // Custom formatter for question column
        function questionFormatter(value, row, index) {
            if (!value) return '-';
            
            // Sanitize the value to prevent XSS while preserving HTML formatting
            const sanitizedValue = sanitizeHtml(value);
            
            // Strip HTML tags for length calculation
            const textOnly = value.replace(/<[^>]*>/g, '');
            const maxLength = 100;
            
            if (textOnly.length <= maxLength) {
                return `<div class="question-cell">${sanitizedValue}</div>`;
            }
            
            // Truncate and sanitize
            const truncatedText = textOnly.substring(0, maxLength);
            const truncated = sanitizeHtml(truncatedText);
            
            return `
                <div class="question-cell">
                    <div class="question-preview">${truncated}...</div>
                    <button type="button" class="btn btn-link btn-sm p-0 mt-1 read-more-btn" 
                            onclick="showFullQuestion('${encodeURIComponent(value)}')" 
                            title="{{ __('Click to read full question') }}">
                        <i class="bi bi-eye"></i> {{ __('Read More') }}
                    </button>
                </div>
            `;
        }

        // Custom formatter for answer column
        function answerFormatter(value, row, index) {
            if (!value) return '-';
            
            // Sanitize the value to prevent XSS while preserving HTML formatting
            const sanitizedValue = sanitizeHtml(value);
            
            // Strip HTML tags for length calculation
            const textOnly = value.replace(/<[^>]*>/g, '');
            const maxLength = 100;
            
            if (textOnly.length <= maxLength) {
                return `<div class="answer-cell">${sanitizedValue}</div>`;
            }
            
            // Truncate and sanitize
            const truncatedText = textOnly.substring(0, maxLength);
            const truncated = sanitizeHtml(truncatedText);
            
            return `
                <div class="answer-cell">
                    <div class="answer-preview">${truncated}...</div>
                    <button type="button" class="btn btn-link btn-sm p-0 mt-1 read-more-btn" 
                            onclick="showFullAnswer('${encodeURIComponent(value)}')" 
                            title="{{ __('Click to read full answer') }}">
                        <i class="bi bi-eye"></i> {{ __('Read More') }}
                    </button>
                </div>
            `;
        }



        // Function to show full question in modal
        function showFullQuestion(encodedQuestion) {
            const question = decodeURIComponent(encodedQuestion);
            // Sanitize the question content to prevent XSS while preserving HTML formatting
            document.getElementById('modalQuestionContent').innerHTML = sanitizeHtml(question);
            
            // Show modal (Bootstrap 5 syntax)
            const modal = new bootstrap.Modal(document.getElementById('questionModal'));
            modal.show();
        }

        // Function to show full answer in modal
        function showFullAnswer(encodedAnswer) {
            const answer = decodeURIComponent(encodedAnswer);
            // Sanitize the answer content to prevent XSS while preserving HTML formatting
            document.getElementById('modalAnswerContent').innerHTML = sanitizeHtml(answer);
            
            // Show modal (Bootstrap 5 syntax)
            const modal = new bootstrap.Modal(document.getElementById('answerModal'));
            modal.show();
        }
    </script>

    <style>
        .question-cell, .answer-cell {
            max-width: 300px;
            word-wrap: break-word;
        }
        
        .question-preview, .answer-preview {
            line-height: 1.4;
        }
        
        .read-more-btn {
            font-size: 0.85rem;
            color: #0d6efd !important;
            text-decoration: none;
        }
        
        .read-more-btn:hover {
            text-decoration: underline !important;
        }
        
        #questionModal .modal-body, #answerModal .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }
    </style>
@endsection
