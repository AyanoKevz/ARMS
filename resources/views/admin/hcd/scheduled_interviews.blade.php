@extends('layouts.admin')

@section('title', 'List of Scheduled Interviews')

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
            <h3>List of Scheduled Interviews</h3>
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
                    <h2>
                        <i class="fas fa-calendar-check text-success me-2"></i>
                        Applicants with Set Interview Schedules
                    </h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <div class="table-responsive">
                        <table id="scheduled_interviews_table"
                               class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table"
                               style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking No</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Organization Email</th>
                                    <th class="column-title text-center">Interview Date</th>
                                    <th class="column-title text-center">Interview Time</th>
                                    <th class="column-title text-center">Mode</th>
                                    <th class="column-title">Venue</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($applications as $app)
                                    @php
                                        $org      = $app->user->organizationProfile;
                                        $isOrg    = $app->user->profile_type === 'Organization';
                                        $ind      = $app->user->individualProfile;
                                        $schedule = $app->interview;
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong>{{ $app->tracking_number }}</strong></td>
                                        <td>
                                            @if($isOrg && $org)
                                                {{ $org->name ?? 'N/A' }}
                                            @else
                                                {{ trim(($ind->first_name ?? '') . ' ' . ($ind->last_name ?? '')) ?: 'N/A' }}
                                            @endif
                                        </td>
                                        <td>{{ $isOrg && $org ? ($org->email ?? '—') : ($app->user->email ?? '—') }}</td>
                                        <td class="text-center">
                                            {{ $schedule?->interview_date?->format('M d, Y') ?? '—' }}
                                        </td>
                                        <td class="text-center">
                                            @if($schedule?->interview_time)
                                                {{ \Carbon\Carbon::parse($schedule->interview_time)->format('h:i A') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($schedule?->mode)
                                                <span class="badge {{ $schedule->mode === 'online' ? 'bg-info' : 'bg-secondary' }} text-white">
                                                    {{ strtoupper($schedule->mode) }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $schedule?->venue ?? '—' }}</td>
                                        <td class="last text-center">
                                            <a href="{{ route('admin.hcd.applications.show', $app->id) }}"
                                               class="btn btn-info btn-xs m-0">
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
