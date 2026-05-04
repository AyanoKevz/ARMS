@extends('layouts.email')

@section('title', 'Instructor Update Approved')

@section('content')
<div style="background-color: #f8fafc; padding: 30px; border-radius: 8px; font-family: 'Helvetica Neue', Arial, sans-serif;">
    <h2 style="color: #0D2B55; margin-top: 0;">Update Request Approved</h2>
    
    <p style="color: #333; font-size: 16px; line-height: 1.5;">
        Dear <strong>{{ $instructor->user->name }}</strong>,
    </p>

    <p style="color: #333; font-size: 16px; line-height: 1.5;">
        Your request to update the credentials for your instructor, <strong>{{ $instructor->first_name }} {{ $instructor->last_name }}</strong>, has been approved by the OSHC administration.
    </p>

    <p style="color: #333; font-size: 16px; line-height: 1.5;">
        The credential update forms have now been unlocked in your applicant portal. You may log in and upload the new documents at your earliest convenience.
    </p>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <a href="{{ route('applicant.instructors.show', $instructor->id) }}" style="background-color: #D4AC4B; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;">
            Update Credentials Now
        </a>
    </div>

    <p style="color: #666; font-size: 14px; line-height: 1.5; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
        If you have any questions, please contact the OSHC HCD support team.
    </p>
</div>
@endsection
