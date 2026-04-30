@extends($layout)

@section('title', 'My Profile')

@push('styles')
<style>
    /* ── Profile Split Layout ── */
    .profile-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,.04);
        padding: 30px;
        border-top: 4px solid var(--portal-gold);
    }
    .profile-avatar-wrapper {
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid #eef0f3;
    }
    .profile-avatar-wrapper img {
        width: 140px;
        height: 140px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,.1);
        margin-bottom: 15px;
    }
    .btn-file {
        position: relative;
        overflow: hidden;
    }
    .btn-file input[type=file] {
        position: absolute;
        top: 0; right: 0;
        min-width: 100%; min-height: 100%;
        font-size: 100px;
        text-align: right;
        filter: alpha(opacity=0);
        opacity: 0;
        outline: none;
        background: white;
        cursor: inherit;
        display: block;
    }
    .form-label {
        font-weight: 600;
        color: #2A3F54;
        font-size: .88rem;
    }
    .form-control:focus {
        border-color: var(--portal-gold);
        box-shadow: 0 0 0 0.2rem rgba(212,172,75,.25);
    }
</style>
@endpush

@section('content')
@php
    $myAccreditation = \App\Models\Accreditation::where('user_id', $user->id)
        ->whereIn('status', ['active', 'expired', 'revoked'])
        ->orderBy('created_at', 'desc')
        ->first();
@endphp
<div class="">

    <div class="page-title">
        <div class="title_left">
            <h3>{{ $readOnly ? 'User Profile' : 'My Profile' }}</h3>
        </div>
    </div>
    <div class="clearfix"></div>

    <div class="row pt-2">
        <div class="col-12 col-lg-8 mx-auto">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
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

            <div class="profile-card">
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="row">
                        {{-- Left Col: Photo --}}
                        <div class="col-md-4 border-end pe-md-4">
                            <div class="profile-avatar-wrapper">
                                <img src="{{ asset($user->user_photo ?? 'images/profile_picture/default_photo.jpg') }}" alt="Profile Photo" id="photoPreview" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">
                                <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
                                <p class="text-muted small mb-3">{{ $user->email }}</p>
                                
                                <span class="badge {{ $user->role && strtolower($user->role->name) === 'admin' ? 'bg-primary' : 'bg-success' }} mb-2">
                                    {{ $user->role ? 'Admin' : $user->profile_type }}
                                </span>

                                @if(!$readOnly)
                                <span class="btn btn-outline-primary btn-sm btn-file d-block mx-auto mt-2" style="max-width:140px;">
                                    <i class="bi bi-camera me-1"></i> Change Photo
                                    <input type="file" name="photo" id="photoInput" accept="image/png, image/jpeg, image/jpg">
                                </span>
                                <div class="text-muted mt-1" style="font-size: .7rem;">Max 5MB (JPG, PNG)</div>
                                @endif

                                @if($myAccreditation)
                                <div class="mt-4 p-3 rounded text-start" style="background-color: #f8f9fa; border: 1px solid #e9ecef;">
                                    <h6 class="fw-bold mb-2 pb-1 border-bottom" style="color: #2A3F54; font-size: 0.85rem; text-transform: uppercase;">Accreditation Details</h6>
                                    <p class="mb-1" style="font-size: 0.85rem;"><strong>Number:</strong> <br>{{ $myAccreditation->accreditation_number ?? 'N/A' }}</p>
                                    <p class="mb-1" style="font-size: 0.85rem;"><strong>Date Accredited:</strong> <br>{{ $myAccreditation->date_of_accreditation ? \Carbon\Carbon::parse($myAccreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}</p>
                                    <p class="mb-1" style="font-size: 0.85rem;"><strong>Valid Until:</strong> <br>{{ $myAccreditation->validity_date ? \Carbon\Carbon::parse($myAccreditation->validity_date)->format('F d, Y') : 'N/A' }}</p>
                                    <p class="mb-0" style="font-size: 0.85rem;"><strong>Status:</strong> <br>
                                        @if($myAccreditation->status === 'active')
                                            <span class="badge bg-success">Active</span>
                                        @elseif($myAccreditation->status === 'expired')
                                            <span class="badge bg-warning text-dark">Expired</span>
                                        @elseif($myAccreditation->status === 'revoked')
                                            <span class="badge bg-danger">Revoked</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($myAccreditation->status) }}</span>
                                        @endif
                                    </p>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Right Col: Info --}}
                        <div class="col-md-8 ps-md-4">
                            <h5 class="mb-3 border-bottom pb-2" style="color: #2A3F54; font-weight: 700;">Account Details</h5>
                            
                            {{-- Admin View --}}
                            @if($user->role && strtolower($user->role->name) === 'admin')
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name" value="{{ old('first_name', $profile->first_name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" value="{{ old('last_name', $profile->last_name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Position/Title</label>
                                        <input type="text" class="form-control" name="position" value="{{ old('position', $profile->position ?? '') }}" {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Division</label>
                                        <input type="text" class="form-control" value="{{ $profile->division->name ?? 'N/A' }}" disabled>
                                    </div>
                                </div>

                            {{-- Organization View --}}
                            @elseif($user->profile_type === 'Organization')
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Organization Name</label>
                                        <input type="text" class="form-control" name="name" value="{{ old('name', $profile->name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Head of Organization</label>
                                        <input type="text" class="form-control" name="head_name" value="{{ old('head_name', $profile->head_name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Designation</label>
                                        <input type="text" class="form-control" name="designation" value="{{ old('designation', $profile->designation ?? '') }}" {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Telephone Number</label>
                                        <input type="text" class="form-control" name="telephone" value="{{ old('telephone', $profile->telephone ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Fax Number</label>
                                        <input type="text" class="form-control" name="fax" value="{{ old('fax', $profile->fax ?? '') }}" {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Organization Email</label>
                                        <input type="email" class="form-control" name="email" value="{{ old('email', $profile->email ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Office Address</label>
                                        <input type="text" class="form-control" name="address" value="{{ old('address', $profile->address ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>
                                
                                @php
                                    $rep = $profile->authorizedRepresentatives->first() ?? null;
                                @endphp
                                
                                <h5 class="mb-3 border-bottom pb-2 mt-4" style="color: #2A3F54; font-weight: 700;">Authorized Representative</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="rep_full_name" value="{{ old('rep_full_name', $rep->full_name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Position</label>
                                        <input type="text" class="form-control" name="rep_position" value="{{ old('rep_position', $rep->position ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" name="rep_contact_number" value="{{ old('rep_contact_number', $rep->contact_number ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="rep_email" value="{{ old('rep_email', $rep->email ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>

                            {{-- Individual View --}}
                            @else
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name" value="{{ old('first_name', $profile->first_name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" value="{{ old('last_name', $profile->last_name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" value="{{ old('address', $profile->address ?? '') }}" {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>
                            @endif

                            @if(!$readOnly)
                            <div class="col-12 mt-4 text-end">
                                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save Changes</button>
                            </div>
                            @endif

                        </div>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const photoInput = document.getElementById('photoInput');
        const photoPreview = document.getElementById('photoPreview');

        if(photoInput) {
            photoInput.addEventListener('change', function() {
                const file = this.files[0];
                if(file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    });
</script>
@endpush
