@extends('layouts.main')

@section('title')
    {{ __('Pay As You Go Packages') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped" id="table_list" data-toggle="table"
                                    data-url="{{ route('pay-as-you-go.show') }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20]" data-search="true" data-search-align="right"
                                    data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                                    data-trim-on-search="false" data-responsive="true" data-sort-name="id"
                                    data-sort-order="desc" data-pagination-successively-size="3"
                                    data-query-params="queryParams">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="true"> {{ __('ID') }} </th>
                                            <th scope="col" data-field="name" data-align="center" data-sortable="true">{{ __('Name') }} </th>
                                            <th scope="col" data-field="type" data-align="center" data-sortable="true">{{ __('Type') }} </th>
                                            <th scope="col" data-field="price" data-align="center" data-sortable="true" data-formatter="packagePriceFormatter">{{ __('Price') }} </th>
                                            <th scope="col" data-field="ios_product_id" data-align="center" data-sortable="true">{{ __('IOS Product ID') }} </th>
                                            @if (has_permissions('update', 'package'))
                                                <th scope="col" data-field="status" data-sortable="false"
                                                    data-align="center" data-formatter="enableDisableSwitchFormatter">
                                                    {{ __('Enable/Disable') }}</th>
                                                <th scope="col" data-field="operate" data-align="center"
                                                    data-sortable="false" data-events="actionEvents"> {{ __('Action') }}
                                                </th>
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

        <!-- EDIT MODEL -->
        <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Edit Pay As You Go Package') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="edit-pay-as-you-go-form" class="form-horizontal create-form" action="" method="POST" data-parsley-validate data-success-function="editFormSuccessFunction">
                        <div class="modal-body">
                            {{ csrf_field() }}
                            <input type="hidden" id="edit-id" name="edit_id">

                            <div class="row">
                                <div class="col-12 form-group mandatory">
                                    {{ Form::label('edit-name', __('Name'), ['class' => 'form-label']) }}
                                    {{ Form::text('name', '', [
                                        'class' => 'form-control',
                                        'placeholder' => trans('Name'),
                                        'id' => 'edit-name',
                                        'required' => true,
                                        'data-parsley-required' => 'true'
                                    ]) }}
                                </div>

                                <div class="col-12 form-group mandatory">
                                    {{ Form::label('edit-price', __('Price') . '(' . $currency_symbol . ')', ['class' => 'form-label']) }}
                                    {{ Form::number('price', '', [
                                        'class' => 'form-control',
                                        'placeholder' => trans('Price'),
                                        'id' => 'edit-price',
                                        'min' => '1',
                                        'required' => true,
                                        'data-parsley-required' => 'true',
                                        'data-parsley-min' => '1'
                                    ]) }}
                                </div>

                                <div class="col-12 form-group">
                                    {{ Form::label('edit-ios-product-id', __('IOS Product ID'), ['class' => 'form-label']) }}
                                    {{ Form::text('ios_product_id', '', [
                                        'class' => 'form-control',
                                        'placeholder' => trans('IOS Product ID'),
                                        'id' => 'edit-ios-product-id',
                                    ]) }}
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" id="save-pay-as-you-go" class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
            };
        }

        function packagePriceFormatter(value, row) {
            return row.price_symbol + value;
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $("#edit-id").val(row.id);
                $("#edit-name").val(row.name);
                $("#edit-price").val(row.price);
                $("#edit-ios-product-id").val(row.ios_product_id);
                
                $('#edit-pay-as-you-go-form').attr('action', '{{ url("pay-as-you-go") }}/' + row.id);
            }
        }

        function editFormSuccessFunction() {
            setTimeout(() => {
                $('#editModal').modal('hide');
                $('#table_list').bootstrapTable('refresh');
            }, 1000);
        }
    </script>
@endsection
