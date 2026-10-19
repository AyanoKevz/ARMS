{{--
    Audit Logs — who did what, when, and to which record.

    Each role sees only its own role's entries. The table is paged on the
    server (see AuditLogController): the trail grows with every action, so the
    browser is only ever sent one page of it. There is deliberately no export
    or print: the trail is viewed here only.
--}}
@extends('layouts.admin')

@section('title', 'Audit Logs')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}?v={{ filemtime(public_path('css/table-component.css')) }}">
@endpush

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3><i class="fas fa-history me-2" style="color: var(--portal-gold);"></i> Audit Logs</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel" style="border-top: 3px solid #2A3F54;">
                <div class="x_title">
                    <h2><i class="fas fa-user-shield me-2" style="color: #2A3F54;"></i> {{ $role }} Activity</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <p class="text-muted" style="font-size:0.85rem;">
                        Every action taken by a <strong>{{ $role }}</strong> &mdash; who did it, when,
                        and on which record. You see your own role's activity only.
                    </p>

                    {{-- Moved into the table's toolbar, right after "Show N entries",
                         once the table is drawn. Styled as that control is, so the
                         two read as one row of options. --}}
                    <div class="dataTables_length" id="auditActionFilterWrap">
                        <label>
                            Action
                            <select id="auditActionFilter" class="form-select form-select-sm d-inline-block dt-filter-select">
                                <option value="">All actions</option>
                                @foreach($actions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="table-responsive">
                        <table id="audit_logs_table"
                               class="table table-striped table-bordered jambo_table bulk_action table-compact"
                               style="width:100%"
                               data-url="{{ route('admin.hcd.audit_logs.data') }}">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">
                                        <span>Date &amp; Time</span>
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
                                    <th class="column-title">Admin</th>
                                    <th class="column-title">Action</th>
                                    <th class="column-title">Record</th>
                                    <th class="column-title">Details</th>
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
    var $table = $('#audit_logs_table');

    var table = window.initServerTable('#audit_logs_table', {
        ajaxUrl: $table.data('url'),
        // No export or print: the audit trail is viewed here only.
        print: false,
        order: [[0, 'desc']],
        extraData: function () {
            return { action_filter: $('#auditActionFilter').val() || '' };
        },
        columns: [
            { orderable: true },
            { orderable: true },
            { orderable: true },
            { orderable: true },
            { orderable: false }
        ]
    });

    // Same row as "Show N entries", after it.
    $('#audit_logs_table_wrapper .dt-toolbar-left').append($('#auditActionFilterWrap'));

    $('#auditActionFilter').on('change', function () {
        $(this).toggleClass('is-active', $(this).val() !== '');
        table.draw();
    });
});
</script>
@endpush
