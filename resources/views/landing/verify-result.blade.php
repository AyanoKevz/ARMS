@extends('layouts.landing')

@section('title', 'Email Verification | ARMS')

@section('content')
<div class="register-page d-flex align-items-center" style="min-height: 80vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="reg-card text-center py-5 px-4">

                    @if($status === 'success')
                        {{-- Success icon --}}
                        <div class="mb-4" style="
                            width: 84px; height: 84px;
                            background: linear-gradient(135deg, #d4f7e0, #a8efcc);
                            border-radius: 50%;
                            display: flex; align-items: center; justify-content: center;
                            font-size: 2.4rem;
                            margin: 0 auto;
                            box-shadow: 0 4px 20px rgba(40,180,100,.2);
                        ">✅</div>

                        <h1 style="font-size: 1.6rem; color: var(--navy-deep); margin: 1rem 0 .5rem;">
                            Application Submitted!
                        </h1>
                        <div style="width: 48px; height: 3px; background: var(--gold-light); border-radius: 2px; margin: 0 auto 1.25rem;"></div>

                        <p style="color: #555; font-size: .95rem; margin-bottom: 1.5rem; line-height: 1.7;">
                            Your email has been verified and your accreditation application
                            has been officially submitted to ARMS.
                        </p>

                        {{-- Tracking number card --}}
                        <div style="
                            background: linear-gradient(135deg, #1a2e5a, #0d1f42);
                            border-radius: 12px;
                            padding: 1.25rem 1.5rem;
                            margin-bottom: 1.5rem;
                            box-shadow: 0 4px 16px rgba(10,20,60,.3);
                        ">
                            <p style="color: rgba(255,255,255,.65); font-size: .78rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: .4rem;">
                                Your Tracking Number
                            </p>
                            <p style="color: #D4AC4B; font-size: 1.35rem; font-weight: 800; letter-spacing: 2px; margin: 0;">
                                {{ $trackingNumber }}
                            </p>
                        </div>

                        <div class="alert" style="background: rgba(46,111,216,.08); border: 1px solid rgba(46,111,216,.2); border-radius: 10px; color: #1a2e5a; font-size: .85rem; text-align: left; margin-bottom: 1.5rem;">
                            <i class="bi bi-info-circle-fill me-2 text-primary"></i>
                            Please save your tracking number. You can use it on the
                            <a href="{{ route('track') }}" style="color: var(--gold-light); font-weight: 600;">Track Application</a>
                            page to monitor the status of your application.
                        </div>

                        <a href="{{ url('/') }}" class="btn w-100 fw-semibold"
                            style="background: var(--gold-light); color: var(--navy-deep); border-radius: 10px; padding: .85rem; font-size: 1rem;">
                            <i class="bi bi-house-fill me-2"></i> Return to Homepage
                        </a>
                    @else
                        {{-- Error icon --}}
                        <div class="mb-4" style="
                            width: 84px; height: 84px;
                            background: linear-gradient(135deg, #ffe0e0, #ffb3b3);
                            border-radius: 50%;
                            display: flex; align-items: center; justify-content: center;
                            font-size: 2.4rem;
                            margin: 0 auto;
                            box-shadow: 0 4px 20px rgba(220,53,69,.15);
                        ">⚠️</div>

                        <h1 style="font-size: 1.5rem; color: var(--navy-deep); margin: 1rem 0 .5rem;">
                            Verification Failed
                        </h1>
                        <div style="width: 48px; height: 3px; background: #dc3545; border-radius: 2px; margin: 0 auto 1.25rem;"></div>

                        <p style="color: #666; font-size: .95rem; line-height: 1.7; margin-bottom: 2rem;">
                            {{ $message }}
                        </p>

                        <a href="{{ route('register') }}" class="btn w-100 fw-semibold"
                            style="background: var(--gold-light); color: var(--navy-deep); border-radius: 10px; padding: .85rem; font-size: 1rem;">
                            <i class="bi bi-arrow-left me-2"></i> Back to Registration
                        </a>
                    @endif

                </div>

            </div>
        </div>
    </div>
</div>
@endsection
