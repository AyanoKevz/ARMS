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
    <div class="page-title">
        <div class="title_left">
            <h3>FATPRO Instructor List</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2><i class="fas fa-chalkboard-teacher me-2"></i> My Instructors</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <div class="table-responsive">
                        <table id="instructors_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Instructor Name</th>
                                    <th class="column-title text-center">No. of Credentials</th>
                                    <th class="column-title text-center">SA Status</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($instructors as $instructor)
                                <tr class="even pointer">
                                    <td>
                                        <strong>
                                            {{ $instructor->last_name }}, {{ $instructor->first_name }}
                                            @if($instructor->middle_name)
                                                {{ strtoupper(substr($instructor->middle_name, 0, 1)) }}.
                                            @endif
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $instructor->credentials->count() }}</span>
                                    </td>
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
                                    <td class="last text-center" style="white-space:nowrap;">
                                        <a href="{{ route('applicant.instructors.show', $instructor->id) }}"
                                           class="btn btn-info btn-xs m-0"
                                           title="View Instructor Details">
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
