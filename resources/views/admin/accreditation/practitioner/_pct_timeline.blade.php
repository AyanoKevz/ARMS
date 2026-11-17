{{--
    Process Cycle Time card — the HCD portal's timeline (show_application_info),
    for the practitioner's steps. The active step's stopwatch ticks live:
    evaluation.js drives #livePctCounter from its data-seconds and the
    working-hours calendar (window.ARMS.holidays / serverTime).

    Expects $pctSummary (PctService::getSummary).
--}}
@if($pctSummary['has_entries'])
@php
    $pctPercent = $pctSummary['percent'];
    $pctOverdue = $pctSummary['is_overdue'];
    $pctTone    = $pctOverdue ? 'is-overdue' : ($pctPercent >= 80 ? 'is-warning' : 'is-ok');
    $pctDash    = round(($pctPercent / 100) * 251.2, 1);
    $remaining  = max(0, $pctSummary['total_target'] - $pctSummary['total_elapsed']);

    $pctNow       = \Carbon\Carbon::now();
    $pctHolidays  = \App\Services\PctService::getHolidays($pctNow->year, $pctNow->year);
    $pctIsHoliday = in_array($pctNow->format('Y-m-d'), $pctHolidays, true);
    $pctOffHours  = $pctNow->lessThan($pctNow->copy()->setTime(8, 0)) || $pctNow->greaterThanOrEqualTo($pctNow->copy()->setTime(17, 0));
