{{--
    Cancelled trainings.

    A record, not a queue. Nothing here is awaiting an evaluator: a Notice of
    Cancellation takes effect when it is filed, so these rows exist so that a
    training the team was expecting can still be looked up afterwards — with
    the reason the FATPro gave, and every document it was filed with intact.

    Same DataTables treatment as the other report lists, so the search, the
    page size and the CSV/Excel/PDF exports all behave the way the evaluators
    already expect them to.
--}}
@extends('layouts.admin')

@section('title', 'Cancelled Training — Reports')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
@endpush

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3><i class="fas fa-ban me-2" style="color: var(--portal-gold);"></i> Cancelled Training</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel" style="border-top: 3px solid #334155;">
                <div class="x_title">
                    <h2><i class="fas fa-list-alt me-2" style="color: #334155;"></i> Trainings Called Off by FATPros</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <p class="text-muted" style="font-size:0.85rem;">
                        These trainings were withdrawn by the FATPro before they were held.
                        Nothing is required of you &mdash; a cancellation is not reviewed or
                        acknowledged. The records are kept in full and remain viewable.
                    </p>

                    <div class="table-responsive">
                        <table id="cancelled_admin_table"
                               class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table"
                               style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Reference #</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Accreditation No.</th>
                                    <th class="column-title">Type</th>
                                    <th class="column-title">Mode</th>
                                    <th class="column-title text-center">Training Period</th>
                                    <th class="column-title">Reason</th>
                                    <th class="column-title text-center">Cancelled On</th>
                                    <th class="column-title no-link last text-center no-sort">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($ntcReports as $ntc)
                                    @php
                                        $user       = $ntc->accreditation->user ?? null;
                                        $accNo      = $ntc->accreditation->accreditation_number ?? '—';
                                        $fatproName = $user?->name ?? '—';
                                        $reason     = $ntc->cancellation_reason ?? '';
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong style="color: #0b3d91;">{{ $ntc->reference_number }}</strong></td>
                                        <td>{{ $fatproName }}</td>
                                        <td>{{ $accNo }}</td>
                                        <td>
                                            <span class="badge"
                                                  style="background: #eef5ff; color: #0b3d91; font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; font-weight: 600;">
                                                {{ $ntc->trainingType->code ?? 'N/A' }}
                                            </span>
                                            <div style="font-size:0.75rem; color:#666; margin-top:2px;">
                                                {{ $ntc->trainingType->name ?? '' }}
                                            </div>
                                        </td>
                                        <td>{{ $ntc->trainingMode->name ?? 'N/A' }}</td>
                                        <td class="text-center" style="white-space: nowrap;">
                                            <div>{{ $ntc->training_start_date ? $ntc->training_start_date->format('M d, Y') : 'N/A' }}</div>
                                            <div style="font-size:0.75rem; color:#999;">to</div>
                                            <div>{{ $ntc->training_end_date ? $ntc->training_end_date->format('M d, Y') : 'N/A' }}</div>
                                        </td>
                                        {{-- Clipped in the cell but complete in the tooltip, and in
                                             full on the detail page. A long reason would otherwise
                                             set the height of every row in the table. --}}
                                        <td style="font-size:0.82rem; max-width: 260px;" title="{{ $reason }}">
                                            {{ $reason !== '' ? Str::limit($reason, 90) : '—' }}
                                        </td>
                                        <td class="text-center" style="font-size:0.82rem; white-space: nowrap;">
                                            <div>{{ $ntc->cancelled_at ? $ntc->cancelled_at->format('M d, Y') : '—' }}</div>
                                            @if($ntc->cancelledByUser)
                                                <div style="font-size:0.72rem; color:#999;">
                                                    by {{ $ntc->cancelledByUser->name }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="last text-center" style="white-space:nowrap;">
                                            <a href="{{ route('admin.hcd.reports.ntc.show', $ntc->id) }}"
                                               class="btn btn-primary btn-xs">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="{{ asset('js/table-component.js') }}"></script>
@endpush
