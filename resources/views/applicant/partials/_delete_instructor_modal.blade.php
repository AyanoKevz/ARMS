{{--
    Delete confirmation for one instructor.

    Rendered outside the table: DataTables detaches paged-out rows, which would
    take a modal nested in a <td> with them.

    Expects: $instructor
--}}
<div class="modal fade" id="deleteInstructorModal-{{ $instructor->id }}" tabindex="-1"
     aria-labelledby="deleteInstructorModalLabel-{{ $instructor->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteInstructorModalLabel-{{ $instructor->id }}">
                    <i class="fas fa-triangle-exclamation text-danger me-2"></i> Remove Instructor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="mb-2">
                    Remove <strong>{{ trim($instructor->first_name . ' ' . $instructor->middle_name . ' ' . $instructor->last_name) }}</strong>
                    from your instructor roster?
                </p>
                <div class="alert alert-danger mb-0" style="font-size:.85rem;">
                    <i class="bi bi-exclamation-octagon-fill me-1"></i>
                    This permanently deletes the instructor's records, credentials, service agreement and CV.
                    The uploaded files are removed from the server and cannot be recovered.
                    Your assigned evaluator will be notified.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('applicant.instructors.destroy', $instructor->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash me-1"></i> Remove Instructor
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
