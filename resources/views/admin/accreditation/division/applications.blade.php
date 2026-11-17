{{--
    A read-only list of applications across OSH Practitioner and OSH
    Consultant: interviews, for recommendation, archived.

    Expects $title, $heading, $hint, $applications, $showInterview.
--}}
@extends('layouts.admin')

@section('title', $title)

@push('styles')
@include('admin.accreditation._datatable_styles')
@endpush

@section('content')
<div class="page-title">
    <div class="title_left">
        <h3>{{ $heading }}</h3>
    </div>
</div>
<div class="clearfix"></div>

@include('admin.accreditation.practitioner._flash')

<div class="x_panel">
    <div class="x_title">
        <h2>{{ $heading }}</h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">
        <p class="prac-admin-hint">{{ $hint }}</p>

        <div class="table-responsive">
            <table id="division_applications_table" class="table table-striped table-bordered jambo_table table-compact dynamic-table">
                <thead>
                    <tr class="headings">
                        <th class="column-title">Tracking No</th>
                        <th class="column-title">Applicant</th>
                        <th class="column-title">Type</th>
                        <th class="column-title">{{ $showInterview ? 'Interview' : 'Last Updated' }}</th>
                        <th class="column-title text-center">Status</th>
                        <th class="column-title">In-Charge</th>
                        <th class="column-title text-center no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($applications as $app)
                        @php
                            $status = $app->latestStatus?->status?->name;
                            $date   = $showInterview && $app->interview?->interview_date ? $app->interview->interview_date : $app->updated_at;
                        @endphp
                        <tr>
                            <td><strong>{{ $app->tracking_number }}</strong></td>
                            <td>{{ $app->user?->name ?? '—' }}</td>
                            <td>{{ \App\Support\AdminAccess::divisionTypeLabel($app->accreditation_type_id) }}</td>
                            <td data-order="{{ $date->format('Y-m-d') }}">
                                {{ $date->format('M d, Y') }}
                                @if($showInterview && $app->interview?->interview_time)
                                    <span class="prac-cell-sub">{{ \Carbon\Carbon::parse($app->interview->interview_time)->format('g:i A') }}</span>
                                @endif
                                @if(($showArchive ?? false) && $app->archived_at)
                                    <span class="prac-cell-sub">{{ app(\App\Services\PractitionerArchiveService::class)->shortReason($app->archive_reason) }}</span>
                                    @if($app->reapply_after)
                                        <span class="prac-cell-sub">May reapply / deleted on {{ $app->reapply_after->format('M d, Y') }}</span>
                                    @endif
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="prac-status prac-status-{{ \Illuminate\Support\Str::slug($status ?? 'unknown') }}">{{ $status }}</span>
                            </td>
                            <td>{{ $app->assignedEvaluator?->name ?? '—' }}</td>
                            <td class="text-center">
                                @include('admin.accreditation.division._view_action', ['app' => $app])
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
