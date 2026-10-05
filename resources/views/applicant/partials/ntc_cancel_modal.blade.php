{{--
    Notice of Cancellation — confirmation dialog.

    One dialog retargeted per training, like the instructor and training-day
    dialogs on this page: the button that opens it tells ntc.js which training
    it belongs to and the form's action is rewritten to match.

    Deliberately heavier than a yes/no. Cancelling is terminal — there is no
    acknowledgement step to catch a mistake, and no way back short of filing a
    fresh NTC — so the dialog states what is about to be lost and asks for a
    reason before the button becomes usable.
--}}
<div class="modal fade" id="ntcCancelModal" tabindex="-1" aria-labelledby="ntcCancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content ntc-modal-surface">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold ntc-heading-navy" id="ntcCancelModalLabel">
                    Notice of Cancellation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" id="ntcCancelForm" novalidate>
                @csrf
                <div class="modal-body">
                    <p class="mb-3 ntc-text-88">
                        You are about to cancel
                        <strong class="ntc-heading-navy" id="ntcCancelRef">this training</strong>.
                    </p>

                    <div class="ntc-cancel-summary mb-3">
                        <div class="ntc-cancel-summary-row">
                            <span>Training</span>
                            <strong id="ntcCancelType">—</strong>
                        </div>
                        <div class="ntc-cancel-summary-row">
                            <span>Training days</span>
                            <strong id="ntcCancelPeriod">—</strong>
                        </div>
                    </div>

                    <div class="alert alert-important py-2 mb-3 ntc-reminder">
                        This cannot be undone. The training will be withdrawn, no Post Training
                        Report will be owed for it, and the DOLE-OSHC Training Evaluators will be
                        notified. To hold the training after all you would have to file a new
                        Notice to Conduct.
                    </div>

                    <label for="ntcCancelReason" class="form-label fw-semibold ntc-text-88">
                        Reason for cancelling <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control ntc-cancel-reason"
                              id="ntcCancelReason"
                              name="cancellation_reason"
                              rows="5"
                              maxlength="1000"
                              minlength="10"
                              required
                              placeholder="e.g. The client postponed the schedule and no replacement batch could be arranged."></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        {{-- Nobody follows this up, so it is the evaluators' only account. --}}
                        <small class="text-muted ntc-text-70">
                            The evaluators are told this and nothing more.
                        </small>
                        <small class="text-muted ntc-text-70">
                            <span id="ntcCancelCount">0</span>/1000
                        </small>
                    </div>
                    <div class="invalid-feedback d-block d-none" id="ntcCancelError">
                        Please give a reason of at least 10 characters.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        Keep this training
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm" id="ntcCancelSubmit">
                        Cancel this training
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
