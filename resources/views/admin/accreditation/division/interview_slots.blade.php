{{--
    Manage Interview Slots — the Accreditation division opens interview
    times for OSH Practitioner and OSH Consultant applicants, who choose one
    from the tracking page. See InterviewSlotController.

    Expects $slots, $awaiting, $openCount, $bookedCount, $presets, $defaultVenue.
--}}
@extends('layouts.admin')

@section('title', 'Manage Interview Slots')

@push('styles')
@include('admin.accreditation._datatable_styles')
@endpush

@section('content')
@php
    $oldDates   = collect(old('dates', []))->filter(fn ($row) => is_array($row) && ! empty($row['date']))->values();
    $oldPresets = old('presets', array_keys($presets));
    $oldCustom  = collect(old('custom', []))->filter(fn ($row) => ($row['start'] ?? null) || ($row['end'] ?? null))->values();
    $oldMode    = old('mode', 'f2f');
@endphp
<div class="page-title">
    <div class="title_left">
        <h3>Manage Interview Slots</h3>
    </div>
</div>
<div class="clearfix"></div>

@include('admin.accreditation.practitioner._flash')
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="slot-stats">
    <div class="slot-stat"><span class="slot-stat-num">{{ $openCount }}</span><span class="slot-stat-label">Open slots</span></div>
    <div class="slot-stat is-booked"><span class="slot-stat-num">{{ $bookedCount }}</span><span class="slot-stat-label">Chosen by applicants</span></div>
    <div class="slot-stat is-waiting"><span class="slot-stat-num">{{ $awaiting->count() }}</span><span class="slot-stat-label">Applicants still choosing</span></div>
</div>

