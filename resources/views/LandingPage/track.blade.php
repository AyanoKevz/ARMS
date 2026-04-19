@extends('layouts.landing')

@section('title', 'Track Application | ARMS')

@section('content')
<section class="section-py bg-light" style="min-height: 70vh; padding-top: 120px;">
    <div class="container pb-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                {{-- Status Messages --}}
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- Track Search Box --}}
                <div class="card shadow-sm border-0 mt-5 mb-4">
                    <div class="card-body p-5 text-center">
                        <i class="bi bi-search" style="font-size: 3rem; color: #f5b041;"></i>
                        <h2 class="mt-3 mb-4">Track Your Application</h2>
                        <p class="text-muted mb-4">Enter your application tracking number below to check the current status of your accreditation.</p>

                        <form action="{{ route('track') }}" method="GET">
                            <div class="input-group input-group-lg mb-3">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-hash text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" name="tracking_number" placeholder="Enter Tracking Number (e.g. ARMS-2026-000001)" value="{{ request('tracking_number') }}" required>
                                <button class="btn btn-primary px-4 fw-semibold" type="submit" style="background-color: #0b3d91; border-color: #0b3d91;">Track Status</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Track Results --}}
                @if(request()->has('tracking_number'))
                    @if($application)
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                                <h4 class="mb-0" style="color: #0b3d91; font-weight: 700;">Application Details</h4>
                            </div>
                            <div class="card-body p-4">
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <p class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Tracking Number</p>
                                        <p class="h5 text-dark fw-bold">{{ $application->tracking_number }}</p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <p class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Current Status</p>
                                        <span class="badge rounded-pill bg-info text-dark px-3 py-2 fs-6" style="background-color: #e0f2fe !important; color: #0369a1 !important;">
                                            {{ $application->latestStatus?->status?->name ?? 'Submitted' }}
                                        </span>
                                    </div>
                                </div>
                                <hr class="my-4" style="border-color: #e9ecef;">
                                <h5 class="mb-3 fw-bold" style="color: #1a2e5a;">Submitted Documents</h5>
                                <div class="list-group list-group-flush border-top">
                                    @foreach($application->documents as $doc)
                                        <div class="list-group-item px-0 py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between border-bottom">
                                            <div class="mb-2 mb-md-0 me-md-3 flex-grow-1">
                                                <h6 class="mb-1 fw-bold">{{ $doc->documentType->name ?? 'Requirement Document' }}</h6>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    @if($doc->status === 'approved')
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="background-color: #dcfce7!important; color: #166534!important;"><i class="bi bi-check-circle me-1"></i> Approved</span>
                                                    @elseif($doc->status === 'returned' || $doc->status === 'rejected')
                                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="background-color: #fee2e2!important; color: #991b1b!important;"><i class="bi bi-x-circle me-1"></i> Requires Resubmission</span>
                                                    @else
                                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="background-color: #fef9c3!important; color: #854d0e!important;"><i class="bi bi-clock me-1"></i> Pending Review</span>
                                                    @endif
                                                    
                                                    <span class="text-muted small">
                                                        <i class="bi bi-file-earmark-pdf"></i> Uploaded file
                                                    </span>
                                                </div>
                                                
                                                @if(($doc->status === 'returned' || $doc->status === 'rejected') && $doc->remarks)
                                                    <div class="alert alert-danger mt-3 mb-0 py-2 px-3 border-0 bg-danger bg-opacity-10 text-danger shadow-sm rounded">
                                                        <strong><i class="bi bi-chat-left-text-fill me-1"></i> Evaluator Remarks:</strong>
                                                        <p class="mb-0 mt-1 small" style="color: #7f1d1d">{{ $doc->remarks }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                            
                                            {{-- Resubmission Form if necessary --}}
                                            @if($doc->status === 'returned' || $doc->status === 'rejected')
                                                <div class="mt-3 mt-md-0 w-100" style="max-width: 380px;">
                                                    <form action="{{ route('track.resubmit', $doc->id) }}" method="POST" enctype="multipart/form-data" class="d-flex border border-danger border-opacity-25 rounded bg-light p-2 shadow-sm align-items-center">
                                                        @csrf
                                                        <label class="btn btn-outline-danger btn-sm m-0 border-0 fw-semibold text-nowrap me-2 position-relative" style="background-color: #fff0f0;">
                                                            <i class="bi bi-upload"></i> Browse...
                                                            <input type="file" name="replacement_file" class="position-absolute top-0 start-0 opacity-0 w-100 h-100 cursor-pointer" accept=".pdf" style="cursor: pointer;" required>
                                                        </label>
                                                        <button type="submit" class="btn btn-sm btn-danger fw-semibold flex-grow-1">Submit</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning text-center border-0 shadow-sm p-4 rounded-3" style="background-color: #fff8e6;">
                            <div class="mb-3">
                                <i class="bi bi-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                            </div>
                            <h4 class="alert-heading fw-bold" style="color: #856404;">No Application Found</h4>
                            <p class="mb-0 text-dark">We couldn't find any application matching the tracking number <strong>{{ request('tracking_number') }}</strong>.</p>
                            <p class="small text-muted mt-2">Please double-check the tracking number from your email and try again.</p>
                        </div>
                    @endif
                @endif

                <div class="text-center mt-4 mb-5">
                    <a href="{{ url('/') }}" class="text-decoration-none fw-semibold" style="color: #0b3d91;">
                        <i class="bi bi-arrow-left me-1"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Extra Script for file input interaction UX --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type="file"]').forEach(function (input) {
            input.addEventListener('change', function (e) {
                if(e.target.files.length > 0) {
                    let label = e.target.closest('label');
                    if(label) {
                        label.innerHTML = '<i class="bi bi-file-earmark-check"></i> ' + e.target.files[0].name.substring(0, 15) + (e.target.files[0].name.length>15 ? '...' : '') + '<input type="file" name="replacement_file" class="position-absolute top-0 start-0 opacity-0 w-100 h-100" accept=".pdf" required>';
                    }
                }
            });
        });
    });
</script>
@endsection