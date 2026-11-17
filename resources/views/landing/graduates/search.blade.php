@extends('layouts.landing')

@section('title', 'Graduate Lookup | ARMS')

@section('content')
<section class="section-py bg-light" style="min-height: 70vh; padding-top: 120px;">
    <div class="container pb-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                {{-- Search Box — same pattern as Track Application --}}
                <div class="card shadow-sm border-0 mt-5 mb-4">
                    <div class="card-body p-5 text-center">
                        <i class="bi bi-mortarboard" style="font-size: 3rem; color: #f5b041;"></i>
                        <h2 class="mt-3 mb-4">Graduate Lookup</h2>
                        <p class="text-muted mb-4">
                            Search by the <strong>graduate's name</strong> or <strong>certificate number</strong>,
                            or by the <strong>training provider's name</strong> or <strong>accreditation number</strong>
                            to see all the graduates of the trainings it conducted.
                        </p>

                        <form id="graduateSearchForm" action="{{ route('graduates.search') }}" method="GET" novalidate>
                            <div class="input-group input-group-lg mb-3">
                                <span class="input-group-text bg-white"><i class="bi bi-search fw-bold" style="color: #0b3d91;"></i></span>
                                <input type="text" class="form-control" name="q" maxlength="100"
                                    placeholder="e.g. Juan Dela Cruz, certificate no., or provider name" value="{{ $term }}" required>
                                <button class="btn btn-primary px-4 fw-semibold" type="submit" id="graduateSearchBtn" style="background-color: #0b3d91; border-color: #0b3d91;">
                                    <span id="graduateSearchText">Search</span>
                                    <span id="graduateSearchSpinner" class="d-none">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span> Loading...
                                    </span>
                                </button>
                            </div>
                        </form>

                        <p class="text-muted mb-0" style="font-size:.8rem;">
                            Searching as <strong>{{ $visitor->name }}</strong> ({{ $visitor->email }}) &middot; your searches are recorded.
                        </p>
                    </div>
                </div>

                {{-- Results --}}
                @if($searched)
                    @if($error)
                        <div class="alert alert-warning text-center" style="border-radius:10px;">
                            <i class="bi bi-exclamation-circle-fill me-2"></i> {{ $error }}
                        </div>
                    @elseif($graduates->isEmpty() && $providers->isEmpty() && !$tooMany)
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
                            <div class="card-body p-5 text-center">
                                <i class="bi bi-search text-muted" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-3 mb-2">No Graduates Found</h5>
                                <p class="text-muted mb-0">
                                    No graduate or training provider matches "<strong>{{ $term }}</strong>".
                                    Check the spelling, or try the full certificate number or accreditation number.
                                </p>
                            </div>
                        </div>
                    @else
                        {{-- Graduates matched by name or certificate number --}}
                        @if($graduates->isNotEmpty())
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div>
                                        <p class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Graduates</p>
                                        <h4 class="mb-0" style="color: #0b3d91; font-weight: 700;">Matching "{{ $term }}"</h4>
                                    </div>
                                    <span class="badge rounded-pill px-3 py-2 fs-6" style="background-color: #e0f2fe; color: #0369a1;">
                                        {{ number_format($graduates->count()) }}{{ $graduatesTruncated ? '+' : '' }} {{ \Illuminate\Support\Str::plural('Graduate', $graduates->count()) }}
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                @if($graduatesTruncated)
                                <div class="alert alert-warning py-2" style="border-radius:10px;font-size:.88rem;">
                                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                                    Showing the first {{ $graduates->count() }} matches. Enter the full name or the certificate number to narrow it down.
                                </div>
                                @endif
                                <div class="table-responsive rounded-3 border">
                                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.9rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:56px;" class="text-center">#</th>
                                                <th>Name of Graduate</th>
                                                <th>Certificate No.</th>
                                                <th>Training Provider</th>
                                                <th>Training</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($graduates as $graduate)
                                            @php
                                                $report = $graduate->postTrainingReport;
                                                $ntc = $report?->ntcReport;
                                            @endphp
                                            <tr>
                                                <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                                <td class="fw-semibold">{{ $graduate->fullName() }}</td>
                                                <td>{{ $graduate->certificate_number }}</td>
                                                <td>
                                                    {{ \App\Http\Controllers\GraduateLookupController::providerName($report?->accreditation) }}
                                                    <div class="text-muted" style="font-size:.78rem;">{{ $report?->accreditation?->accreditationType?->name }}</div>
                                                </td>
                                                <td>
                                                    {{ $ntc?->trainingType?->name ?? 'Training' }}
                                                    <div class="text-muted" style="font-size:.78rem;">{{ $ntc ? $ntc->trainingPeriodLabel() : 'N/A' }}</div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($tooMany)
                        <div class="alert alert-warning text-center" style="border-radius:10px;">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            Too many training providers match "<strong>{{ $term }}</strong>". Please enter more of the provider's name, or its accreditation number.
                        </div>
                        @endif

                        {{-- Training providers matched by name or accreditation number --}}
                        @foreach($providers as $provider)
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div>
                                        <p class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Training Provider</p>
                                        <h4 class="mb-1" style="color: #0b3d91; font-weight: 700;">{{ $provider['name'] }}</h4>
                                        <p class="text-muted mb-0" style="font-size:.88rem;">
                                            {{ $provider['accreditation_type'] }}
                                            @if($provider['accreditation_number'])
                                                &middot; Accreditation No. {{ $provider['accreditation_number'] }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="badge rounded-pill px-3 py-2 fs-6" style="background-color: #e0f2fe; color: #0369a1;">
                                        {{ number_format($provider['graduate_count']) }} {{ \Illuminate\Support\Str::plural('Graduate', $provider['graduate_count']) }}
                                    </span>
                                </div>
                            </div>

                            <div class="card-body p-4">
                                @foreach($provider['trainings'] as $report)
                                @php $ntc = $report->ntcReport; @endphp
                                <div class="rounded-3 border mb-3 overflow-hidden">
                                    <div class="px-3 py-2 d-flex flex-wrap justify-content-between gap-2" style="background:#f8fafc;">
                                        <div class="fw-bold" style="color:#1a2e5a;font-size:.92rem;">
                                            <i class="bi bi-journal-medical me-1"></i>
                                            {{ $ntc?->trainingType?->name ?? 'Training' }}
                                            @if($ntc?->trainingMode)
                                                <span class="text-muted fw-normal">&middot; {{ $ntc->trainingMode->name }}</span>
                                            @endif
                                        </div>
                                        <div class="text-muted" style="font-size:.85rem;">
                                            <i class="bi bi-calendar-event me-1"></i>
                                            {{ $ntc ? $ntc->trainingPeriodLabel() : 'N/A' }}
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.9rem;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:56px;" class="text-center">#</th>
                                                    <th>Name of Graduate</th>
                                                    <th>Certificate No.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($report->participants as $participant)
                                                <tr>
                                                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                                    <td class="fw-semibold">{{ $participant->fullName() }}</td>
                                                    <td>{{ $participant->certificate_number }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    @endif
                @endif

            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.getElementById('graduateSearchForm').addEventListener('submit', function () {
    document.getElementById('graduateSearchBtn').disabled = true;
    document.getElementById('graduateSearchText').classList.add('d-none');
    document.getElementById('graduateSearchSpinner').classList.remove('d-none');
});
</script>
@endpush
