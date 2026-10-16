// Bootstrap Table Localization for ebroker-admin
// This file MUST be loaded after bootstrap-table.min.js
// window.trans and window.currentLocale must be set before this file loads

(function () {
    if (typeof $.fn.bootstrapTable === 'undefined') {
        console.warn('Bootstrap Table not loaded before localization script');
        return;
    }

    function getTrans(key) {
        return window.trans && window.trans[key] ? window.trans[key] : null;
    }

    // Override defaults IMMEDIATELY (not in document.ready)
    // This ensures tables initialized later pick up translations
    var defaults = $.fn.bootstrapTable.defaults;

    defaults.formatLoadingMessage = function () {
        return getTrans('Loading, please wait') || 'Loading, please wait';
    };
    defaults.formatRecordsPerPage = function (pageNumber) {
        return pageNumber + ' ' + (getTrans('rows per page') || 'rows per page');
    };
    defaults.formatShowingRows = function (pageFrom, pageTo, totalRows, totalNotFiltered) {
        var showing = getTrans('Showing') || 'Showing';
        var to = getTrans('to') || 'to';
        var of = getTrans('of') || 'of';
        var rows = getTrans('rows') || 'rows';

        if (totalNotFiltered !== undefined && totalNotFiltered > 0 && totalNotFiltered > totalRows) {
            var filtered = getTrans('filtered from') || 'filtered from';
            var total = getTrans('total rows') || 'total rows';
            return showing + ' ' + pageFrom + ' ' + to + ' ' + pageTo + ' ' + of + ' ' + totalRows + ' ' + rows + ' (' + filtered + ' ' + totalNotFiltered + ' ' + total + ')';
        }
        return showing + ' ' + pageFrom + ' ' + to + ' ' + pageTo + ' ' + of + ' ' + totalRows + ' ' + rows;
    };
    defaults.formatSRPaginationPreText = function () {
        return getTrans('previous page') || 'previous page';
    };
    defaults.formatSRPaginationPageText = function (page) {
        return (getTrans('to page') || 'to page') + ' ' + page;
    };
    defaults.formatSRPaginationNextText = function () {
        return getTrans('next page') || 'next page';
    };
    defaults.formatDetailPagination = function (totalRows) {
        return (getTrans('Showing') || 'Showing') + ' ' + totalRows + ' ' + (getTrans('rows') || 'rows');
    };
    defaults.formatSearch = function () {
        return getTrans('Search') || 'Search';
    };
    defaults.formatClearSearch = function () {
        return getTrans('Clear Search') || 'Clear Search';
    };
    defaults.formatNoMatches = function () {
        return getTrans('No matching records found') || 'No matching records found';
    };
    defaults.formatPaginationSwitch = function () {
        return getTrans('Hide/Show pagination') || 'Hide/Show pagination';
    };
    defaults.formatPaginationSwitchDown = function () {
        return getTrans('Show pagination') || 'Show pagination';
    };
    defaults.formatPaginationSwitchUp = function () {
        return getTrans('Hide pagination') || 'Hide pagination';
    };
    defaults.formatRefresh = function () {
        return getTrans('Refresh') || 'Refresh';
    };
    defaults.formatToggle = function () {
        return getTrans('Toggle') || 'Toggle';
    };
    defaults.formatToggleOn = function () {
        return getTrans('Show card view') || 'Show card view';
    };
    defaults.formatToggleOff = function () {
        return getTrans('Hide card view') || 'Hide card view';
    };
    defaults.formatColumns = function () {
        return getTrans('Columns') || 'Columns';
    };
    defaults.formatColumnsToggleAll = function () {
        return getTrans('Toggle all') || 'Toggle all';
    };
    defaults.formatFullscreen = function () {
        return getTrans('Fullscreen') || 'Fullscreen';
    };
    defaults.formatAllRows = function () {
        return getTrans('All') || 'All';
    };

    // Now initialize tables that were deferred (data-toggle was removed to prevent early auto-init)
    $('[data-toggle-original="table"]').each(function () {
        var $table = $(this);
        $table.attr('data-toggle', 'table').removeAttr('data-toggle-original');

        if (!$table.data('bootstrap.table')) {
            $table.bootstrapTable($table.data());
        }
    });

})();
