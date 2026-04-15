@extends('layouts.landing')

@section('title', 'Track Application | ARMS')

{{-- Force the navbar into its solid dark state on this light-background page --}}



@section('content')
<section class="section-py bg-light" style="min-height: 70vh; padding-top: 120px;">
    <div class="container pb-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mt-5">
                    <div class="card-body p-5 text-center">
                        <i class="bi bi-search" style="font-size: 3rem; color: #f5b041;"></i>
                        <h2 class="mt-3 mb-4">Track Your Application</h2>
                        <p class="text-muted mb-4">Enter your application tracking number below to check the current status of your accreditation.</p>

                        <form action="#" method="GET">
                            <div class="input-group input-group-lg mb-3">
                                <span class="input-group-text bg-white" id="basic-addon1"><i class="bi bi-hash"></i></span>
                                <input type="text" class="form-control" name="tracking_number" placeholder="Enter Tracking Number (e.g. OSHC-12345)" aria-label="Tracking Number" aria-describedby="basic-addon1" required>
                                <button class="btn btn-primary" type="submit" style="background-color: #0b3d91; border-color: #0b3d91;">Track Status</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="{{ url('/') }}" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection