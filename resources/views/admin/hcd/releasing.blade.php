@extends('layouts.admin')

@section('title', 'Certificate Releasing')

@push('styles')
{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
<style>
    .cert-badge {
        font-size: 0.72rem;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 600;
        display: inline-block;
        margin: 2px;
    }
    .badge-released { background-color: #dcfce7; color: #166534; }
    .badge-awaiting { background-color: #fef9c3; color: #854d0e; }
</style>
@endpush

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3>Certificate Releasing</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="x_panel">
                <div class="x_title">
                    <h2>Approved Applications & Releasing Status</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <div class="table-responsive">
                        <table id="releasing_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%" data-date-index="4">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking No</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Accreditation No</th>
                                    <th class="column-title">Approved Date</th>
                                    <th class="column-title text-center">Certificate Status</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($applications as $app)
                                    @php
                                        $org  = $app->user->organizationProfile;
                                        $isOrg = $app->user->profile_type === 'Organization';
                                        $accreditation = $app->accreditation;
                                        $hasScannedCert = $accreditation && $accreditation->scanned_certificate;
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
                                            <code class="fw-bold text-dark" style="font-size: .88rem;">
                                                {{ $accreditation->accreditation_number ?? 'Pending Number' }}
                                            </code>
                                        </td>
                                        <td data-order="{{ $app->updated_at->format('Y-m-d') }}">
                                            {{ $app->updated_at->format('M d, Y') }}
                                        </td>
                                        <td class="text-center">
                                            @if($hasScannedCert)
                                                <span class="cert-badge badge-released">
                                                    <i class="fas fa-check-circle me-1"></i> Released
                                                </span>
                                            @else
                                                <span class="cert-badge badge-awaiting">
                                                    <i class="fas fa-hourglass-half me-1"></i> Awaiting Scanned Cert
                                                </span>
                                            @endif
                                        </td>
                                        <td class="last text-center" style="white-space:nowrap;">
                                            <a href="{{ route('admin.hcd.applications.show', $app->id) }}"
                                               class="btn btn-info btn-xs m-0"
                                               title="View & Upload Certificate">
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
<script>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#releasing_table')) {
            $('#releasing_table').DataTable().destroy();
        }
        $('#releasing_table').DataTable({
            responsive: true,
            order: [[3, 'desc']], // Sort by Approved Date
            columnDefs: [
                { targets: 'no-sort', orderable: false }
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search releasing applications..."
            }
        });
    });
</script>
@endpush
