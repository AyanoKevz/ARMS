{{--
    Graduate Lookup Visitors — who asked to use the public Graduate Lookup,
    whether they verified their email, and what they searched for.

    Paged on the server (see GraduateVisitorController): a public page keeps
    adding to it, so the browser is only ever sent one page of rows.
--}}
@extends('layouts.admin')

@section('title', 'Graduate Lookup Visitors')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}?v={{ filemtime(public_path('css/table-component.css')) }}">
@endpush

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3><i class="fas fa-user-graduate me-2" style="color: var(--portal-gold);"></i> Graduate Lookup Visitors</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel" style="border-top: 3px solid #2A3F54;">
                <div class="x_title">
                    <h2><i class="fas fa-eye me-2" style="color: #2A3F54;"></i> Visitor Log</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <p class="text-muted" style="font-size:0.85rem;">
                        Everyone who requested access to the public <strong>Graduate Lookup</strong> &mdash;
                        who they are, why they asked, when they verified their email, and what they searched.
                    </p>

                    {{-- Moved into the table's toolbar, right after "Show N entries". --}}
                    <div class="dataTables_length" id="visitorStatusFilterWrap">
                        <label>
                            Status
                            <select id="visitorStatusFilter" class="form-select form-select-sm d-inline-block dt-filter-select">
                                <option value="">All</option>
                                <option value="verified">Verified</option>
                                <option value="unverified">Not verified</option>
                            </select>
                        </label>
                    </div>

                    <div class="table-responsive">
                        <table id="graduate_visitors_table"
                               class="table table-striped table-bordered jambo_table bulk_action table-compact"
                               style="width:100%"
                               data-url="{{ route('admin.hcd.graduate_visitors.data') }}">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">
                                        <span>Requested</span>
                                        <span class="date-filter-dropdown">
                                            <i class="fas fa-filter date-filter-toggle" id="dateFilterToggle" title="Filter by date"></i>
                                            <div class="date-filter-panel" id="dateFilterPanel">
                                                <p class="mb-2" style="font-size:0.8rem;font-weight:700;color:#2A3F54;">Filter by Date</p>
                                                <label>From</label>
                                                <input type="date" id="date_from">
                                                <label>To</label>
                                                <input type="date" id="date_to">
                                                <div class="d-flex gap-2">
                                                    <button type="button" id="apply_date_filter" class="btn btn-primary btn-sm flex-fill">Apply</button>
                                                    <button type="button" id="clear_date_filter" class="btn btn-outline-secondary btn-sm flex-fill">Clear</button>
                                                </div>
                                            </div>
                                        </span>
                                    </th>
                                    <th class="column-title">Visitor</th>
                                    <th class="column-title">Organization</th>
                                    <th class="column-title">Purpose</th>
                                    <th class="column-title">Email Verified</th>
                                    <th class="column-title">Searches</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="{{ asset('js/table-component.js') }}?v={{ filemtime(public_path('js/table-component.js')) }}"></script>
<script>
$(function () {
    var $table = $('#graduate_visitors_table');

    var table = window.initServerTable('#graduate_visitors_table', {
        ajaxUrl: $table.data('url'),
        order: [[0, 'desc']],
        extraData: function () {
            return { status_filter: $('#visitorStatusFilter').val() || '' };
        },
        columns: [
            { orderable: true },
            { orderable: true },
            { orderable: true },
            { orderable: false },
            { orderable: true },
            { orderable: false }
        ]
    });

    // Same row as "Show N entries", after it.
    $('#graduate_visitors_table_wrapper .dt-toolbar-left').append($('#visitorStatusFilterWrap'));

    $('#visitorStatusFilter').on('change', function () {
        $(this).toggleClass('is-active', $(this).val() !== '');
        table.draw();
    });
});
</script>
@endpush
