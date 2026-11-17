@extends('layouts.admin')

@section('title', $typeLabel . ' — New Applications (Under Review)')

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
        <h2>Under Review</h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">
        <div class="table-responsive">
            <table id="practitioner_review_table" class="table table-striped table-bordered jambo_table table-compact dynamic-table">
                <thead>
                    <tr class="headings">
                        <th class="column-title">Tracking No</th>
                        <th class="column-title">Applicant</th>
                        <th class="column-title">In-Charge</th>
                        <th class="column-title">Last Updated</th>
                        <th class="column-title text-center">Status</th>
                        <th class="column-title text-center no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($applications as $app)
                        @php $status = $app->latestStatus?->status?->name; @endphp
                        <tr>
                            <td><strong>{{ $app->tracking_number }}</strong></td>
                            <td>{{ $app->user?->name ?? '—' }}</td>
                            <td>{{ $app->assignedEvaluator?->name ?? '—' }}</td>
                            <td data-order="{{ $app->updated_at->format('Y-m-d H:i') }}">{{ $app->updated_at->format('M d, Y') }}</td>
                            <td class="text-center">
                                <span class="prac-status prac-status-{{ \Illuminate\Support\Str::slug($status ?? 'unknown') }}">{{ $status }}</span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.accreditation.practitioner.show', $app->id) }}" class="btn btn-primary btn-xs m-0">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('admin.accreditation._datatable_scripts')
@endpush
