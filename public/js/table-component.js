/* Table Component Reusable JS */
window.initDynamicTable = function(selector, options = {}) {
    const defaultOptions = {
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], ['10', '25', '50', '100']],
        dom: '<"dt-toolbar"<"dt-toolbar-left"B l><"dt-toolbar-right"f>>rtip',
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv me-1"></i> CSV',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel me-1"></i> Excel',
                className: 'btn btn-primary btn-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'landscape',
                pageSize: 'A4'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-1"></i> Print',
                className: 'btn btn-info btn-sm'
            }
        ],
        order: [[3, 'desc']],
        language: {
            search: '<i class="fas fa-search"></i>',
            searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'No entries to show',
            infoFiltered: '(filtered from _MAX_ total entries)',
            paginate: {
                first:    '<i class="fas fa-angle-double-left"></i>',
                last:     '<i class="fas fa-angle-double-right"></i>',
                next:     '<i class="fas fa-angle-right"></i>',
                previous: '<i class="fas fa-angle-left"></i>'
            }
        },
        columnDefs: []
    };

    // --- Automatic Column Configuration ---
    // Detect columns with 'no-sort' class in the header
    const nonSortableColumns = [];
    $(selector).find('thead th').each(function(index) {
        if ($(this).hasClass('no-sort')) {
            nonSortableColumns.push(index);
        }
    });

    if (nonSortableColumns.length > 0) {
        const autoColumnDefs = nonSortableColumns.map(index => ({
            orderable: false,
            targets: index
        }));
        options.columnDefs = (options.columnDefs || []).concat(autoColumnDefs);
    }

    const finalOptions = $.extend(true, {}, defaultOptions, options);
    const table = $(selector).DataTable(finalOptions);

    // --- Date Range Custom Search Filter ---
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        var min = $('#date_from').val();
        var max = $('#date_to').val();
        
        // Skip if no date range inputs found
        if (!min && !max) return true;

        // Try to find the date-order attribute in the cell
        const rowNode = table.row(dataIndex).node();
        if (!rowNode) return true;
        
        const dateCell = rowNode.cells[3]; // Assuming 4th column is date
        if (!dateCell) return true;

        const dateRaw = dateCell.getAttribute('data-order');

        if (!dateRaw) return true;
        if (!min && dateRaw <= max) return true;
        if (min <= dateRaw && !max) return true;
        if (min <= dateRaw && dateRaw <= max) return true;
        return false;
    });

    // --- Date Filter Toggle UI Logic ---
    $('#dateFilterToggle').on('click', function (e) {
        e.stopPropagation();
        $('#dateFilterPanel').toggleClass('show');
        $(this).toggleClass('active');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.date-filter-dropdown').length) {
            $('#dateFilterPanel').removeClass('show');
            $('#dateFilterToggle').removeClass('active');
        }
    });

    $('#dateFilterPanel').on('click', function (e) {
        e.stopPropagation();
    });

    $('#apply_date_filter').on('click', function () {
        table.draw();
        $('#dateFilterPanel').removeClass('show');
        $('#dateFilterToggle').removeClass('active');

        if ($('#date_from').val() || $('#date_to').val()) {
            $('#dateFilterToggle').css('color', '#f0ad4e');
        } else {
            $('#dateFilterToggle').css('color', '');
        }
    });

    $('#clear_date_filter').on('click', function () {
        $('#date_from').val('');
        $('#date_to').val('');
        $('#dateFilterToggle').css('color', '');
        table.draw();
    });

    return table;
};

// --- Auto-Initialize on Document Ready ---
$(document).ready(function() {
    $('.dynamic-table').each(function() {
        const id = '#' + $(this).attr('id');
        window.initDynamicTable(id);
    });
});

