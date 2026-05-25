@extends('layouts.admin')

@section('title', 'Recommendation / Payment Verification')

@push('styles')
{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
<style>
    .payment-badge {
        font-size: 0.72rem;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 600;
        display: inline-block;
        margin: 2px;
    }
    .badge-pending { background-color: #fef9c3; color: #854d0e; }
    .badge-approved { background-color: #dcfce7; color: #166534; }
    .badge-rejected { background-color: #fee2e2; color: #991b1b; }
    .badge-missing { background-color: #f3f4f6; color: #4b5563; }
</style>
@endpush

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3>Recommendation / Payment Verification</h3>
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
                    <h2>Applications Awaiting Recommendation Letter & Payment</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <div class="table-responsive">
                        <table id="awaiting_payment_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking No</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Type</th>
                                    <th class="column-title">Passed Date</th>
                                    <th class="column-title text-center">Payment Requirements</th>
                                    <th class="column-title text-center">Recommendation Letter</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($applications as $app)
                                    @php
                                        $org  = $app->user->organizationProfile;
                                        $isOrg = $app->user->profile_type === 'Organization';
                                        $payment = $app->payment;
                                        
                                        $proofStatus = $payment ? $payment->proof_of_payment_status : 'missing';
                                        $sigStatus = $payment ? $payment->e_signature_status : 'missing';
                                        $photoStatus = $payment ? $payment->id_photo_status : 'missing';
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
                                            <span class="badge bg-secondary">{{ ucfirst($app->application_type) }}</span>
                                        </td>
                                        <td data-order="{{ $app->updated_at->format('Y-m-d') }}">{{ $app->updated_at->format('M d, Y') }}</td>
                                        <td class="text-center">
                                            <span class="payment-badge badge-{{ $proofStatus }}">
                                                Proof: {{ ucfirst($proofStatus) }}
                                            </span>
                                            <span class="payment-badge badge-{{ $sigStatus }}">
                                                E-Sig: {{ ucfirst($sigStatus) }}
                                            </span>
                                            <span class="payment-badge badge-{{ $photoStatus }}">
                                                ID Photo: {{ ucfirst($photoStatus) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if($payment && $payment->signed_recommendation_letter)
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Uploaded</span>
                                            @else
                                                <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Missing</span>
                                            @endif
                                        </td>
                                        <td class="last text-center">
                                            <a href="{{ route('admin.hcd.applications.show', $app->id) }}" class="btn btn-primary btn-xs m-0">
                                                <i class="fas fa-search me-1"></i> View & Evaluate
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
        $('#awaiting_payment_table').DataTable({
            responsive: true,
            order: [[3, 'desc']], // Order by Passed Date descending
            columnDefs: [
                { orderable: false, targets: [4, 5, 6] } // Disable ordering on status and actions
            ]
        });
    });
</script>
@endpush
