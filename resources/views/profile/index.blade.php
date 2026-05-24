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
                                <img src="{{ asset($user->user_photo ?? 'images/profile_picture/default_photo.jpg') }}" alt="Profile Photo" id="photoPreview" loading="lazy" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random';">
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
                                        @if($readOnly)
                                            <input type="text" class="form-control" value="{{ $profile->adminRole->name ?? '—' }}" disabled>
                                        @else
                                            <select class="form-control" name="admin_role_id" required>
                                                <option value="">Select Role</option>
                                                @foreach(\App\Models\AdminRole::all() as $role)
                                                    <option value="{{ $role->id }}" {{ old('admin_role_id', $profile->admin_role_id ?? '') == $role->id ? 'selected' : '' }}>
                                                        {{ $role->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Division</label>
                                        <input type="text" class="form-control" value="{{ $profile->division->name ?? 'N/A' }}" disabled>
                                    </div>
                                </div>

                            {{-- Organization View --}}
                            @elseif($user->profile_type === 'Organization')
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Organization Name</label>
                                        <input type="text" class="form-control" name="name" value="{{ old('name', $profile->name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Organization Email</label>
                                        <input type="email" class="form-control" name="email" value="{{ old('email', $profile->email ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Head of Organization</label>
                                        <input type="text" class="form-control" name="head_name" value="{{ old('head_name', $profile->head_name ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Designation</label>
                                        <input type="text" class="form-control" name="designation" value="{{ old('designation', $profile->designation ?? '') }}" {{ $readOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Telephone Number</label>
                                        <input type="text" class="form-control" id="telephone" name="telephone" value="{{ old('telephone', preg_replace('/[^0-9]/', '', $profile->telephone ?? '')) }}" placeholder="e.g. 0281234567" pattern="[0-9]{10}" maxlength="10" required {{ $readOnly ? 'disabled' : '' }}>
                                        <div class="invalid-feedback">Enter a valid 10-digit telephone number (e.g. 0281234567).</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Fax Number</label>
                                        <input type="text" class="form-control" id="fax" name="fax" value="{{ old('fax', preg_replace('/[^0-9]/', '', $profile->fax ?? '')) }}" placeholder="e.g. 0281234567" pattern="[0-9]{10}" maxlength="10" {{ $readOnly ? 'disabled' : '' }}>
                                        <div class="invalid-feedback">Enter a valid 10-digit facsimile number (e.g. 0281234567).</div>
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
                                        <input type="text" class="form-control" id="rep_contact" name="rep_contact_number" value="{{ old('rep_contact_number', $rep->contact_number ?? '') }}" required pattern="^(09|\+639)\d{9}$" maxlength="13" {{ $readOnly ? 'disabled' : '' }}>
                                        <div class="invalid-feedback">Enter a valid PH mobile number (e.g. 09171234567).</div>
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

        /* ── Live Validation for Telephone, Fax, and Rep Contact ── */
        const telInput = document.getElementById('telephone');
        const faxInput = document.getElementById('fax');
        const repContactInput = document.getElementById('rep_contact');

        function validateLandline(input, typeName) {
            let val = input.value.replace(/[^0-9]/g, '');
            input.value = val;
            
            if (val.length === 10) {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                input.setCustomValidity('');
            } else if (val.length === 0) {
                input.classList.remove('is-invalid', 'is-valid');
                input.setCustomValidity('');
            } else {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                input.setCustomValidity(`Enter a valid 10-digit ${typeName} number (e.g. 0281234567).`);
            }
        }

        function validateRepContact(input) {
            let val = input.value.replace(/[^\d+]/g, '');
            if (val.startsWith('+')) {
                val = '+' + val.slice(1).replace(/\+/g, '');
            } else {
                val = val.replace(/\+/g, '');
            }
            input.value = val;

            const pattern = /^(09|\+639)\d{9}$/;
            if (val.length === 0) {
                if (input.hasAttribute('required')) {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                    input.setCustomValidity('Contact number is required.');
                } else {
                    input.classList.remove('is-invalid', 'is-valid');
                    input.setCustomValidity('');
                }
            } else {
                if (pattern.test(val)) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                    input.setCustomValidity('');
                } else {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                    input.setCustomValidity('Enter a valid PH mobile number (e.g. 09171234567).');
                }
            }
        }

        if (telInput) {
            if (telInput.value) validateLandline(telInput, 'telephone');
            telInput.addEventListener('input', function() {
                validateLandline(this, 'telephone');
            });
        }

        if (faxInput) {
            if (faxInput.value) validateLandline(faxInput, 'facsimile');
            faxInput.addEventListener('input', function() {
                validateLandline(this, 'facsimile');
            });
        }

        if (repContactInput) {
            if (repContactInput.value) validateRepContact(repContactInput);
            repContactInput.addEventListener('input', function() {
                validateRepContact(this);
            });
        }
    });
</script>
@endpush
