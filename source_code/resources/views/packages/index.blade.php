@extends('layouts.main')

@section('title')
    {{ $type == 'agent' ? __('Agent Packages') : __('Packages') }}
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
        <div class="card">
            @if (has_permissions('create', 'package'))
                <div class="card-header">
                    <div class="row">
                        <div class="col-12 col-xs-12 d-flex justify-content-end">
                            <a href="{{ $type == 'agent' ? route('agent-packages.create', ['user_type' => 'agent']) : route('package.create') }}" class="btn btn-primary">{{ __('Add Package') }}</a>
                        </div>
                    </div>
                </div>
            @endif
            <hr>
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        {{-- User/Agent Filter Tabs --}}
                        <div class="mb-3">
                            <div class="btn-group" role="group" id="package-type-filter">
                                <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                                <button type="button" class="btn btn-outline-primary" data-value="user">{{ __('User') }}</button>
                                <button type="button" class="btn btn-outline-primary" data-value="agent">{{ __('Agent') }}</button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped" id="table_list" data-toggle="table"
                                    data-url="{{ $type == 'agent' ? route('agent-packages.show', 1) : route('package.show', 1) }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-search-align="right"
                                    data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                                    data-trim-on-search="false" data-responsive="true" data-sort-name="id"
                                    data-sort-order="desc" data-pagination-successively-size="3"
                                    data-query-params="queryParams">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="true"> {{ __('ID') }}
                                            </th>
                                            <th scope="col" data-field="user_type" data-sortable="true" data-formatter="userTypeFormatter"> {{ __('Target Audience') }}</th>
                                            <th scope="col" data-field="ios_product_id" data-align="center"
                                                data-sortable="true"> {{ __('IOS Product ID') }} </th>
                                            <th scope="col" data-field="name" data-align="center" data-sortable="true">
                                                {{ __('Name') }} </th>
                                            <th scope="col" data-field="duration" data-align="center"
                                                data-sortable="false"> {{ __('Duration (In Days)') }}</th>
                                            <th scope="col" data-field="list_duration_type" data-align="center"
                                                data-sortable="false"> {{ __('List Duration Type') }}</th>
                                            <th scope="col" data-field="custom_duration" data-align="center"
                                                data-sortable="false" data-formatter="durationDaysFormatter"> {{ __('Days') }}</th>
                                            <th scope="col" data-field="package_type" data-align="center"
                                                data-sortable="false" data-formatter="packageTypeFormatter">
                                                {{ __('Package Type') }} </th>
                                            <th scope="col" data-field="price" data-align="center"
                                                data-sortable="false" data-formatter="packagePriceFormatter">
                                                {{ __('Price') }} </th>
                                            <th scope="col" data-field="package_features" data-sortable="false"
                                                data-formatter="packageFeaturesFormatter"> {{ __('Features') }} </th>
                                            @if (has_permissions('update', 'package') && has_permissions('delete', 'package'))
                                                <th scope="col" data-field="status" data-sortable="false"
                                                    data-align="center" data-formatter="enableDisableSwitchFormatter">
                                                    {{ __('Enable/Disable') }}</th>
                                                <th scope="col" data-field="operate" data-align="center"
                                                    data-sortable="false" data-events="actionEvents"> {{ __('Action') }}
                                                </th>
                                            @elseif (has_permissions('delete', 'package') && !has_permissions('update', 'package'))
                                                <th scope="col" data-field="status" data-sortable="true"
                                                    data-align="center" data-formatter="yesNoStatusFormatter">
                                                    {{ __('Is Active ?') }}</th>
                                                <th scope="col" data-field="operate" data-align="center"
                                                    data-sortable="false" data-events="actionEvents"> {{ __('Action') }}
                                                </th>
                                            @else
                                                <th scope="col" data-field="status" data-sortable="true"
                                                    data-align="center" data-formatter="yesNoStatusFormatter">
                                                    {{ __('Is Active ?') }}</th>
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

        <!-- EDIT MODEL MODEL -->
        <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ $type == 'agent' ? __('Edit Agent Package') : __('Edit Package') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ $type == 'agent' ? url('agent-packages') : url('package') }}"
                        class="form-horizontal edit-form" enctype="multipart/form-data" method="POST"
                        data-success-function="formSuccessFunction">
                        @csrf
                        {{ method_field('PUT') }}
                        <div class="modal-body">
                            <input type="hidden" name="edit_id" id="edit-id">

                            {{-- Target Audience --}}
                            <div class="col-12 form-group mandatory">
                                {{ Form::label('edit_user_type', __('Target Audience'), ['class' => 'form-label']) }}
                                <select name="user_type" id="edit_user_type" class="form-select" data-parsley-required="true" disabled>
                                    <option value="user">{{ __('User') }}</option>
                                    <option value="agent">{{ __('Agent') }}</option>
                                </select>
                            </div>

                            <div class="row">
                                {{-- Package Name --}}
                                <div class="col-12 form-group mandatory">
                                    {{ Form::label('edit-name', __('Package Name'), ['class' => 'form-label']) }}
                                    {{ Form::text('name', '', [
                                        'class' => 'form-control ',
                                        'placeholder' => trans('Package Name'),
                                        'data-parsley-required' => 'true',
                                        'id' => 'edit-name',
                                    ]) }}
                                </div>

                                {{-- IOS Product ID --}}
                                <div class="col-12 form-group">
                                    {{ Form::label('edit-ios-product-id', __('IOS Product ID'), ['class' => 'form-label']) }}
                                    {{ Form::text('ios_product_id', '', [
                                        'class' => 'form-control ',
                                        'placeholder' => trans('IOS Product ID'),
                                        'id' => 'edit-ios-product-id',
                                    ]) }}
                                </div>

                                {{-- Duration --}}
                                <div class="col-12 form-group mandatory">
                                    {{ Form::label('edit-duration', __('Duration (In Days)'), ['class' => 'form-label']) }}
                                    {{ Form::number('duration', '', [
                                        'class' => 'form-control ',
                                        'placeholder' => trans('Duration (In Days)'),
                                        'data-parsley-required' => 'true',
                                        'id' => 'edit-duration',
                                        'min' => '1',
                                        'max' => '730',
                                    ]) }}
                                </div>
                                {{-- Price --}}
                                <div class="col-12 form-group mandatory" id="edit-price-div">
                                    {{ Form::label('edit-price', __('Price') . '(' . $currency_symbol . ')', ['class' => 'form-label']) }}
                                    {{ Form::number('price', '', [
                                        'class' => 'form-control ',
                                        'placeholder' => trans('Price'),
                                        'id' => 'edit-price',
                                        'data-parsley-required' => 'true',
                                        'min' => '0',
                                        'step' => '0.01',
                                    ]) }}
                                </div>

                                {{-- Purchase Type --}}
                                <div class="col-12 form-group mandatory">
                                    {{ Form::label('', __('Purchase Type'), ['class' => 'form-label col-12 ']) }}

                                    {{-- Unlimited --}}
                                    {{ Form::radio('purchase_type', 'unlimited', null, ['class' => 'form-check-input edit-purchase-type', 'data-parsley-required' => 'true', 'id' => 'edit-purchase-type-unlimited']) }}
                                    {{ Form::label('edit-purchase-type-unlimited', __('Unlimited'), ['class' => 'form-check-label']) }}

                                    {{-- One Time --}}
                                    {{ Form::radio('purchase_type', 'one_time', null, ['class' => 'form-check-input edit-purchase-type', 'data-parsley-required' => 'true', 'id' => 'edit-purchase-type-one-time']) }}
                                    {{ Form::label('edit-purchase-type-one-time', __('One Time'), ['class' => 'form-check-label']) }}
                                </div>

                                {{-- List Duration Type (read-only) --}}
                                <div class="col-12 form-group" id="edit-list-duration-type-div">
                                    {{ Form::label('edit-list-duration-type', __('List Duration Type'), ['class' => 'form-label']) }}
                                    <input type="text" id="edit-list-duration-type" class="form-control" readonly>
                                </div>

                                {{-- Days (read-only) --}}
                                <div class="col-12 form-group" id="edit-days-div">
                                    {{ Form::label('edit-days', __('Days'), ['class' => 'form-label']) }}
                                    <input type="text" id="edit-days" class="form-control" readonly>
                                </div>

                                @if (isset($languages) && $languages->count() > 0)
                                    {{-- Translations Div --}}
                                    <div class="translation-div mt-4">
                                        <div class="col-12">
                                            <div class="divider">
                                                <div class="divider-text">
                                                    <h5>{{ __('Translations for Package Name') }}</h5>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Fields for Translations --}}
                                        @foreach ($languages as $key => $language)
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label
                                                        for="translation-{{ $language->id }}">{{ $language->name }}</label>
                                                    <input type="hidden" name="translations[{{ $key }}][id]"
                                                        value="" class="edit-translations"
                                                        id="edit-translation-id-{{ $language->id }}">
                                                    <input type="hidden"
                                                        name="translations[{{ $key }}][language_id]"
                                                        value="{{ $language->id }}">
                                                    <input type="text" name="translations[{{ $key }}][value]"
                                                        id="edit-translation-{{ $language->id }}"
                                                        class="form-control edit-translations" value=""
                                                        placeholder="{{ __('Enter Package Name') }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary waves-effect"
                                data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit"
                                class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- EDIT MODEL -->
    </section>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            @if(!empty($type) && $type !== 'user')
                $('#package-type-filter button').removeClass('active');
                $('#package-type-filter button[data-value="{{ $type }}"]').addClass('active');
            @endif
        });

        $('#package-type-filter button').on('click', function() {
            $('#package-type-filter button').removeClass('active');
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
                user_type: $('#package-type-filter button.active').data('value') || '',
            };
        }

        function userTypeFormatter(value, row) {
            return value == 'agent' ? '<span class="badge bg-info">' + "{{ __('Agent') }}" + '</span>' : '<span class="badge bg-primary">' + "{{ __('User') }}" + '</span>';
        }

        function durationDaysFormatter(value, row) {
            if (row.list_duration_type == 'Custom') {
                return row.custom_duration ? row.custom_duration : '-';
            } else if (row.list_duration_type == 'Standard') {
                return '30';
            } else if (row.list_duration_type == 'Package') {
                return row.duration ? row.duration : '-';
            }
            return '-';
        }

        function chk(checkbox) {
            if (checkbox.checked) {

                active(event.target.id);

            } else {

                disable(event.target.id);
            }
        }
        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $("#edit-id").val(row.id);
                $("#edit-name").val(row.name);
                $("#edit_user_type").val(row.user_type);
                $("#edit-duration").val(row.duration);
                if (row.package_type == 'paid') {
                    // Show Price Div
                    $("#edit-price-div").show();
                    $('#edit-price').val(row.price).attr('data-parsley-required', true)
                    // Show IOS Product ID Div
                    $("#edit-ios-product-id-div").show();
                    $("#edit-ios-product-id").val(row.ios_product_id);
                } else {
                    // Hide Price Div
                    $('#edit-price').val("").removeAttr('data-parsley-required')
                    $("#edit-price-div").hide();
                    // Hide IOS Product ID Div
                    $("#edit-ios-product-id-div").hide();
                    $("#edit-ios-product-id").val("");
                }
                // Set Purchase Type
                if (row.purchase_type == 'one_time') {
                    $("#edit-purchase-type-one-time").prop('checked', true);
                } else {
                    $("#edit-purchase-type-unlimited").prop('checked', true);
                }
                // List Duration Type & Days (read-only)
                const listDurationType = row.list_duration_type || '';
                $("#edit-list-duration-type").val(listDurationType);
                if (listDurationType) {
                    $("#edit-list-duration-type-div").show();
                } else {
                    $("#edit-list-duration-type-div").hide();
                }

                if (listDurationType == 'Custom') {
                    $("#edit-days").val(row.custom_duration || '');
                    $("#edit-days-div").show();
                } else if (listDurationType == 'Standard') {
                    $("#edit-days").val('30');
                    $("#edit-days-div").show();
                } else {
                    $("#edit-days").val('');
                    $("#edit-days-div").hide();
                }
                // Translations
                $(".edit-translations").val("");
                if (row.translations.length > 0) {
                    row.translations.forEach(translation => {
                        $("#edit-translation-id-" + translation.language_id).val(translation.id);
                        $("#edit-translation-" + translation.language_id).val(translation.value);
                    });
                }
            }
        }
    </script>

    <script>
        window.onload = function() {

            $('#limitation_for_property').hide();

            $('#limitation_for_advertisement').hide();
            $('.limitations').hide();

        }


        $('input[type="radio"][name="package_type"]').click(function() {
            if ($(this).is(':checked')) {
                if ($(this).val() == 'product_listing') {
                    $('.limitations').show();
                } else {
                    $('.limitations').hide();

                }
            }

        });

        $('input[type="radio"][name="typep"]').click(function() {


            if ($(this).is(':checked')) {
                if ($(this).val() == 'add_limited_property') {
                    $('#limitation_for_property').show();
                    $('#propertylimit').attr('required', 'true');
                } else {
                    $('#limitation_for_property').hide();
                    $('#propertylimit').removeAttr('required');
                }
            }
        });
        $('input[type="radio"][name="typel"]').click(function() {

            if ($(this).is(':checked')) {
                if ($(this).val() == 'add_limited_advertisement') {

                    $('#limitation_for_advertisement').show();
                    $('#advertisementlimit').attr("required", "true");
                } else {
                    $('#limitation_for_advertisement').hide();
                    $('#advertisementlimit').removeAttr("required");
                }
            }
        });


        function disable(id) {
            $.ajax({
                url: "{{ route('package.updatestatus') }}",
                type: "POST",
                data: {
                    '_token': "{{ csrf_token() }}",
                    "id": id,
                    "status": 0,
                },
                cache: false,
                success: function(result) {
                    let text = '{{ trans('Package OFF Successfully') }}';
                    if (result.error == false) {
                        Toastify({
                            text: text,
                            duration: 6000,
                            close: !0,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    } else {
                        Toastify({
                            text: result.message,
                            duration: 6000,
                            close: !0,
                            backgroundColor: '#dc3545' //"linear-gradient(to right, #dc3545, #96c93d)"

                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    }

                },
                error: function(error) {

                }
            });
        }

        function active(id) {
            $.ajax({
                url: "{{ route('package.updatestatus') }}",
                type: "POST",
                data: {
                    '_token': "{{ csrf_token() }}",
                    "id": id,
                    "status": 1,
                },
                cache: false,
                success: function(result) {

                    if (result.error == false) {
                        let text = '{{ trans('Package On Successfully') }}';
                        Toastify({
                            text: text,
                            duration: 6000,
                            close: !0,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    } else {
                        Toastify({
                            text: result.message,
                            duration: 6000,
                            close: !0,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    }

                },
                error: function(error) {

                }
            });
        }

        let formSuccessFunction = () => {
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    </script>
@endsection