<div class="row g-3">
    {{-- ── Open new slots ── --}}
    <div class="col-12">
        <div class="x_panel">
            <div class="x_title">
                <h2><i class="fas fa-calendar-plus me-1"></i> Open Interview Slots</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">
                {{-- novalidate: the mode / venue box only stages what the next
                     added date takes, so it must not block the submit; the
                     script checks it on Add and the server checks every date. --}}
                <form method="POST" action="{{ route('admin.accreditation.division.interviews.slots.store') }}" data-slot-form novalidate>
                    @csrf

                    {{-- The three steps side by side on a wide screen, stacked below it. --}}
                    <div class="slot-steps">
                    <div class="slot-step">
                    {{-- Mode & venue — taken by each date when it is added --}}
                    <label class="slot-label">1. Mode &amp; venue <span class="text-danger">*</span></label>
                    <p class="prac-admin-hint">Applies to the dates you add next. Switch it between dates to mix face-to-face and online days.</p>
                    <div class="slot-modes">
                        <label class="slot-mode"><input type="radio" name="mode" value="f2f" data-slot-mode {{ $oldMode === 'f2f' ? 'checked' : '' }}> Face-to-Face</label>
                        <label class="slot-mode"><input type="radio" name="mode" value="online" data-slot-mode {{ $oldMode === 'online' ? 'checked' : '' }}> Online</label>
                    </div>
                    <input type="{{ $oldMode === 'online' ? 'url' : 'text' }}" name="venue" class="form-control" maxlength="500"
                           value="{{ old('venue', $oldMode === 'f2f' ? $defaultVenue : '') }}"
                           placeholder="{{ $oldMode === 'online' ? 'Meeting link (e.g. Zoom, Google Meet)' : 'Venue address' }}"
                           aria-label="Venue or meeting link"
                           data-slot-venue data-default-venue="{{ $defaultVenue }}">
                    </div>

                    <div class="slot-step">
                    {{-- Dates --}}
                    <label class="slot-label" for="slotDateInput">2. Interview dates <span class="text-danger">*</span></label>
                    <p class="prac-admin-hint">Pick a date and press Add. Each date keeps the mode and venue it was added with.</p>
                    <div class="slot-date-picker">
                        <input type="date" id="slotDateInput" class="form-control" min="{{ now()->toDateString() }}" data-slot-date-input>
                        <button type="button" class="btn btn-outline-primary m-0" data-slot-date-add><i class="fas fa-plus me-1"></i> Add</button>
                    </div>
                    <div class="slot-chips" data-slot-dates data-next-index="{{ $oldDates->count() }}">
                        @foreach($oldDates as $i => $row)
                            @php $rowOnline = ($row['mode'] ?? '') === 'online'; @endphp
                            <div class="slot-chip {{ $rowOnline ? 'is-online' : '' }}" data-slot-chip>
                                <div class="slot-chip-body">
                                    <span class="slot-chip-date">{{ rescue(fn () => \Carbon\Carbon::parse($row['date'])->format('D, M d, Y'), $row['date'], false) }}</span>
                                    <span class="slot-chip-venue">
                                        <i class="fas {{ $rowOnline ? 'fa-video' : 'fa-users' }}"></i>
                                        {{ $rowOnline ? 'Online' : 'Face-to-Face' }} · {{ $row['venue'] ?? '' }}
                                    </span>
                                </div>
                                <input type="hidden" name="dates[{{ $i }}][date]" value="{{ $row['date'] }}">
                                <input type="hidden" name="dates[{{ $i }}][mode]" value="{{ $row['mode'] ?? '' }}">
                                <input type="hidden" name="dates[{{ $i }}][venue]" value="{{ $row['venue'] ?? '' }}">
                                <button type="button" class="slot-chip-remove" data-slot-chip-remove aria-label="Remove date">&times;</button>
                            </div>
                        @endforeach
                        <span class="slot-chips-empty" data-slot-dates-empty>No dates added yet.</span>
                    </div>
                    </div>

                    <div class="slot-step">
                    {{-- Times --}}
                    <label class="slot-label">3. Time slots <span class="text-danger">*</span></label>
                    <p class="prac-admin-hint">Every chosen time is opened on every date.</p>
                    <div class="slot-presets">
                        @foreach($presets as $key => [$start, $end])
                            <label class="slot-preset">
                                <input type="checkbox" name="presets[]" value="{{ $key }}" data-slot-time {{ in_array($key, $oldPresets, true) ? 'checked' : '' }}>
                                <span>{{ \Carbon\Carbon::parse($start)->format('g:i A') }} – {{ \Carbon\Carbon::parse($end)->format('g:i A') }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="slot-custom" data-slot-custom data-next-index="{{ $oldCustom->count() }}">
                        @foreach($oldCustom as $i => $row)
                            <div class="slot-custom-row" data-slot-custom-row>
                                <input type="time" name="custom[{{ $i }}][start]" value="{{ $row['start'] ?? '' }}" class="form-control form-control-sm" aria-label="Custom start time" data-slot-time>
                                <span>to</span>
                                <input type="time" name="custom[{{ $i }}][end]" value="{{ $row['end'] ?? '' }}" class="form-control form-control-sm" aria-label="Custom end time" data-slot-time>
                                <button type="button" class="btn btn-link text-danger p-0" data-slot-custom-remove aria-label="Remove custom time"><i class="fas fa-times"></i></button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-slot-custom-add><i class="fas fa-plus me-1"></i> Add Custom Time</button>

                    <template data-slot-custom-template>
                        <div class="slot-custom-row" data-slot-custom-row>
                            <input type="time" name="custom[__i__][start]" class="form-control form-control-sm" aria-label="Custom start time" data-slot-time>
                            <span>to</span>
                            <input type="time" name="custom[__i__][end]" class="form-control form-control-sm" aria-label="Custom end time" data-slot-time>
                            <button type="button" class="btn btn-link text-danger p-0" data-slot-custom-remove aria-label="Remove custom time"><i class="fas fa-times"></i></button>
                        </div>
                    </template>
                    </div>
                    </div>

                    <div class="slot-submit">
                        <span class="slot-summary" data-slot-summary>Add a date and choose at least one time.</span>
                        <button type="submit" class="btn btn-primary m-0" data-slot-submit><i class="fas fa-check me-1"></i> Open Slots</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Upcoming slots ── --}}
    <div class="col-12">
        <div class="x_panel">
            <div class="x_title">
                <h2><i class="fas fa-calendar-alt me-1"></i> Upcoming Interview Slots</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">
                <div class="table-responsive">
                    <table id="interview_slots_table" class="table table-striped table-bordered jambo_table table-compact dynamic-table" data-order='[[0,"asc"]]'>
                        <thead>
                            <tr class="headings">
                                <th class="column-title">Date</th>
                                <th class="column-title">Time</th>
                                <th class="column-title">Mode / Venue</th>
                                <th class="column-title">Status</th>
                                <th class="column-title text-center no-sort">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slots as $slot)
                                @php $passed = ! $slot->isBooked() && ! $slot->startsAt()->isFuture(); @endphp
                                <tr>
                                    <td data-order="{{ $slot->slot_date->format('Y-m-d') }} {{ $slot->start_time }}">
                                        {{ $slot->slot_date->format('D, M d, Y') }}
                                    </td>
                                    <td>{{ $slot->timeLabel() }}</td>
                                    <td>
                                        {{ $slot->modeLabel() }}
                                        <span class="prac-cell-sub">{{ $slot->venue }}</span>
                                    </td>
                                    <td>
                                        @if($slot->isBooked())
                                            <span class="slot-status is-booked">Chosen</span>
                                            <span class="prac-cell-sub">
                                                {{ $slot->application?->user?->name }} · {{ $slot->application?->tracking_number }}
                                            </span>
                                        @elseif($passed)
                                            <span class="slot-status is-passed">Passed</span>
                                        @else
                                            <span class="slot-status is-open">Open</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap">
                                        @if($slot->isBooked())
                                            @if($slot->application?->isPractitioner() && $slot->application->application_type === 'new')
                                                <a href="{{ route('admin.accreditation.practitioner.show', $slot->application_id) }}" class="btn btn-outline-primary btn-xs m-0">Open</a>
                                            @endif
                                        @else
                                            <button type="button" class="btn btn-outline-danger btn-xs m-0"
                                                    data-bs-toggle="modal" data-bs-target="#slotRemoveModal"
                                                    data-slot-remove-action="{{ route('admin.accreditation.division.interviews.slots.destroy', $slot->id) }}"
                                                    data-slot-remove-date="{{ $slot->slot_date->format('l, F d, Y') }}"
                                                    data-slot-remove-time="{{ $slot->timeLabel() }}"
                                                    data-slot-remove-mode="{{ $slot->modeLabel() }}">Remove</button>
                                        @endif
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

