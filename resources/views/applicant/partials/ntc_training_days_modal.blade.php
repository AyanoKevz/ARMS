{{--
    The training period of one Notice to Conduct, day by day.

    The column holds only a count and a button: a training may run over any
    number of dates, and a day of the course may itself take more than one, so
    the cell cannot grow with them. The dates are read here instead, grouped by
    the day they belong to, with the derived start and end above them.

    One dialog for the whole table, retargeted by ntc.js from the row that
    opened it — the same arrangement the Instructors dialog uses.
--}}

<div class="modal fade" id="ntcTrainingDaysModal" tabindex="-1"
     aria-labelledby="ntcTrainingDaysModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered portal-scroll-modal">
        <div class="modal-content ntc-modal-surface">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold ntc-heading-navy" id="ntcTrainingDaysModalLabel">
                    <i class="fas fa-calendar-alt ntc-icon-gold me-2"></i>
                    Training Period
                    <span class="text-secondary fw-normal ms-1" id="ntcTrainingDaysModalRef"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="ntc-period-summary">
                    <div class="ntc-period-summary-item">
                        <span class="ntc-period-summary-label">Start</span>
                        <span class="ntc-period-summary-value" id="ntcTrainingDaysModalStart">—</span>
                    </div>
                    <div class="ntc-period-summary-item">
                        <span class="ntc-period-summary-label">End</span>
                        <span class="ntc-period-summary-value" id="ntcTrainingDaysModalEnd">—</span>
                    </div>
                </div>

                <ul class="ntc-readout" id="ntcTrainingDaysModalList"></ul>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
