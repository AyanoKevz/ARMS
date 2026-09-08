@extends('layouts.applicant')

@section('title', 'FATPRO Instructors')

@push('styles')
{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
@endpush

@section('content')
<div class="">
    <div class="page-title d-flex justify-content-between align-items-center">
        <div class="title_left">
            <h3>FATPRO Instructor List</h3>
        </div>
        <a href="{{ route('applicant.dashboard') }}" class="btn btn-secondary btn-sm mt-3">
            Back
        </a>
    </div>

    <div class="clearfix"></div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-octagon-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            The instructor could not be added. Please reopen <strong>Add Instructor</strong> and correct the following:
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel">
                <div class="x_title d-flex justify-content-between align-items-center flex-wrap">
                    <h2 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i> My Instructors under {{ auth()->user()->name }}</h2>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-primary btn-sm m-0"
                                data-bs-toggle="modal" data-bs-target="#addInstructorModal">
                            <i class="fas fa-user-plus me-1"></i> Add Instructor
                        </button>
                        <ul class="nav navbar-right panel_toolbox m-0">
                            <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                        </ul>
                    </div>
                </div>

                <div class="x_content">
                    <div class="table-responsive">
                        <table id="instructors_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">First Name</th>
                                    <th class="column-title">Middle Name</th>
                                    <th class="column-title">Last Name</th>
                                    <th class="column-title text-center">Service Agreement</th>
                                    <th class="column-title text-center">Credentials Status</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($instructors as $instructor)
                                <tr class="even pointer">
                                    <td>{{ $instructor->first_name }}</td>
                                    <td>{{ $instructor->middle_name }}</td>
                                    <td>{{ $instructor->last_name }}</td>
                                    <td class="text-center">
                                        @php
                                            $saClass = match($instructor->status) {
                                                'approved' => 'bg-success',
                                                'returned' => 'bg-warning text-dark',
                                                'rejected' => 'bg-danger',
                                                default    => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $saClass }}">{{ ucfirst($instructor->status) }}</span>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $hasExpired = $instructor->credentials->contains(fn($c) => $c->validity_date && \Carbon\Carbon::parse($c->validity_date)->isPast());
                                            $hasPending = $instructor->update_request_status === 'pending_review';
                                            $hasRequested = $instructor->update_request_status === 'admin_requested';
                                        @endphp
                                        @if($hasExpired)
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Has Expired Credential</span>
                                        @elseif($hasPending)
                                            <span class="badge bg-info text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending Review</span>
                                        @elseif($hasRequested)
                                            <span class="badge bg-warning text-dark"><i class="bi bi-bell-fill me-1"></i>Update Requested</span>
                                        @else
                                            <span class="badge bg-success">Up to Date</span>
                                        @endif
                                    </td>
                                    <td class="last text-center" style="white-space:nowrap;">
                                        <a href="{{ route('applicant.instructors.show', $instructor->id) }}"
                                           class="btn btn-info btn-xs m-0"
                                           title="View Instructor Details">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        @if(in_array($instructor->id, $deletableIds))
                                            <button type="button" class="btn btn-danger btn-xs m-0"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteInstructorModal-{{ $instructor->id }}"
                                                    title="Remove Instructor">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        @else
                                            <span class="d-inline-block" tabindex="0"
                                                  title="You must keep at least one instructor on your roster.">
                                                <button type="button" class="btn btn-danger btn-xs m-0" disabled
                                                        style="pointer-events:none;">
                                                    <i class="fas fa-trash me-1"></i> Delete
                                                </button>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Kept outside the table: DataTables detaches paged-out rows. --}}
                    @foreach($instructors as $instructor)
                        @if(in_array($instructor->id, $deletableIds))
                            @include('applicant.partials._delete_instructor_modal', ['instructor' => $instructor])
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@include('applicant.partials._add_instructor_modal', ['credentialTypes' => $credentialTypes])
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
