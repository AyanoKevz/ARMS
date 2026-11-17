@extends('layouts.admin')

@section('title', $typeLabel . ' — New Applications (Pending)')

@push('styles')
@include('admin.accreditation._datatable_styles')
@endpush

@section('content')
<div class="page-title">
    <div class="title_left">
        <h3>{{ $typeLabel }} — New Applications</h3>
    </div>
</div>
<div class="clearfix"></div>

@include('admin.accreditation.practitioner._flash')

<div class="x_panel">
    <div class="x_title">
        <h2>New Applications Awaiting Evaluation</h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">
        <div class="table-responsive">
            <table id="practitioner_pending_table" class="table table-striped table-bordered jambo_table table-compact dynamic-table">
                <thead>
                    <tr class="headings">
                        <th class="column-title">Tracking No</th>
                        <th class="column-title">Applicant</th>
                        <th class="column-title">Email</th>
                        <th class="column-title">Date Submitted</th>
                        <th class="column-title text-center no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($applications as $app)
                        <tr>
                            <td><strong>{{ $app->tracking_number }}</strong></td>
                            <td>{{ $app->user?->name ?? '—' }}</td>
                            <td>{{ $app->user?->email ?? '—' }}</td>
                            <td data-order="{{ $app->created_at->format('Y-m-d H:i') }}">{{ $app->created_at->format('M d, Y') }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-primary btn-xs m-0" data-bs-toggle="modal" data-bs-target="#startEvalModal{{ $app->id }}">
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

@foreach($applications as $app)
    @include('admin.accreditation.practitioner._start_modal', ['application' => $app])
@endforeach
@endsection

@push('scripts')
@include('admin.accreditation._datatable_scripts')
<script src="{{ \App\Support\AssetVersion::url('js/accreditation-practitioner.js') }}"></script>
@endpush
