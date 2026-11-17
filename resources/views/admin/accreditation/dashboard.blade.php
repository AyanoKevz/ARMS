@extends('layouts.admin')

@section('title', 'Accreditation Portal Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/accreditation-practitioner.css') }}">
@endpush

@section('content')
@php
    $p = $division['practitioner'];
    $c = $division['consultant'];
@endphp
<div class="">

    {{-- ── Page Header ── --}}
    <div class="page-title">
        <div class="title_left">
            <h3>Accreditation Portal Dashboard</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    {{-- ── OSH Practitioner & OSH Consultant ── --}}
    <p class="dash-section-title">OSH Practitioner &amp; OSH Consultant</p>
    <div class="row mb-4">
        {{-- New Applications --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <a href="{{ route('admin.accreditation.practitioner.new.pending') }}" class="stat-card stat-card-green">
                <div class="stat-card-icon green"><i class="bi bi-file-earmark-plus-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $p['new']['pending'] + $p['new']['under_review'] + $c['new']['pending'] + $c['new']['under_review'] }}</div>
                    <div class="stat-card-label">New Applications</div>
                    <div class="stat-card-sub">Practitioner: {{ $p['new']['pending'] }} Pending · {{ $p['new']['under_review'] }} Under Review</div>
                    <div class="stat-card-sub">Consultant: {{ $c['new']['pending'] }} Pending · {{ $c['new']['under_review'] }} Under Review</div>
                </div>
            </a>
        </div>

        {{-- Renewal Applications (not open yet, so the card does not link) --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card stat-card-amber stat-card-static">
                <div class="stat-card-icon amber"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $p['renewal']['pending'] + $p['renewal']['under_review'] + $c['renewal']['pending'] + $c['renewal']['under_review'] }}</div>
                    <div class="stat-card-label">Renewal Applications</div>
                    <div class="stat-card-sub">Practitioner: {{ $p['renewal']['pending'] }} Pending · {{ $p['renewal']['under_review'] }} Under Review</div>
                    <div class="stat-card-sub">Consultant: {{ $c['renewal']['pending'] }} Pending · {{ $c['renewal']['under_review'] }} Under Review</div>
                </div>
            </div>
        </div>

        {{-- Scheduled Interviews --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <a href="{{ route('admin.accreditation.division.interviews.scheduled') }}" class="stat-card stat-card-violet">
                <div class="stat-card-icon violet"><i class="bi bi-calendar2-check-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $p['interviews'] + $c['interviews'] }}</div>
                    <div class="stat-card-label">Scheduled Interviews</div>
                    <div class="stat-card-sub">Practitioner: {{ $p['interviews'] }} · Consultant: {{ $c['interviews'] }}</div>
                </div>
            </a>
        </div>

        {{-- Rejected Applications --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <a href="{{ route('admin.accreditation.division.applications.archived') }}" class="stat-card stat-card-red">
                <div class="stat-card-icon red"><i class="bi bi-x-circle-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $p['rejected'] + $c['rejected'] }}</div>
                    <div class="stat-card-label">Rejected Applications</div>
                    <div class="stat-card-sub">Practitioner: {{ $p['rejected'] }} · Consultant: {{ $c['rejected'] }}</div>
                </div>
            </a>
        </div>
    </div>

    {{-- ── Every accreditation type: payment, certificates, totals ── --}}
    <p class="dash-section-title">All Accreditations — Payment &amp; Certificates</p>
    <div class="row mb-4">
        {{-- Total Active Accreditations (all types, not just FATPro) --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card stat-card-blue stat-card-static">
                <div class="stat-card-icon blue"><i class="bi bi-patch-check-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalActiveFATPro }}</div>
                    <div class="stat-card-label">Total Active Accreditations</div>
                    <div class="stat-card-sub">Currently accredited</div>
                </div>
            </div>
        </div>

        {{-- Awaiting Payment/Recommendation --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <a href="{{ route('admin.accreditation.applications.awaiting_payment') }}" class="stat-card stat-card-green">
                <div class="stat-card-icon green"><i class="bi bi-cash-coin"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalAwaitingPayment }}</div>
                    <div class="stat-card-label">Recommendation / Payment</div>
                    <div class="stat-card-sub">Applications awaiting review</div>
                </div>
            </a>
        </div>

        {{-- Certificate Issuance (Releasing) --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <a href="{{ route('admin.accreditation.applications.releasing') }}" class="stat-card stat-card-amber">
                <div class="stat-card-icon amber"><i class="bi bi-award-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalAwaitingCertificate }}</div>
                    <div class="stat-card-label">Certificate Issuance</div>
                    <div class="stat-card-sub">Approved, pending release</div>
                </div>
            </a>
        </div>

        {{-- Total Revoked Accreditations (all types, not just FATPro) --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card stat-card-red stat-card-static">
                <div class="stat-card-icon red"><i class="bi bi-slash-circle-fill"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-num">{{ $totalRevokedFATPro }}</div>
                    <div class="stat-card-label">Total Revoked Accreditations</div>
                    <div class="stat-card-sub">Accreditation revoked</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Charts ── --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="dash-chart-card">
                <div class="dash-chart-title">
                    <i class="bi bi-bar-chart-fill me-1"></i> Monthly Applications — {{ $selectedYear }}
                </div>
                <canvas id="barChart" height="80"
                        data-months="{{ $monthlyRows->pluck('month')->toJson() }}"
                        data-new="{{ $monthlyRows->pluck('new')->toJson() }}"
                        data-renewal="{{ $monthlyRows->pluck('renewal')->toJson() }}"
                        data-accredited="{{ $monthlyRows->pluck('accredited')->toJson() }}"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Monthly Table ── --}}
    <div class="row">
        <div class="col-12">
            <div class="x_panel">
                <div class="x_title d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h2><i class="bi bi-table me-1"></i> Monthly Applications and Accreditations</h2>
                    <form method="GET" action="{{ route('admin.accreditation.dashboard') }}" class="d-flex align-items-center gap-2 mb-0">
                        <label class="form-label mb-0 small fw-semibold">Year:</label>
                        <select name="year" class="form-select form-select-sm w-auto" data-autosubmit>
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
<script src="{{ \App\Support\AssetVersion::url('js/accreditation-dashboard.js') }}" defer></script>
@endpush
