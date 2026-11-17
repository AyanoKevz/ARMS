{{--
    "Start Evaluation" confirmation, as FATPro's was before evaluators were
    assigned. Expects $application.
--}}
<div class="modal fade" id="startEvalModal{{ $application->id }}" tabindex="-1" aria-labelledby="startEvalLabel{{ $application->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="startEvalLabel{{ $application->id }}">Confirm Evaluation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <p>Are you sure you want to start the evaluation for the following application?</p>
                <ul class="prac-modal-facts">
                    <li><strong>Tracking Number:</strong> {{ $application->tracking_number }}</li>
                    <li><strong>Applicant:</strong> {{ $application->user?->name }}</li>
                </ul>
                <p class="prac-admin-hint mb-0">
                    The applicant is emailed that their application was received and is now being evaluated.
                    You become the in-charge, and the 5-day evaluation clock starts.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.accreditation.practitioner.start', $application->id) }}" method="POST" class="d-inline" data-prac-busy-form>
                    @csrf
                    <button type="submit" class="btn btn-primary" data-prac-busy-label="Starting…">Start Evaluation</button>
                </form>
            </div>
        </div>
    </div>
</div>
