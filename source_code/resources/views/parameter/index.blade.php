@extends('layouts.main')

@section('title')
    {{ __('Facility') }}
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
        <div class="card">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ __('Create Facility') }}</h4>
                    </div>
                </div>
            </div>

            <div class="card-content">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            {!! Form::open(['url' => route('parameters.store'), 'data-parsley-validate', 'files' => true, 'class' => 'create-form','data-pre-submit-function','data-success-function'=> "formSuccessFunction"]) !!}
                                @csrf

                                <div class="row">
                                    {{-- Facility Name --}}
                                    <div class="col-sm-12 col-md-6 col-lg-3 form-group mandatory">
                                        {{ Form::label('type', __('Facility Name'), ['class' => 'form-label text-center']) }}
                                        {{ Form::text('parameter', '', ['class' => 'form-control', 'placeholder' => trans('Facility Name'), 'data-parsley-required' => 'true']) }}
                                    </div>

                                    {{-- Type --}}
                                    <div class="col-sm-12 col-md-6 col-lg-3 form-group mandatory">
                                        {{ Form::label('type', __('Type'), ['class' => 'form-label text-center']) }}
                                        <select name="options" id="options" class="form-select form-control-sm" data-parsley-required=true>
                                            <option value="">{{ __('Select Type') }}</option>
                                            <option value="textbox">{{ __('Text Box') }}</option>
                                            <option value="textarea">{{ __('Text Area') }}</option>
                                            <option value="dropdown">{{ __('Dropdown') }}</option>
                                            <option value="radiobutton">{{ __('Radio Button') }}</option>
                                            <option value="checkbox">{{ __('Checkbox') }}</option>
                                            <option value="file">{{ __('File') }}</option>
                                            <option value="number">{{ __('Number') }}</option>
                                        </select>
                                    </div>

                                    {{-- Image --}}
                                    <div class="col-sm-12 col-md-6 col-lg-3 form-group mandatory">
                                        {{ Form::label('image', __('Image'), ['class' => 'form-label text-center']) }}
                                        <input type="file" class="filepond" id="image" name="image" accept="image/svg+xml" required>
                                    </div>

                                    {{-- Is Required --}}
                                    <div class="col-sm-12 col-md-6 col-lg-3">
                                        {{ Form::label('is_required', __('Is Required ?'), ['class' => 'col-form-label text-center']) }}
                                        <div class="form-check form-switch col-12">
                                            {{ Form::checkbox('is_required', '1', false, ['class' => 'form-check-input', 'id' => 'is-required']) }}
                                        </div>
                                    </div>

                                    {{-- Options --}}
                                    <input type="hidden" name="optionvalues" id="optionvalues">
                                    <div class="row pt-5" id="elements"> </div>

                                    @if(isset($languages) && $languages->count() > 0)
                                        {{-- Translations Div --}}
                                        <div class="translation-div">
                                            <div class="col-12">
                                                <div class="divider">
                                                    <div class="divider-text">
                                                        <h5>{{ __('Translations for Facility Name') }}</h5>
                                                    </div>
                                                </div>
                                            </div>
                                            {{-- Fields for Translations --}}
                                            @foreach($languages as $key =>$language)
                                                <div class="col-md-6 col-xl-4">
                                                    <div class="form-group">
                                                        <label for="translation-{{ $language->id }}">{{ $language->name }}</label>
                                                        <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                                        <input type="text" name="translations[{{ $key }}][value]" id="translation-{{ $language->id }}" class="form-control" value="" placeholder="{{ __('Enter Facility Name') }}">
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif



                                    <div class="col-12  d-flex justify-content-end pt-3">
                                        {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1', 'id' => 'btn_submit']) }}
                                    </div>
                                </div>
                                {!! Form::close() !!}
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    @if (has_permissions('read', 'facility'))
        <section class="section">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">

                            <table class="table table-striped"
                                id="table_list" data-toggle="table" data-url="{{ url('parameter-list') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                                data-responsive="true" data-sort-name="id" data-sort-order="desc"
                                data-pagination-successively-size="3" data-query-params="queryParams">
                                <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                        <th scope="col" data-field="image" data-sortable="false" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                        <th scope="col" data-field="type_of_parameter" data-formatter="typeFormatter"> {{ __('Type') }}</th>
                                        <th scope="col" data-field="is_required" data-formatter="yesNoStatusFormatter"> {{ __('Is Required ?') }}</th>
                                        <th scope="col" data-field="value" data-sortable="true">{{ __('Value') }}</th>
                                        @if (has_permissions('update', 'facility'))
                                            <th scope="col" data-field="operate" data-sortable="false" data-events="parameterEvents">{{ __('Action') }} </th>
                                        @endif
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <!-- EDIT MODEL MODEL -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel1">{{ __('Edit Facility') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ url('parameter-update') }}" class="form-horizontal" enctype="multipart/form-data" method="POST" data-parsley-validate>
                        {{ csrf_field() }}
                        <input type="hidden" id="edit_id" name="edit_id">

                        {{-- Edit Name --}}
                        <div class="row">
                            <div class="col-md-12 col-12">
                                <div class="form-group mandatory">
                                    <label for="edit-name" class="form-label col-12">{{ __('Name') }}</label>
                                    <input type="text" id="edit-name" class="form-control col-12" placeholder="" name="edit_name" data-parsley-required="true">
                                </div>
                            </div>
                        </div>
                        {{-- Edit Image --}}
                        <div class="row">
                            {{ Form::label('image', __('Image'), ['class' => 'col-sm-12 col-form-label']) }}
                            <div class="col-md-12 col-12">
                                <input accept="image/svg+xml" name='image' type='file' id="edit_image" class="filepond" />
                            </div>
                            <div class="col-md-12 col-12 text-center edit-image-preview-div" style="display: none;">
                                <img id="edit-image-preview" height="100" width="110" />
                            </div>
                        </div>

                        {{-- Is Required --}}
                        <div class="col-12">
                            {{ Form::label('edit-is-required', __('Is Required ?'), ['class' => 'col-form-label text-center']) }}
                            <div class="form-check form-switch col-12">
                                {{ Form::checkbox('edit_is_required', '1', false, ['class' => 'form-check-input', 'id' => 'edit-is-required']) }}
                            </div>
                        </div>

                        {{-- Options with Translations --}}
                        <div class="row" id="edit-options-container" style="display: none;">
                            <div class="col-12">
                                <div class="divider">
                                    <div class="divider-text">
                                        <h5>{{ __('Options and Translations') }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div id="edit-options-list"></div>
                        </div>

                        @if(isset($languages) && $languages->count() > 0)
                            {{-- Translations Div --}}
                            <div class="translation-div">
                                <div class="col-12">
                                    <div class="divider">
                                        <div class="divider-text">
                                            <h5>{{ __('Translations for Facility Name') }}</h5>
                                        </div>
                                    </div>
                                </div>
                                {{-- Fields for Translations --}}
                                @foreach($languages as $key =>$language)
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <input type="hidden" name="translations[{{ $key }}][id]" value="" id="edit-translation-id-{{ $language->id }}" class="edit-translations">
                                            <label for="translation-{{ $language->id }}">{{ $language->name }}</label>
                                            <input type="hidden" name="translations[{{ $key }}][language_id]" value="{{ $language->id }}">
                                            <input type="text" name="translations[{{ $key }}][value]" id="edit-translation-{{ $language->id }}" class="form-control edit-translations" value="" placeholder="{{ __('Enter Facility Name') }}">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary waves-effect waves-light" id="edit_btn_submit">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
    </div>
    <!-- EDIT MODEL -->
@endsection

@section('script')
    <script>
        // Pass languages data to JavaScript
        var languages = @json($languages ?? []);
        var optionCounter = 0;

        // Register custom Parsley validators to avoid regex parsing issues
        if (typeof Parsley !== 'undefined') {
            // Custom validator for option values (no commas, quotes)
            Parsley.addValidator('noSpecialChars', {
                validateString: function(value) {
                    if (!value) return true;
                    // Check for commas, single quotes, or double quotes
                    return !/[,'"]/.test(value);
                },
                messages: {
                    en: 'Option value cannot contain commas, single or double quotes',
                    'default': 'Option value cannot contain commas, single or double quotes'
                }
            });

            // Custom validator for translations (no quotes)
            Parsley.addValidator('noQuotes', {
                validateString: function(value) {
                    if (!value) return true;
                    // Check for single quotes or double quotes
                    return !/['"]/.test(value);
                },
                messages: {
                    en: 'Translation cannot contain single or double quotes',
                    'default': 'Translation cannot contain single or double quotes'
                }
            });
        }

        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search
            };
        }

        function typeFormatter(value, row) {
            let translations = {
                'textbox': '{{ __("Text Box") }}',
                'textarea': '{{ __("Text Area") }}',
                'dropdown': '{{ __("Dropdown") }}',
                'radiobutton': '{{ __("Radio Button") }}',
                'checkbox': '{{ __("Checkbox") }}',
                'file': '{{ __("File") }}',
                'number': '{{ __("Number") }}'
            };
            return translations[value] || value;
        }

        window.parameterEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $("#edit_id").val(row.id);
                $("#edit-name").val(row.name);
                $("#edit-image-preview").attr('src', row.image);
                if(row.image != null){
                    $(".edit-image-preview-div").show();
                }else{
                    $(".edit-image-preview-div").hide();
                }
                if(row.is_required){
                    $("#edit-is-required").prop('checked', true);
                }else{
                    $("#edit-is-required").prop('checked', false);
                }

                // Handle options with translations
                if(row.type_of_parameter == "dropdown" || row.type_of_parameter == "checkbox" || row.type_of_parameter == "radiobutton") {
                    $("#edit-options-container").show();
                    populateEditOptions(row.type_values);
                } else {
                    $("#edit-options-container").hide();
                }

                $(".edit-translations").val("");
                if(row.translations){
                    $.each(row.translations, function(key, value) {
                        $("#edit-translation-id-" + value.language_id).val(value.id);
                        $("#edit-translation-" + value.language_id).val(value.value);
                    });
                }
            }
        }

        function populateEditOptions(typeValues) {
            $("#edit-options-list").empty();

            if(typeValues && typeValues.length > 0) {
                $.each(typeValues, function(optionIndex, option) {
                    var optionValue = option.value || option; // Handle both new and old format
                    var optionTranslations = option.translations || [];

                    var optionHtml = '<div class="card mb-3" style="width: 100%;">' +
                        '<div class="card-header">' +
                        '<h6 class="mb-0">{{ __("Option") }} ' + (optionIndex + 1) + '</h6>' +
                        '</div>' +
                        '<div class="card-body">' +
                        '<div class="row">' +
                        '<div class="col-md-12">' +
                        '<label class="form-label">{{ __("Option Value") }}</label>' +
                        `<input type='text' class='form-control' value='${optionValue}' disabled>` +
                        `<input type='hidden' name='edit_option_values[]' value='${optionValue}'>` +
                        '</div>' +
                        '</div>';

                    // Add translation fields if languages exist
                    if (languages && languages.length > 0) {
                        optionHtml += '<div class="row mt-3">' +
                            '<div class="col-12">' +
                            '<h6>{{ __("Translations for this option") }}</h6>' +
                            '</div>' +
                            '</div>' +
                            '<div class="row">';

                        languages.forEach(function(language, langIndex) {
                            var translationValue = '';
                            var translationId = '';

                            // Find existing translation for this language
                            if(optionTranslations && optionTranslations.length > 0) {
                                $.each(optionTranslations, function(transIndex, translation) {
                                    if(translation.language_id == language.id) {
                                        translationValue = translation.value || '';
                                        translationId = translation.id || '';
                                    }
                                });
                            }

                            optionHtml += '<div class="col-md-6 col-lg-4 mb-2">' +
                                '<label for="edit-option-translation-' + optionIndex + '-' + language.id + '" class="form-label">' + language.name + '</label>' +
                                '<input type="hidden" name="edit_option_translations[' + optionIndex + '][' + langIndex + '][id]" value="' + translationId + '">' +
                                '<input type="hidden" name="edit_option_translations[' + optionIndex + '][' + langIndex + '][language_id]" value="' + language.id + '">' +
                                `<input type='text' name='edit_option_translations[${optionIndex}][${langIndex}][value]' id='edit-option-translation-${optionIndex}-${language.id}' class='form-control' value='${translationValue}' data-parsley-no-quotes="true" placeholder='{{ __("Enter translation") }}'>` +
                                '</div>';
                        });

                        optionHtml += '</div>';
                    }

                    optionHtml += '</div></div>';

                    $("#edit-options-list").append(optionHtml);
                });
            }
        }

        window.onload = function() {
            $('#add_options').hide();
            $('#edit_opt').hide();
        }

        function createOptionWithTranslations(optionIndex, canDelete = true) {
            var optionHtml = '<div class="card mb-3 option-card" style="width: 100%;" id="op">' +
                '<div class="card-header">' +
                '<h6 class="mb-0">{{ __("Option") }} ' + (optionIndex + 1) + '</h6>' +
                '</div>' +
                '<div class="card-body">' +
                '<div class="row">' +
                '<div class="col-md-8">' +
                '<label class="form-label">{{ __("Option Value") }}</label>' +
                '<input type="text" class="form-control opt" name="opt[]" data-parsley-required="true" data-parsley-no-special-chars="true" placeholder="{{ __("Enter option value") }}">' +
                '</div>' +
                '<div class="col-md-4 d-flex align-items-end">' +
                '<button type="button" class="btn btn-danger btn-sm remove-option-btn"' + (!canDelete ? ' disabled' : '') + '>' +
                '<i class="bi bi-trash"></i> {{ __("Remove") }}' +
                '</button>' +
                '</div>' +
                '</div>';

            // Add translation fields if languages exist
            if (languages && languages.length > 0) {
                optionHtml += '<div class="row mt-3">' +
                    '<div class="col-12">' +
                    '<h6>{{ __("Translations for this option") }}</h6>' +
                    '</div>' +
                    '</div>' +
                    '<div class="row">';

                languages.forEach(function(language, index) {
                    optionHtml += '<div class="col-md-6 col-lg-4 mb-2">' +
                        '<label for="option-translation-' + optionIndex + '-' + language.id + '" class="form-label">' + language.name + '</label>' +
                        '<input type="hidden" name="option_translations[' + optionIndex + '][' + index + '][language_id]" value="' + language.id + '">' +
                        '<input type="text" name="option_translations[' + optionIndex + '][' + index + '][value]" id="option-translation-' + optionIndex + '-' + language.id + '" class="form-control" data-parsley-no-quotes="true" placeholder="{{ __("Enter translation") }}">' +
                        '</div>';
                });

                optionHtml += '</div>';
            }

            optionHtml += '</div></div>';

            return optionHtml;
        }

        function updateRemoveButtons() {
            var optionCount = $('#elements .option-card').length;
            $('#elements .remove-option-btn').each(function(index) {
                // Disable first two buttons or when only 2 options remain
                if (index < 2 || optionCount <= 2) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
        }

        $('#options').on('change', function() {
            selected_option = $('#options').val();
            if (selected_option == "radiobutton" || selected_option == "dropdown" || selected_option == "checkbox") {
                $('#elements').empty();
                $('#add_options').show();
                optionCounter = 0;

                // Add first two required options
                $('#elements').append(createOptionWithTranslations(0, false)); // First option - cannot delete
                $('#elements').append(createOptionWithTranslations(1, false)); // Second option - cannot delete
                optionCounter = 2;

                // Add "Add More" button
                $('#elements').append(
                    '<div class="text-center mb-3">' +
                    '<button type="button" class="btn btn-success" id="button-addon2">' +
                    '<i class="bi bi-plus"></i> {{ __("Add More Options") }}' +
                    '</button>' +
                    '</div>'
                );

                // Handle add more options
                $('#button-addon2').off('click').on('click', function() {
                    var newOption = createOptionWithTranslations(optionCounter, true);
                    $(this).parent().before(newOption);
                    optionCounter++;
                    updateRemoveButtons();
                });

                // Handle remove option
                $("body").off("click", ".remove-option-btn").on("click", ".remove-option-btn", function() {
                    if (!$(this).prop('disabled')) {
                        $(this).closest("#op").remove();
                        updateRemoveButtons();
                        // Update option numbers in headers
                        updateOptionNumbers();
                    }
                });

                // Initial update of remove buttons
                updateRemoveButtons();

            } else {
                $('#elements').empty();
                optionCounter = 0;
            }
        });

        function updateOptionNumbers() {
            $('#elements .option-card').each(function(index) {
                $(this).find('.card-header h6').text('{{ __("Option") }} ' + (index + 1));
            });
        }


        sum = [];
        optionTranslations = [];

        function preSubmitFunction() {
            // Collect option values
            sum = [];
            $('#elements .opt').each(function() {
                sum.push($(this).val().trimEnd());
            });
            $('#optionvalues').val(sum);

            // Collect option translations
            optionTranslations = [];
            $('#elements .option-card').each(function(optionIndex) {
                var optionTranslationData = [];
                $(this).find('input[name*="option_translations"]').each(function() {
                    var name = $(this).attr('name');
                    var value = $(this).val();
                    if (name && value) {
                        optionTranslationData.push({
                            name: name,
                            value: value
                        });
                    }
                });
                if (optionTranslationData.length > 0) {
                    optionTranslations.push(optionTranslationData);
                }
            });
        }

        function formSuccessFunction(response) {
            if(!response.error){
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        }




        function setValue(id) {
            $('#edit_options').val($("#" + id).parents('tr:first').find('td:nth-child(4)').text()).trigger('change');
            if ($('#svg_clr').val() == 1) {
                src = ($("#" + id).parents('tr:first').find('td:nth-child(3)').find($('.svg-img'))).attr('src');
            } else {
                src = ($("#" + id).parents('tr:first').find('td:nth-child(3)').find($('.image-popup-no-margins'))).attr('href');
            }
            $('#blah').attr('src', src);

            // $('#image').attr('src', src);

            if ($('#edit_options').val() == "checkbox" || $('#edit_options').val() == "radiobutton" || $('#edit_options') .val() == "dropdown") {
                val_str = ($("#" + id).parents('tr:first').find('td:nth-child(6)').text());
                arr = val_str.split(",");
                $('#edit_elements').empty();
                $.each(arr, function(key, value) {




                    newRowAdd =

                        ' <div class="card" style="width:15rem;" id="edit_op">' +
                        '<div class="row">' +
                        ' <div class="col-6">' +
                        ' <input type="text" class="form-control opt" name="edit_opt[]" id="first_value" value="' +
                        value +
                        '" data-parsley-required="true">' +
                        '      </div>' +
                        ' <div class="col-1">' +

                        '<button type="button" class="btn btn-primary me-1 mb-1 mt-0 ' + key + '" id="btn2" ' + (
                            key == 0 ?
                            'disabled' : '') + '> x</button>' +
                        '</div>' +
                        ' </div>' +
                        '</div>';

                    $('#edit_elements').append(

                        newRowAdd

                    );
                });
            }
            $('#edit_image').click(function() {

                $('#blah').hide();


            });
            if ($('#first_value').val() == 'null') {
                $('#first_value').val('');
            }
        }
    </script>
@endsection

