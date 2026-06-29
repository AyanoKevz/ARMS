@extends('layouts.admin')

@section('title', 'HCD Dashboard')

@section('content')
@php
    $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
    $isVerifier = strtolower($isAdminRole) === 'verifier';
@endphp
<div class="">

    {{-- ── Page Header ── --}}
    <div class="page-title">
        <div class="title_left">
            <h3>HCD Dashboard</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    {{-- ── Stat Cards ── --}}
    <div class="row mb-4">
        {{-- Card 1: Total Active FATPro --}}
        <div class="col-xl-4 col-md-6 mb-3">
            <a href="{{ route('admin.hcd.directory.fatpros') }}" class="stat-card stat-card-blue">
                <div class="stat-card-icon blue"><i class="bi bi-patch-check-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalActiveFATPro }}</div>
                    <div class="stat-card-label">Total Active FATPro</div>
                    <div class="stat-card-sub">Currently accredited</div>
                </div>
            </a>
        </div>

        {{-- Card 2: New Applications --}}
        <div class="col-xl-4 col-md-6 mb-3">
            @if($isVerifier)
            <div class="stat-card stat-card-green" style="cursor: default;">
                <div class="stat-card-icon green"><i class="bi bi-file-earmark-plus-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $newPending + $newUnderReview }}</div>
                    <div class="stat-card-label">New Applications</div>
                    <div class="stat-card-sub">{{ $newPending }} Pending · {{ $newUnderReview }} Under Review</div>
                </div>
            </div>
            @else
            <a href="{{ route('admin.hcd.applications.pending') }}" class="stat-card stat-card-green">
                <div class="stat-card-icon green"><i class="bi bi-file-earmark-plus-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $newPending + $newUnderReview }}</div>
                    <div class="stat-card-label">New Applications</div>
                    <div class="stat-card-sub">{{ $newPending }} Pending · {{ $newUnderReview }} Under Review</div>
                </div>
            </a>
            @endif
        </div>

        {{-- Card 3: Renewal Applications --}}
        <div class="col-xl-4 col-md-6 mb-3">
            @if($isVerifier)
            <div class="stat-card stat-card-amber" style="cursor: default;">
                <div class="stat-card-icon amber"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $renewalPending + $renewalUnderReview }}</div>
                    <div class="stat-card-label">Renewal Applications</div>
                    <div class="stat-card-sub">{{ $renewalPending }} Pending · {{ $renewalUnderReview }} Under Review</div>
                </div>
            </div>
            @else
            <a href="{{ route('admin.hcd.renewal.pending') }}" class="stat-card stat-card-amber">
                <div class="stat-card-icon amber"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $renewalPending + $renewalUnderReview }}</div>
                    <div class="stat-card-label">Renewal Applications</div>
                    <div class="stat-card-sub">{{ $renewalPending }} Pending · {{ $renewalUnderReview }} Under Review</div>
                </div>
            </a>
            @endif
        </div>

        {{-- Card 4: Scheduled Interviews --}}
        <div class="col-xl-4 col-md-6 mb-3">
            @if($isVerifier)
            <div class="stat-card stat-card-violet" style="cursor: default;">
                <div class="stat-card-icon violet"><i class="bi bi-calendar2-check-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $scheduledInterviews }}</div>
                    <div class="stat-card-label">Scheduled Interviews</div>
                    <div class="stat-card-sub">Pending interview completion</div>
                </div>
            </div>
            @else
            <a href="{{ route('admin.hcd.interviews.scheduled') }}" class="stat-card stat-card-violet">
                <div class="stat-card-icon violet"><i class="bi bi-calendar2-check-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $scheduledInterviews }}</div>
                    <div class="stat-card-label">Scheduled Interviews</div>
                    <div class="stat-card-sub">Pending interview completion</div>
                </div>
            </a>
            @endif
        </div>

        {{-- Card 5: Rejected Applications --}}
        <div class="col-xl-4 col-md-6 mb-3">
            @if($isVerifier)
            <div class="stat-card stat-card-red" style="cursor: default;">
                <div class="stat-card-icon red"><i class="bi bi-x-circle-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalRejected }}</div>
                    <div class="stat-card-label">Rejected Applications</div>
                    <div class="stat-card-sub">Did not pass (Archived)</div>
                </div>
            </div>
            @else
            <a href="{{ route('admin.hcd.applications.archived') }}" class="stat-card stat-card-red">
                <div class="stat-card-icon red"><i class="bi bi-x-circle-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalRejected }}</div>
                    <div class="stat-card-label">Rejected Applications</div>
                    <div class="stat-card-sub">Did not pass (Archived)</div>
                </div>
            </a>
            @endif
        </div>

        {{-- Card 6: Total Revoked FATPro --}}
        <div class="col-xl-4 col-md-6 mb-3">
            <a href="{{ route('admin.hcd.directory.fatpros.inactive') }}" class="stat-card stat-card-red">
                <div class="stat-card-icon red"><i class="bi bi-slash-circle-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalRevokedFATPro }}</div>
                    <div class="stat-card-label">Total Revoked FATPro</div>
                    <div class="stat-card-sub">Accreditation revoked</div>
                </div>
            </a>
        </div>
    </div>

    {{-- ── Charts ── --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="dash-chart-card">
                <div class="dash-chart-title">
                    <i class="bi bi-bar-chart-fill me-1"></i> Monthly Applications — {{ $selectedYear }}
                </div>
                <canvas id="barChart" height="80"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Monthly Table ── --}}
    <div class="row">
        <div class="col-12">
            <div class="x_panel">
                <div class="x_title d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h2><i class="bi bi-table me-1"></i> Monthly Applications and Accreditations</h2>
                    <form method="GET" action="{{ route('admin.hcd.dashboard') }}" class="d-flex align-items-center gap-2 mb-0">
                        <label class="form-label mb-0 small fw-semibold">Year:</label>
                        <select name="year" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                            @foreach($availableYears as $yr)
                            <option value="{{ $yr }}" {{ $yr == $selectedYear ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover monthly-table mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Month</th>
                                    <th class="text-center">New</th>
                                    <th class="text-center">Renewal</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Accredited</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totNew = 0; $totRen = 0; $totAcc = 0; @endphp
                                @foreach($monthlyRows as $row)
                                @php
                                $totNew += $row['new'];
                                $totRen += $row['renewal'];
                                $totAcc += $row['accredited'];
                                $rowTotal = $row['new'] + $row['renewal'];
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $row['month'] }}</td>
                                    <td class="text-center {{ $row['new'] == 0 ? 'zero' : '' }}">{{ $row['new'] ?: '—' }}</td>
                                    <td class="text-center {{ $row['renewal'] == 0 ? 'zero' : '' }}">{{ $row['renewal'] ?: '—' }}</td>
                                    <td class="text-center fw-semibold {{ $rowTotal == 0 ? 'zero' : '' }}">{{ $rowTotal ?: '—' }}</td>
                                    <td class="text-center">
                                        @if($row['accredited'])
                                        <span class="badge bg-success">{{ $row['accredited'] }}</span>
                                        @else
                                        <span class="zero">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-secondary fw-bold">
                                <tr>
                                    <td>Total</td>
                                    <td class="text-center">{{ $totNew }}</td>
                                    <td class="text-center">{{ $totRen }}</td>
                                    <td class="text-center">{{ $totNew + $totRen }}</td>
                                    <td class="text-center"><span class="badge bg-success">{{ $totAcc }}</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        @php
            $months = $monthlyRows->pluck('month');
            $newData = $monthlyRows->pluck('new');
            $renewalData = $monthlyRows->pluck('renewal');
            $accreditedData = $monthlyRows->pluck('accredited');
        @endphp

        const months = @json($months);
        const newData = @json($newData);
        const renewalData = @json($renewalData);
        const accreditedData = @json($accreditedData);

        // Bar Chart
        new Chart(document.getElementById('barChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                        label: 'New',
                        data: newData,
                        backgroundColor: 'rgba(26,111,189,.75)',
                        borderRadius: 4
                    },
                    {
                        label: 'Renewal',
                        data: renewalData,
                        backgroundColor: 'rgba(230,126,34,.75)',
                        borderRadius: 4
                    },
                    {
                        label: 'Accredited',
                        data: accreditedData,
                        backgroundColor: 'rgba(39,174,96,.75)',
                        borderRadius: 4
                    },
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0,0,0,.05)'
                        }
                    }
                }
            }
        });



    });
</script>
@endpush