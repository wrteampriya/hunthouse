@extends('layouts.main')

@section('title')
    {{ __('City Images') }}
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
        @if (has_permissions('update', 'city_images'))
            {!! Form::open(['route' => 'city-image-settings', 'data-parsley-validate', 'class' => 'create-form', 'data-success-function'=> "formSuccessFunction"]) !!}
                <div class="card">
                    <div class="card-header">
                        <div class="divider">
                            <div class="divider-text">
                                <h4>{{ __('Settings') }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            {{-- Style --}}
                            <div class="col-lg-6 form-group mandatory">
                                <label class="form-check-label" for="city-image-style">{{ __('Style') }}</label>
                                <select name="city_image_style" id="city-image-style" class="form-control" value="{{ $cityImageStyle->data ?? 'style_1' }}">
                                    <option value="style_1">{{ __('Style 1 (With Image and Text)') }}</option>
                                    <option value="style_2">{{ __('Style 2 (Only Text)') }}</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        @endif
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                            data-toggle="table" data-url="{{ route('city-images.show',1) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                            data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                            data-responsive="true" data-sort-name="property_count" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="city" data-sortable="false">{{ __('City') }}</th>
                                    <th scope="col" data-field="image" data-sortable="false" data-align="center" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                    <th scope="col" data-field="total_properties" data-sortable="false" data-align="center">{{ __('Total Properties') }}</th>
                                    @if (has_permissions('update', 'city_images'))
                                        <th scope="col" data-field="status" data-sortable="false" data-align="center" data-width="5%" data-formatter="enableDisableCityImageSwitchFormatter"> {{ __('Enable/Disable') }}</th>
                                    @endif
                                    @if(has_permissions('delete', 'city_images') || has_permissions('update', 'city_images'))
                                        <th scope="col" data-field="operate" data-sortable="false" data-events="actionEvents" data-align="center">{{ __('Action') }}</th>
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
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="CityImageEditModal"
        aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="CityImageEditModal">{{ __('Edit City Image') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal edit-form" action="{{ url('city-images') }}" enctype="multipart/form-data" data-parsley-validate>
                        {{ csrf_field() }}
                        <input type="hidden" id="edit-id" name="edit_id">

                        {{-- Edit Image --}}
                        <div class="row">
                            {{ Form::label('image', __('Image'), ['class' => 'col-sm-12 col-form-label']) }}
                            <div class="col-md-12 col-12">
                                <input accept="image/png,image/jpg,image/jpeg,image/webp" name='image' type='file' id="edit-image" class="filepond" />
                            </div>
                            <div class="col-md-12 col-12 text-center" id="image-preview-div" style="display:none">
                                <img id="image-preview" height="100" width="110" />
                            </div>
                        </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary waves-effect waves-light" id="btn_submit">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        function formSuccessFunction(response) {
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
        $(document).ready(function () {
            $('#city-image-style').val('{{ $cityImageStyle->data ?? 'style_1' }}');
        });
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search
            };
        }
        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $('#edit-id').val(row.id);
                if(row.image){
                    $("#image-preview-div").show();
                    $("#image-preview").attr('src',row.image);
                }else{
                    $("#image-preview").removeAttr('src');
                    $("#image-preview-div").hide();
                }
            }

        }
    </script>
@endsection
