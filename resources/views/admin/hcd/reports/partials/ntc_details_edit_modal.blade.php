{{--
    Training Evaluator's correction of an acknowledged NTC's details.

    The FATPro's own route for this is the Report of Changes, which closes three
    working days before the training. An evaluator who spots a wrong venue or a
    mistyped date after that has nothing else to reach for, short of declining
    the whole submission.

    The day and instructor pickers are the same partials the FATPro's form uses,
    driven by the same ntc-training-picker.js — only the id prefix and the date
    floor differ, since the ten-working-day lead time governs how far ahead a
    training may be FILED, not how late a mistake may be corrected.

    Expects: $ntcReport, $trainingTypes, $trainingModes, $instructorRoster
--}}

<div class="modal fade" id="ntcDetailsEditModal" tabindex="-1"
     aria-labelledby="ntcDetailsEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered portal-scroll-modal">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="ntcDetailsEditModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>
                    Correct Submission Details
                    <span class="text-secondary fw-normal ms-1">{{ $ntcReport->reference_number }}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST"
                  action="{{ route('admin.hcd.reports.ntc.details.update', $ntcReport->id) }}"
                  id="ntcDetailsEditForm"
                  novalidate>
                @csrf

                <div class="modal-body">
                    <div class="alert alert-warning py-2 ntc-text-sm">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        You are changing a submission on the FATPro's behalf. The training
                        has not started, so its deadlines will move with any change of date.
                    </div>

                    {{-- Training Type --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="admin_ntc_training_type_id">
                            Type of Training <span class="text-danger">*</span>
                        </label>
                        <select id="admin_ntc_training_type_id"
                                name="ntc_training_type_id"
                                class="form-control"
                                required>
                            @foreach($trainingTypes as $type)
                                <option value="{{ $type->id }}"
                                        data-duration="{{ $type->durationDays() }}"
                                        {{ $ntcReport->ntc_training_type_id == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }} &mdash; {{ $type->durationLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mode of Training --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="admin_ntc_training_mode_id">
                            Mode of Training <span class="text-danger">*</span>
                        </label>
                        <select id="admin_ntc_training_mode_id"
                                name="ntc_training_mode_id"
                                class="form-control"
                                required>
                            @foreach($trainingModes as $mode)
                                <option value="{{ $mode->id }}"
                                        data-code="{{ $mode->code }}"
                                        {{ $ntcReport->ntc_training_mode_id == $mode->id ? 'selected' : '' }}>
                                    {{ $mode->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Venue / Zoom Link --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="admin_ntc_venue">
                            Venue / Zoom Link <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               id="admin_ntc_venue"
                               name="venue"
                               class="form-control"
                               value="{{ $ntcReport->venue }}"
                               required>
                    </div>

                    @include('applicant.partials.ntc_training_days', [
                        'prefix'       => 'admin_',
                        // Only "not in the past" applies here — see the note above.
                        'minDate'      => \Carbon\Carbon::today()->format('Y-m-d'),
                        'oldExtraDays' => $ntcReport->trainingDatesByDay(),
                    ])

                    @include('applicant.partials.ntc_instructor_picker', [
                        'prefix'                => 'admin_',
                        'instructorRoster'      => $instructorRoster,
                        'selectedInstructorIds' => $ntcReport->instructors->pluck('id')->all(),
                    ])

                    {{-- Remarks --}}
                    <div class="form-group mb-0">
                        <label class="fw-semibold" for="admin_ntc_remarks">
                            Remarks <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <textarea id="admin_ntc_remarks"
                                  name="remarks"
                                  class="form-control"
                                  rows="2"
                                  placeholder="Note what was corrected and why…">{{ $ntcReport->remarks }}</textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="ntcDetailsEditSubmit">
                        <i class="bi bi-check2 me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
