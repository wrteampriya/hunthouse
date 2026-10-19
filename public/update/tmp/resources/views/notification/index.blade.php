@extends('layouts.main')

@section('title')
    {{ __('Send Notification') }}
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
    <div class="row">
        <section class="section">
            <div class="row">
                {{-- Notification Form --}}
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="divider">
                                <div class="divider-text">
                                    <h4>{{ __('Compose Notification') }}</h4>
                                </div>
                            </div>
                        </div>
                        <form action="{{ route('notification.store') }}" class="needs-validation" method="post"
                            data-parsley-validate enctype="multipart/form-data">
                            {{ csrf_field() }}

                            <div class="card-body">
                                <textarea id="user_id" name="user_id" style="display: none"></textarea>
                                <textarea id="agent_user_id" name="agent_user_id" style="display: none"></textarea>
                                <textarea id="fcm_id" name="fcm_id" style="display: none"></textarea>
                                <textarea id="agent_fcm_id" name="agent_fcm_id" style="display: none"></textarea>
                                <input type="hidden" name="type" value="0">
                                <input type="hidden" id="send_type_hidden" name="send_type" value="everyone">

                                {{-- Audience Selection --}}
                                <div class="form-group row">
                                    <div class="col-md-12 col-sm-12">
                                        <label class="form-label">{{ __('Send To') }} <span class="text-danger">*</span></label>
                                        <div class="d-flex flex-column gap-2 mt-1">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="send_target" id="target_everyone" value="everyone" checked>
                                                <label class="form-check-label" for="target_everyone">{{ __('Everyone') }} <small class="text-muted">({{ __('Users & Agents') }})</small></label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="send_target" id="target_users" value="all_users">
                                                <label class="form-check-label" for="target_users">{{ __('All Users') }}</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="send_target" id="target_agents" value="all_agents">
                                                <label class="form-check-label" for="target_agents">{{ __('All Agents') }}</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="send_target" id="target_specific" value="specific">
                                                <label class="form-check-label" for="target_specific">{{ __('Specific People') }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Customer Selection (inline, shows for Specific People) --}}
                                <div id="customer-selection-inline" style="display: none;">
                                    <div class="card bg-light border mt-2 mb-3">
                                        <div class="card-header pb-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="m-0">{{ __('Select Recipients') }}</h6>
                                                <div>
                                                    <span class="badge bg-primary" id="user_selected_count">0 {{ __('user(s)') }}</span>
                                                    <span class="badge bg-info" id="agent_selected_count">0 {{ __('agent(s)') }}</span>
                                                    <button class="btn btn-sm btn-outline-secondary ms-1" type="button" id="clear_selection">{{ __('Clear') }}</button>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <div class="btn-group" role="group" id="people-tabs">
                                                    <button type="button" class="btn btn-sm btn-outline-primary active" data-tab="users">
                                                        <i class="bi bi-person me-1"></i>{{ __('Users') }}
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-tab="agents">
                                                        <i class="bi bi-person-badge me-1"></i>{{ __('Agents') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body pt-2">
                                            {{-- Users Table --}}
                                            <div id="users-table-section">
                                                <small class="text-muted d-block mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('Selected people will receive notification in their User mode') }}</small>
                                                <table class="table table-striped" id="users_table">
                                                    <thead class="thead-dark">
                                                        <tr>
                                                            <th scope="col" data-field="state" data-checkbox="true"></th>
                                                            <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                                            <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                                            <th scope="col" data-field="mobile" data-sortable="true">{{ __('Number') }}</th>
                                                        </tr>
                                                    </thead>
                                                </table>
                                            </div>

                                            {{-- Agents Table --}}
                                            <div id="agents-table-section" style="display: none;">
                                                <small class="text-muted d-block mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('Selected people will receive notification in their Agent mode') }}</small>
                                                <table class="table table-striped" id="agents_table">
                                                    <thead class="thead-dark">
                                                        <tr>
                                                            <th scope="col" data-field="state" data-checkbox="true"></th>
                                                            <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                                            <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                                            <th scope="col" data-field="mobile" data-sortable="true">{{ __('Number') }}</th>
                                                        </tr>
                                                    </thead>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Title --}}
                                <div class="form-group row">
                                    <div class="col-md-12 col-sm-12">
                                        <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                                        <input name="title" type="text" class="form-control" placeholder="{{ __('Title') }}" required>
                                    </div>
                                </div>

                                {{-- Message --}}
                                <div class="form-group row">
                                    <div class="col-md-12">
                                        <label class="form-label">{{ __('Message') }} <span class="text-danger">*</span></label>
                                        <textarea name="message" class="form-control" placeholder="{{ __('Message') }}" required></textarea>
                                    </div>
                                </div>

                                {{-- Include Image --}}
                                <div class="form-group row">
                                    <div class="col-md-12 col-sm-12">
                                        <div class="form-check">
                                            <input id="include_image" name="include_image" type="checkbox" class="form-check-input">
                                            <label class="form-check-label">{{ __('Include Image') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row" id="show_image" style="display: none">
                                    <div class="col-md-12 col-sm-12">
                                        <label class="form-label">{{ __('Image') }}</label>
                                        <input id="file" name="file" type="file" accept="image/jpeg, image/png, image/jpg, image/webp" class="filepond">
                                        <small class="text-muted">{{ __('Only JPG, JPEG and PNG files are allowed') }}</small>
                                        <small class="text-muted">{{ __('Max Size: 3MB') }}</small>
                                    </div>
                                </div>

                                {{-- Property --}}
                                <div class="col-md-12 col-12 form-group">
                                    <label class="form-label">{{ __('Property') }}</label>
                                    <select name="property" class="choosen-select form-select form-control-sm" id="property">
                                        <option value="">{{ __('Select Option') }}</option>
                                        @foreach ($property_list as $row)
                                            <option value="{{ $row->id }}">{{ $row->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-12 d-flex justify-content-end mt-2">
                                    <button class="btn btn-primary" type="submit" name="submit">{{ __('Submit') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            {{-- Notification History --}}
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div id="toolbar">
                                <button class="btn btn-danger btn-sm btn-icon text-white" id="delete_multiple"
                                    title="Delete Notification"><em class='fa fa-trash'></em></button>
                            </div>
                            <table aria-describedby="mydesc" class='table-striped' id="table_list1" data-toggle="table"
                                data-url="{{ url('notificationList') }}" data-click-to-select="true"
                                data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                                data-responsive="true" data-sort-name="id" data-sort-order="desc"
                                data-pagination-successively-size="3">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="state" data-checkbox="true"></th>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="title" data-sortable="true">{{ __('Title') }}</th>
                                        <th scope="col" data-field="message" data-sortable="true">{{ __('Message') }}</th>
                                        <th scope="col" data-field="image" data-sortable="false" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                        <th scope="col" data-field="type" data-sortable="true">{{ __('Type') }}</th>
                                        <th scope="col" data-field="send_type" data-sortable="true">{{ __('Message Type') }}</th>
                                        <th scope="col" data-field="role_context" data-sortable="true" data-formatter="addedAsTagFormatter">{{ __('Target') }}</th>
                                        <th scope="col" data-field="customer_data" data-sortable="false" data-formatter="notificationCustomerFormatter">{{ __('Selected Users') }}</th>
                                        <th scope="col" data-field="operate" data-sortable="false" data-events="actionEvents">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.11/lodash.min.js"></script>

    <script type="text/javascript">
        // -------------------------------------------------------
        // State management — separate lists for users and agents
        // -------------------------------------------------------
        var user_list       = [];
        var user_fcm_list   = [];
        var user_fcm_map    = {};

        var agent_list      = [];
        var agent_fcm_list  = [];
        var agent_fcm_map   = {};

        var isRestoring     = false;

        var $usersTable  = $('#users_table');
        var $agentsTable = $('#agents_table');

        // -------------------------------------------------------
        // Response handlers
        // -------------------------------------------------------
        function userResponseHandler(res) {
            $.each(res.rows, function(i, row) {
                row.state = ($.inArray(row.id, user_list) !== -1);
                if (row.fcm_id) user_fcm_map[row.id] = row.fcm_id;
            });
            return res;
        }

        function agentResponseHandler(res) {
            $.each(res.rows, function(i, row) {
                row.state = ($.inArray(row.id, agent_list) !== -1);
                if (row.fcm_id) agent_fcm_map[row.id] = row.fcm_id;
            });
            return res;
        }

        // -------------------------------------------------------
        // Post-body: restore checkboxes after page change
        // -------------------------------------------------------
        $usersTable.on('post-body.bs.table', function() {
            isRestoring = true;
            $usersTable.find('tbody tr').each(function() {
                var row_id = parseInt($(this).find('td').eq(1).text().trim());
                if ($.inArray(row_id, user_list) !== -1) {
                    $(this).find('input[type="checkbox"]').prop('checked', true);
                    $(this).addClass('selected');
                }
            });
            isRestoring = false;
        });

        $agentsTable.on('post-body.bs.table', function() {
            isRestoring = true;
            $agentsTable.find('tbody tr').each(function() {
                var row_id = parseInt($(this).find('td').eq(1).text().trim());
                if ($.inArray(row_id, agent_list) !== -1) {
                    $(this).find('input[type="checkbox"]').prop('checked', true);
                    $(this).addClass('selected');
                }
            });
            isRestoring = false;
        });

        // -------------------------------------------------------
        // Update UI counts
        // -------------------------------------------------------
        function updateSelectionUI() {
            $('textarea#user_id').val(user_list.join(','));
            $('textarea#fcm_id').val(user_fcm_list.join(','));
            $('textarea#agent_user_id').val(agent_list.join(','));
            $('textarea#agent_fcm_id').val(agent_fcm_list.join(','));
            $('#user_selected_count').text(user_list.length + ' {{ __("user(s)") }}');
            $('#agent_selected_count').text(agent_list.length + ' {{ __("agent(s)") }}');
            $('#user_badge_count').text(user_list.length);
            $('#agent_badge_count').text(agent_list.length);
        }

        // -------------------------------------------------------
        // Users table check/uncheck
        // -------------------------------------------------------
        function handleCheck(list, fcmList, fcmMap, e, row) {
            if (isRestoring) return;
            var id = row.id;
            var fcm = row.fcm_id || fcmMap[id];
            if (fcm) fcmMap[id] = fcm;

            if (e.type === 'check') {
                if ($.inArray(id, list) === -1) list.push(id);
                if (fcm && $.inArray(fcm, fcmList) === -1) fcmList.push(fcm);
            } else {
                var idx = list.indexOf(id);
                if (idx > -1) list.splice(idx, 1);
                if (fcm) { var fi = fcmList.indexOf(fcm); if (fi > -1) fcmList.splice(fi, 1); }
            }
            updateSelectionUI();
        }

        function handleCheckAll(list, fcmList, fcmMap, e, rowsAfter, rowsBefore) {
            if (isRestoring) return;
            var rows = (e.type === 'uncheck-all') ? rowsBefore : rowsAfter;
            rows = !$.isArray(rows) ? [rows] : rows;
            $.each(rows, function(i, row) {
                handleCheck(list, fcmList, fcmMap, e, row);
            });
        }

        $usersTable.on('check.bs.table uncheck.bs.table', function(e, row) { handleCheck(user_list, user_fcm_list, user_fcm_map, e, row); });
        $usersTable.on('check-all.bs.table uncheck-all.bs.table', function(e, ra, rb) { handleCheckAll(user_list, user_fcm_list, user_fcm_map, e, ra, rb); });
        $agentsTable.on('check.bs.table uncheck.bs.table', function(e, row) { handleCheck(agent_list, agent_fcm_list, agent_fcm_map, e, row); });
        $agentsTable.on('check-all.bs.table uncheck-all.bs.table', function(e, ra, rb) { handleCheckAll(agent_list, agent_fcm_list, agent_fcm_map, e, ra, rb); });

        // -------------------------------------------------------
        // Tabs
        // -------------------------------------------------------
        $('#people-tabs button').on('click', function() {
            $('#people-tabs button').removeClass('active');
            $(this).addClass('active');
            var tab = $(this).data('tab');
            if (tab === 'users') {
                $('#users-table-section').show();
                $('#agents-table-section').hide();
            } else {
                $('#users-table-section').hide();
                $('#agents-table-section').show();
            }
        });

        // -------------------------------------------------------
        // Initialize tables manually (they start hidden)
        // -------------------------------------------------------
        var tablesInitialized = false;

        function initCustomerTables() {
            if (tablesInitialized) return;
            $usersTable.bootstrapTable({
                url: '{{ url("customerList") }}',
                clickToSelect: true,
                sidePagination: 'server',
                pagination: true,
                pageList: [5, 10, 20, 50, 100, 200],
                search: true,
                showRefresh: true,
                trimOnSearch: false,
                responsive: true,
                sortName: 'id',
                sortOrder: 'desc',
                paginationSuccessivelySize: 3,
                queryParams: userTableQueryParams,
                responseHandler: userResponseHandler
            });
            $agentsTable.bootstrapTable({
                url: '{{ url("customerList") }}',
                clickToSelect: true,
                sidePagination: 'server',
                pagination: true,
                pageList: [5, 10, 20, 50, 100, 200],
                search: true,
                showRefresh: true,
                trimOnSearch: false,
                responsive: true,
                sortName: 'id',
                sortOrder: 'desc',
                paginationSuccessivelySize: 3,
                queryParams: agentTableQueryParams,
                responseHandler: agentResponseHandler
            });
            tablesInitialized = true;
        }

        // -------------------------------------------------------
        // Audience radio toggle
        // -------------------------------------------------------
        $('input[name="send_target"]').on('change', function() {
            var val = $(this).val();
            $('#send_type_hidden').val(val);
            if (val === 'specific') {
                $('#customer-selection-inline').show('fast', function() {
                    initCustomerTables();
                });
            } else {
                $('#customer-selection-inline').hide('fast');
                clearAllSelections();
            }
        });

        // -------------------------------------------------------
        // Clear selections
        // -------------------------------------------------------
        function clearAllSelections() {
            user_list = []; user_fcm_list = []; user_fcm_map = {};
            agent_list = []; agent_fcm_list = []; agent_fcm_map = {};
            updateSelectionUI();
            isRestoring = true;
            $usersTable.find('tbody tr').each(function() { $(this).find('input[type="checkbox"]').prop('checked', false); $(this).removeClass('selected'); });
            $agentsTable.find('tbody tr').each(function() { $(this).find('input[type="checkbox"]').prop('checked', false); $(this).removeClass('selected'); });
            isRestoring = false;
        }

        $('#clear_selection').on('click', clearAllSelections);

        // -------------------------------------------------------
        // Query params
        // -------------------------------------------------------
        function userTableQueryParams(p) {
            return { sort: p.sort, order: p.order, offset: p.offset, limit: p.limit, search: p.search };
        }

        function agentTableQueryParams(p) {
            return { sort: p.sort, order: p.order, offset: p.offset, limit: p.limit, search: p.search, role_filter: 'agent' };
        }

        function queryParams(p) {
            return { sort: p.sort, order: p.order, offset: p.offset, limit: p.limit, search: p.search };
        }

        window.actionEvents = {};
    </script>

    {{-- Include image toggle --}}
    <script>
        $("#include_image").change(function() {
            if (this.checked) {
                $('#show_image').show('fast');
                $('#file').attr('required', 'required');
            } else {
                $('#file').val('');
                $('#file').removeAttr('required');
                $('#show_image').hide('fast');
            }
        });
    </script>

    {{-- Image file validation --}}
    <script type="text/javascript">
        var _URL = window.URL || window.webkitURL;
        $("#file").change(function(e) {
            var file, img;
            if ((file = this.files[0])) {
                img = new Image();
                img.onerror = function() {
                    $('#file').val('');
                    $('#img_error_msg').html('{{ trans('message.invalid_image_type') }}');
                    $('#img_error_msg').show().delay(3000).fadeOut();
                };
                img.src = _URL.createObjectURL(file);
            }
        });
    </script>

    {{-- Single notification delete --}}
    <script type="text/javascript">
        $(document).on('click', '.delete-data', function() {
            $this = $(this);
            swal.fire({
                title: '{{ __("Are you sure") }}',
                text: '{{ __("You wants to delete this notification ?") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ __("Yes Delete") }}',
                cancelButtonText: '{{ __("cancel") }}',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    var id    = $this.data("id");
                    var image = $this.data("image");
                    $.ajax({
                        url: "{{ url('notification-delete') }}",
                        type: "GET",
                        data: { id: id, image: image },
                        success: function(result) {
                            if (result.error) {
                                Toastify({ text: result.message, duration: 6000, close: !0, backgroundColor: '#dc3545' }).showToast();
                            } else {
                                $('#table_list1').bootstrapTable('refresh');
                                Toastify({ text: result.message, duration: 6000, close: !0, backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)" }).showToast();
                            }
                        },
                        error: function(result) {
                            Toastify({ text: result.responseJSON.message, duration: 6000, close: !0, backgroundColor: '#dc3545' }).showToast();
                        }
                    });
                }
            });
        });
    </script>

    {{-- Multiple notification delete --}}
    <script type="text/javascript">
        $('#delete_multiple').on('click', function(e) {
            var table = $('#table_list1');
            var delete_button = $('#delete_multiple');
            var selected = table.bootstrapTable('getSelections');
            var ids = "";
            $.each(selected, function(i, e) { ids += e.id + ","; });
            ids = ids.slice(0, -1);

            if (ids == "") {
                swal.fire({ title: '{{ __("Please Select Some Data") }}', icon: 'error' });
            } else {
                swal.fire({
                    title: '{{ __("Are you sure") }}',
                    text: '{{ __("You wants to delete this notification ?") }}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '{{ __("Yes Delete") }}',
                    cancelButtonText: '{{ __("cancel") }}',
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        let ButtonHtml = delete_button.html();
                        $.ajax({
                            url: "{{ url('notification-multiple-delete') }}",
                            type: "POST",
                            data: { "_token": "{{ csrf_token() }}", id: ids },
                            beforeSend: function() { delete_button.html('<em class="fa fa-spinner fa-pulse"></em>'); },
                            success: function(result) {
                                if (result.error) {
                                    Toastify({ text: result.message, duration: 6000, close: !0, backgroundColor: '#dc3545' }).showToast();
                                } else {
                                    delete_button.html('<em class="fa fa-trash"></em>');
                                    $('#table_list1').bootstrapTable('refresh');
                                    Toastify({ text: result.message, duration: 6000, close: !0, backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)" }).showToast();
                                }
                            },
                            error: function(result) {
                                delete_button.html(ButtonHtml);
                                Toastify({ text: result.responseJSON.message, duration: 6000, close: !0, backgroundColor: '#dc3545' }).showToast();
                            }
                        });
                    }
                });
            }
        });
    </script>
@endsection
