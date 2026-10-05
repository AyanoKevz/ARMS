{{--
    Opens the Notice of Cancellation dialog for one training.

    Shown wherever a training is still live and still inside the amendment
    window — alongside Report of Changes on an acknowledged NTC, and on its own
    for one still awaiting evaluation. canCancel() decides both, so the button
    cannot appear where the controller would refuse it.

    Expects: $ntc (NtcReport)
--}}
@if($ntc->canCancel())
<div class="mt-2">
    <button type="button"
            class="btn btn-xs btn-outline-danger fw-bold px-2 py-1 mt-1 btn-cancel-ntc ntc-btn-xs"
            data-url="{{ route('applicant.ntc.cancel', $ntc->id) }}"
            data-ref="{{ $ntc->reference_number }}"
            data-type="{{ $ntc->trainingType->name ?? 'N/A' }}"
            data-period="{{ $ntc->trainingPeriodLabel() }}">
        Notice of Cancellation
    </button>
</div>
@endif
