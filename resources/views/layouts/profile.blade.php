@extends($layout)

@section('title', 'My Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
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
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" value="{{ old('email', $user->email ?? '') }}" required {{ $readOnly ? 'disabled' : '' }}>
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
                            <div class="col-12 mt-4 text-end d-flex justify-content-end gap-2">
                                <button type="button" class="btn px-4" style="background: #1A4A8A; color: #fff; border: none;" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    <i class="bi bi-shield-lock me-1"></i> Change Password
                                </button>
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

@if(!$readOnly)
{{-- ── Change Password Modal ── --}}
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">

            {{-- Modal Header --}}
            <div class="modal-header" style="background: linear-gradient(135deg,#1A4A8A,#0D2B55); border-bottom: none; padding: 20px 24px;">
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <h5 class="modal-title mb-0" id="changePasswordModalLabel" style="color: #fff; font-weight: 700; font-size: 1.1rem;">Change Password</h5>
                        <p class="mb-0" style="color: rgba(255,255,255,.6); font-size: 0.78rem;">Update your account security credentials</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body" style="padding: 24px;">



                @if(session('password_error') || $errors->has('current_password') || $errors->has('new_password'))
                    <div class="text-danger mb-3 fw-semibold" style="font-size: 0.85rem;">
                        @foreach($errors->get('current_password') as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                        @foreach($errors->get('new_password') as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('profile.change_password') }}" method="POST" id="changePasswordForm" novalidate>
                    @csrf

                    <div class="row g-3">
                        {{-- Current Password --}}
                        <div class="col-12">
                            <label for="current_password" class="form-label fw-semibold" style="color: #2A3F54; font-size: .88rem;">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter your current password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPass" title="Show/hide password">
                                    <i class="bi bi-eye" id="toggleCurrentPassIcon"></i>
                                </button>
                            </div>
                        </div>

                        {{-- New Password --}}
                        <div class="col-12">
                            <label for="new_password" class="form-label fw-semibold" style="color: #2A3F54; font-size: .88rem;">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Min. 8 characters, letters & numbers" minlength="8" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPass" title="Show/hide password">
                                    <i class="bi bi-eye" id="toggleNewPassIcon"></i>
                                </button>
                            </div>
                            <div id="pwStrengthFeedback" class="mt-2" style="font-size: 0.82rem; line-height: 1.5;">
                                <div class="text-secondary" id="pw-rule-length"><i class="bi bi-circle me-2"></i>At least 8 characters</div>
                                <div class="text-secondary" id="pw-rule-letter"><i class="bi bi-circle me-2"></i>Contains letters</div>
                                <div class="text-secondary" id="pw-rule-number"><i class="bi bi-circle me-2"></i>Contains numbers</div>
                            </div>
                        </div>

                        {{-- Confirm New Password --}}
                        <div class="col-12">
                            <label for="new_password_confirmation" class="form-label fw-semibold" style="color: #2A3F54; font-size: .88rem;">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" placeholder="Re-enter new password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmNewPass" title="Show/hide password">
                                    <i class="bi bi-eye" id="toggleConfirmNewPassIcon"></i>
                                </button>
                                <div class="invalid-feedback" id="confirmNewPwFeedback">Passwords do not match.</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer" style="border-top: 1px solid #eef0f3; padding: 16px 24px;">
                <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="changePasswordForm" class="btn px-4" id="changePwBtn" style="background: linear-gradient(135deg,#1A4A8A,#0D2B55); color: #fff; border: none; font-weight: 600;">
                    <i class="bi bi-shield-check me-1"></i> Update Password
                </button>
            </div>

        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script src="{{ asset('js/profile.js') }}?v={{ filemtime(public_path('js/profile.js')) }}"></script>
@endpush
