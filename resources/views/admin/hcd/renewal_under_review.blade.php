@extends('layouts.admin')

@section('title', 'Renewal / Reinstatement — Under Evaluation')

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
            <h3>Renewal / Reinstatement — Under Evaluation</h3>
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
                    <h2>Renewal / Reinstatement Applications Under Evaluation</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <div class="table-responsive">
                        <table id="renewal_under_review_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking No</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Type</th>
                                    <th class="column-title">Accreditation No.</th>
                                    <th class="column-title">Accreditation Status</th>
                                    <th class="column-title">Address</th>
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
                                            @if($isOrg && $org)
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
                                        <td>{{ $isOrg && $org ? ($org->address ?? '—') : ($app->user->individualProfile->address ?? '—') }}</td>
                                        <td class="text-center">
                                            @php
                                                $statusName = $app->latestStatus?->status?->name ?? 'Under Evaluation';
                                                $badgeClass = $statusName === 'For Update' ? 'bg-warning text-dark' : 'bg-success text-white';
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ $statusName }}</span>
                                        </td>
                                        <td class="last text-center">
                                            <a href="{{ route('admin.hcd.applications.show', $app->id) }}" class="btn btn-info btn-xs m-0">
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