@endphp
<div class="ai-card mb-4 pct-card">
    <div class="ai-card-header prac-card-toggle" data-bs-toggle="collapse" data-bs-target="#pctTimelineBody" aria-expanded="true">
        <span class="prac-card-icon"><i class="bi bi-clock-history"></i></span>
        <h5 class="mb-0">Process Cycle Time (PCT)</h5>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="badge prac-pct-total-badge {{ $pctOverdue ? 'bg-danger' : ($pctPercent >= 80 ? 'bg-warning text-dark' : 'bg-success') }}">
                {{ $pctSummary['total_elapsed'] }} / {{ $pctSummary['total_target'] }} Days
            </span>
            <i class="bi bi-chevron-down" id="pctChevron"></i>
        </div>
    </div>
    <div id="pctTimelineBody" class="collapse show">
        <div class="pct-summary-row">
            <div class="pct-ring-wrapper">
                <svg class="pct-ring" viewBox="0 0 90 90">
                    <circle class="pct-ring-bg" cx="45" cy="45" r="40" />
                    <circle class="pct-ring-fill prac-tone-stroke {{ $pctTone }}" cx="45" cy="45" r="40" stroke-dasharray="{{ $pctDash }} 251.2" />
                </svg>
                <div class="pct-ring-text">
                    <span class="pct-ring-value prac-tone {{ $pctTone }}">{{ $pctPercent }}%</span>
                </div>
            </div>
            <div class="pct-stats">
                <div class="pct-stat-item">
                    <div class="pct-stat-label">Elapsed</div>
                    <div class="pct-stat-value prac-tone {{ $pctTone }}">{{ $pctSummary['total_elapsed'] }} <small>days</small></div>
                </div>
                <div class="pct-stat-item">
                    <div class="pct-stat-label">Max Limit</div>
                    <div class="pct-stat-value prac-tone">{{ $pctSummary['total_target'] }} <small>days</small></div>
                </div>
                <div class="pct-stat-item">
                    <div class="pct-stat-label">Remaining</div>
                    <div class="pct-stat-value prac-tone {{ $remaining > 0 ? 'is-ok' : 'is-overdue' }}">{{ $remaining }} <small>days</small></div>
                </div>
            </div>
        </div>

        <div class="pct-timeline">
            @foreach($pctSummary['steps'] as $step)
            @php
                $stepStatus = $step['status'];
                $isUntimed  = $stepStatus === 'pending';

                $stepClass = match ($stepStatus) {
                    'completed' => 'pct-step-completed',
                    'active'    => 'pct-step-active',
                    'paused'    => 'pct-step-paused',
                    default     => 'pct-step-pending',
                };
                $stepIcon = match ($stepStatus) {
                    'completed' => 'bi-check-circle-fill',
                    'active'    => 'bi-play-circle-fill',
                    'paused'    => 'bi-pause-circle-fill',
                    default     => 'bi-circle',
                };
                $slaClass = $isUntimed ? '' : ($step['is_overdue'] ? 'pct-sla-overdue' : ($step['percent'] >= 80 ? 'pct-sla-warning' : 'pct-sla-ok'));

                // Why the clock is not running: the applicant has the
                // application (paused), or it is outside working hours.
                $pausedReason = '';
                if ($stepStatus === 'paused') {
                    $pausedReason = $step['number'] === 5 ? 'Waiting for scheduled interview' : 'Waiting for applicant';
                } elseif ($stepStatus === 'active') {
                    $pausedReason = $pctNow->isWeekend() ? 'Weekend' : ($pctIsHoliday ? 'Holiday' : ($pctOffHours ? 'Past Working Hours' : ''));
                }

                $s       = $step['elapsed_seconds'] ?? 0;
                $timeFmt = floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm ' . ($s % 60) . 's';
            @endphp
            <div class="pct-step {{ $stepClass }}">
                <div class="pct-step-connector"></div>
                <div class="pct-step-icon"><i class="bi {{ $stepIcon }}"></i></div>
                <div class="pct-step-content">
                    <div class="pct-step-header">
                        <span class="pct-step-number">Step {{ $step['number'] }}</span>
                        <span class="pct-step-name">
                            {{ $step['name'] }}<span @if($stepStatus === 'active') id="pct-paused-reason-live" @endif
                                  class="text-warning fw-semibold ms-1 pct-paused-reason-text {{ $pausedReason ? '' : 'is-hidden' }}">@if($pausedReason) — {{ $pausedReason }}@endif</span>
                            @if(! empty($step['continues']))
                                <span class="prac-step-continues">(continues Step {{ $step['continues'] }})</span>
                            @endif
                        </span>
                        @if(! $isUntimed)
                        <span class="pct-step-badge {{ $slaClass }}"
                              @if($stepStatus === 'active') id="livePctCounter" data-seconds="{{ $s }}" data-target="{{ $step['target_days'] }}" data-target-label="{{ $step['target_label'] }}" @endif>
                            <i class="bi bi-stopwatch me-1"></i>({{ $timeFmt }}) &nbsp;&nbsp;{{ round($s / 32400, 1) }} days / {{ $step['target_label'] }}
                        </span>
                        @else
                        <span class="pct-step-badge pct-sla-pending">{{ $step['target_label'] }} target</span>
                        @endif
                    </div>
                    @if(! $isUntimed)
                    <div class="pct-step-bar-wrapper">
                        <div class="pct-step-bar">
                            <progress class="prac-step-progress {{ $slaClass }}" max="100" value="{{ min(100, $step['percent']) }}"></progress>
                        </div>
                    </div>
                    @endif
                    @if($step['started_at'] || $step['completed_at'])
                    <div class="pct-step-meta">
                        @if($step['started_at'])
                            <span><i class="bi bi-arrow-right-circle me-1"></i>Started: {{ $step['started_at']->format('M d, Y h:i A') }}</span>
                        @endif
                        @if($step['completed_at'])
                            <span><i class="bi bi-check2 me-1"></i>Completed: {{ $step['completed_at']->format('M d, Y h:i A') }}</span>
                        @endif
                        @if($stepStatus === 'paused')
                            <span class="text-warning"><i class="bi bi-pause-fill me-1"></i>Paused</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <div class="pct-legend">
            <span class="pct-legend-item"><i class="bi bi-check-circle-fill prac-legend-completed"></i> Completed</span>
            <span class="pct-legend-item"><i class="bi bi-play-circle-fill prac-legend-active"></i> Active</span>
            <span class="pct-legend-item"><i class="bi bi-pause-circle-fill prac-legend-paused"></i> Paused</span>
            <span class="pct-legend-item"><i class="bi bi-circle prac-legend-pending"></i> Pending</span>
        </div>
    </div>
</div>
@endif