{{-- ── Applicants still choosing — the same table as Scheduled Interviews ── --}}
@include('admin.accreditation.division._pending_interview_table', ['pending' => $awaiting, 'tableId' => 'slots_pending_interview_table'])

{{-- Remove an open slot. One modal for every row: the Remove button that
     opens it fills in the slot and the form's action. --}}
<div class="modal fade" id="slotRemoveModal" tabindex="-1" aria-labelledby="slotRemoveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content prac-modal">
            <div class="modal-header border-0 prac-modal-header is-danger">
                <h5 class="modal-title text-white fw-bold mb-0" id="slotRemoveModalLabel"><i class="fas fa-exclamation-triangle me-1"></i> Remove Interview Slot</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <p>Are you sure you want to remove this slot? Applicants will no longer be able to choose it.</p>
                <ul class="mb-0">
                    <li><strong>Date:</strong> <span data-slot-remove-field="date"></span></li>
                    <li><strong>Time:</strong> <span data-slot-remove-field="time"></span></li>
                    <li><strong>Mode:</strong> <span data-slot-remove-field="mode"></span></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" class="d-inline" data-prac-busy-form data-slot-remove-form>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold" data-prac-busy-label="Removing…">Remove Slot</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('admin.accreditation._datatable_scripts')
<script src="{{ \App\Support\AssetVersion::url('js/accreditation-practitioner.js') }}"></script>
<script src="{{ \App\Support\AssetVersion::url('js/accreditation-interview-slots.js') }}"></script>
@endpush
