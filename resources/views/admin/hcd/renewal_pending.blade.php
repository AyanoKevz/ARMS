@extends('layouts.admin')

@section('title', 'Renewal / Reinstatement — Pending')

@push('styles')
{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
@endpush

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3>Renewal / Reinstatement — Pending</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="x_panel">
                <div class="x_title">
                    <h2>Renewal / Reinstatement Applications Awaiting Evaluation</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <div class="table-responsive">
                        <table id="renewal_pending_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking No</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Type</th>
                                    <th class="column-title">Accreditation No.</th>
                                    <th class="column-title">Accreditation Status</th>
                                    <th class="column-title">
                                        <span>Date Submitted</span>
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
                                    <th class="column-title text-center">Status</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($applications as $app)
                                    @php
                                        $org  = $app->user->organizationProfile;
                                        $isOrg = $app->user->profile_type === 'Organization';
                                        $accreditation = $app->user->accreditations->first();
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong>{{ $app->tracking_number }}</strong></td>
                                        <td>
                                            @if($isOrg)
                                                {{ $org->name ?? 'N/A' }}
                                            @else
                                                {{ ($app->user->individualProfile->first_name ?? '') . ' ' . ($app->user->individualProfile->last_name ?? '') }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($app->application_type === 'renewal')
                                                <span class="badge bg-info text-white">Renewal</span>
                                            @else
                                                <span class="badge bg-secondary text-white">Reinstatement</span>
                                            @endif
                                        </td>
                                        <td>{{ $accreditation->accreditation_number ?? '—' }}</td>
                                        <td>
                                            @if($accreditation)
                                                @if($accreditation->status === 'active')
                                                    <span class="badge bg-success">Active</span>
                                                @elseif($accreditation->status === 'expired')
                                                    <span class="badge bg-warning text-dark">Expired</span>
                                                @elseif($accreditation->status === 'revoked')
                                                    <span class="badge bg-danger">Revoked</span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td data-order="{{ $app->created_at->format('Y-m-d') }}">{{ $app->created_at->format('M d, Y') }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark">{{ $app->latestStatus?->status?->name ?? 'Pending' }}</span>
                                        </td>
                                        <td class="last text-center">
                                            <button type="button" class="btn btn-primary btn-xs m-0" data-bs-toggle="modal" data-bs-target="#evalModal{{ $app->id }}">
                                                <i class="fas fa-play me-1"></i> Start Evaluation
                                            </button>
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

@foreach($applications as $app)
@php
    $org  = $app->user->organizationProfile;
    $isOrg = $app->user->profile_type === 'Organization';
    $fatproName = $isOrg ? ($org->name ?? 'N/A') : trim(($app->user->individualProfile->first_name ?? '') . ' ' . ($app->user->individualProfile->last_name ?? ''));
@endphp
<!-- Modal -->
<div class="modal fade" id="evalModal{{ $app->id }}" tabindex="-1" aria-labelledby="evalModalLabel{{ $app->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="evalModalLabel{{ $app->id }}">Confirm Evaluation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <p>Are you sure you want to start the evaluation for the following {{ ucfirst($app->application_type) }} application?</p>
                <ul class="mb-0">
                    <li><strong>Tracking Number:</strong> {{ $app->tracking_number }}</li>
                    <li><strong>FATPro Name:</strong> {{ $fatproName }}</li>
                    <li><strong>Application Type:</strong> {{ ucfirst($app->application_type) }}</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.hcd.applications.update_evaluation', $app->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">Start Evaluation</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection

@push('scripts')
{{-- jQuery (required by DataTables) --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

{{-- DataTables Core --}}
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

{{-- DataTables Extensions --}}
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

{{-- Reusable Table Component JS --}}
<script src="{{ asset('js/table-component.js') }}"></script>
@endpush
