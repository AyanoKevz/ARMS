{{--
    Accreditations across OSH Practitioner and OSH Consultant — active, or
    revoked / expired.

    Expects $title, $heading, $accreditations; $status is null on the active
    list and ''|'revoked'|'expired' on the revoked / expired list (filter tabs).
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

<div class="x_panel">
    <div class="x_title">
        <h2>OSH Practitioner &amp; OSH Consultant</h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">
        @if(! is_null($status))
            <div class="prac-filter-tabs">
                @foreach(['' => 'All', 'revoked' => 'Revoked', 'expired' => 'Expired'] as $value => $label)
                    <a href="{{ route('admin.accreditation.division.accreditations.inactive', $value ? ['status' => $value] : []) }}"
                       class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table id="division_accreditations_table" class="table table-striped table-bordered jambo_table table-compact dynamic-table">
                <thead>
                    <tr class="headings">
                        <th class="column-title">Accreditation No</th>
                        <th class="column-title">Name</th>
                        <th class="column-title">Type</th>
                        <th class="column-title">Date Accredited</th>
                        <th class="column-title">Valid Until</th>
                        <th class="column-title text-center">Status</th>
                        <th class="column-title text-center no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accreditations as $acc)
                        <tr>
                            <td><strong>{{ $acc->accreditation_number ?? '—' }}</strong></td>
                            <td>{{ $acc->user?->name ?? '—' }}</td>
                            <td>{{ \App\Support\AdminAccess::divisionTypeLabel($acc->accreditation_type_id) }}</td>
                            <td data-order="{{ $acc->date_of_accreditation?->format('Y-m-d') }}">{{ $acc->date_of_accreditation?->format('M d, Y') ?? '—' }}</td>
                            <td data-order="{{ $acc->validity_date?->format('Y-m-d') }}">{{ $acc->validity_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-center"><span class="prac-acc-status is-{{ $acc->status }}">{{ ucfirst($acc->status) }}</span></td>
                            <td class="text-center">
                                @if($acc->application && $acc->application->isPractitioner() && $acc->application->application_type === 'new')
                                    <a href="{{ route('admin.accreditation.practitioner.show', $acc->application_id) }}" class="btn btn-primary btn-xs m-0">Open</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
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
