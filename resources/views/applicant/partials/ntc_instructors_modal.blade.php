{{--
    The instructors declared on one Notice to Conduct.

    One dialog for the whole table, retargeted by ntc.js from the row that
    opened it — the same arrangement the Report of Changes dialog uses, so a
    long list of submissions does not render a modal apiece.
--}}

<div class="modal fade" id="ntcInstructorsModal" tabindex="-1"
     aria-labelledby="ntcInstructorsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered portal-scroll-modal">
        <div class="modal-content ntc-modal-surface">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold ntc-heading-navy" id="ntcInstructorsModalLabel">
                    <i class="fas fa-users ntc-icon-gold me-2"></i>
                    Instructors Conducting
                    <span class="text-secondary fw-normal ms-1" id="ntcInstructorsModalRef"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <ul class="ntc-readout" id="ntcInstructorsModalList"></ul>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
