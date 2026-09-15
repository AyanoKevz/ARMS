@extends('layouts.admin')

@section('title', 'Post Training Reports')

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
            <h3><i class="fas fa-flag-checkered me-2" style="color: var(--portal-gold);"></i> Post Training Reports</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel" style="border-top: 3px solid #0b3d91;">
                <div class="x_title">
                    <h2><i class="fas fa-list-alt me-2" style="color: #0b3d91;"></i> All Post Training Report Submissions</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <div class="table-responsive">
                        <table id="post_training_admin_table"
                               class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table"
                               style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Reference #</th>
                                    <th class="column-title">NTC Reference #</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Accreditation No.</th>
                                    <th class="column-title">Type</th>
                                    <th class="column-title text-center">Training Period</th>
                                    <th class="column-title text-center">Deadline</th>
                                    <th class="column-title text-center">Status</th>
                                    <th class="column-title text-center">Submitted</th>
                                    <th class="column-title no-link last text-center no-sort">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($reports as $report)
                                    @php
                                        $ntc        = $report->ntcReport;
                                        $user       = $report->accreditation->user ?? null;
                                        $accNo      = $report->accreditation->accreditation_number ?? '—';
                                        $fatproName = $user?->name ?? '—';
                                        $isLate     = $report->wasSubmittedLate();
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong style="color: #0b3d91;">{{ $report->reference_number }}</strong></td>
                                        <td>{{ $ntc->reference_number ?? '—' }}</td>
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
                                        <td class="text-center" style="white-space: nowrap;">
                                            <div>{{ $ntc?->training_start_date?->format('M d, Y') ?? 'N/A' }}</div>
                                            <div style="font-size:0.75rem; color:#999;">to</div>
                                            <div>{{ $ntc?->training_end_date?->format('M d, Y') ?? 'N/A' }}</div>
                                        </td>
                                        <td class="text-center" style="white-space: nowrap; font-size:0.82rem;">
                                            {{ $report->due_date ? $report->due_date->format('M d, Y') : '—' }}
                                        </td>
                                        <td class="text-center">
                                            @if($report->isAccepted())
                                                <span class="badge bg-success" style="font-size:0.75rem;">Accepted</span>
                                            @elseif($report->documents->contains(fn($d) => $d->status === 'rejected' && !$d->file_path))
                                                <span class="badge bg-danger" style="font-size:0.75rem;">Documents Declined</span>
                                            @elseif($report->documents->contains('status', 'returned'))
                                                <span class="badge bg-warning text-dark" style="font-size:0.75rem;">Under Review (Re-uploaded)</span>
                                            @else
                                                <span class="badge bg-warning text-dark" style="font-size:0.75rem;">Submitted</span>
                                            @endif
                                        </td>
                                        <td class="text-center" style="font-size:0.82rem; white-space: nowrap;">
                                            {{ $report->submitted_at ? $report->submitted_at->format('M d, Y') : '—' }}
                                            @if($isLate)
                                                <div><span class="badge bg-danger" style="font-size:0.68rem;">Late</span></div>
                                            @endif
                                        </td>
                                        <td class="last text-center" style="white-space:nowrap;">
                                            <a href="{{ route('admin.hcd.reports.post_training.show', $report->id) }}"
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
