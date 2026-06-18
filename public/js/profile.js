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

    /* ── Change Password: Show/Hide Toggles ── */
    function setupPasswordToggle(btnId, iconId, inputId) {
        const btn = document.getElementById(btnId);
        const icon = document.getElementById(iconId);
        const input = document.getElementById(inputId);
        if (btn && input) {
            btn.addEventListener('click', function() {
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                if (icon) icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }
    }

    setupPasswordToggle('toggleCurrentPass', 'toggleCurrentPassIcon', 'current_password');
    setupPasswordToggle('toggleNewPass', 'toggleNewPassIcon', 'new_password');
    setupPasswordToggle('toggleConfirmNewPass', 'toggleConfirmNewPassIcon', 'new_password_confirmation');

    /* ── Change Password: Real-time Strength Validation ── */
    const newPwInput = document.getElementById('new_password');
    const confirmNewPwInput = document.getElementById('new_password_confirmation');

    if (newPwInput) {
        newPwInput.addEventListener('input', function() {
            const val = this.value;
            const hasLen = val.length >= 8;
            const hasLetter = /[A-Za-z]/.test(val);
            const hasNumber = /\d/.test(val);

            const rLen = document.getElementById('pw-rule-length');
            const rLet = document.getElementById('pw-rule-letter');
            const rNum = document.getElementById('pw-rule-number');

            if (rLen) {
                rLen.className = hasLen ? 'text-success' : 'text-secondary';
                rLen.querySelector('i').className = hasLen ? 'bi bi-check-circle-fill me-2' : 'bi bi-circle me-2';
            }
            if (rLet) {
                rLet.className = hasLetter ? 'text-success' : 'text-secondary';
                rLet.querySelector('i').className = hasLetter ? 'bi bi-check-circle-fill me-2' : 'bi bi-circle me-2';
            }
            if (rNum) {
                rNum.className = hasNumber ? 'text-success' : 'text-secondary';
                rNum.querySelector('i').className = hasNumber ? 'bi bi-check-circle-fill me-2' : 'bi bi-circle me-2';
            }

            this.setCustomValidity((hasLen && hasLetter && hasNumber) ? '' : 'Password must contain at least 8 characters, including letters and numbers.');

            // Re-trigger confirm match
            if (confirmNewPwInput && confirmNewPwInput.value) {
                const matches = confirmNewPwInput.value === val;
                confirmNewPwInput.setCustomValidity(matches ? '' : 'Passwords do not match.');
                if (matches) {
                    confirmNewPwInput.classList.remove('is-invalid');
                    confirmNewPwInput.classList.add('is-valid');
                } else {
                    confirmNewPwInput.classList.remove('is-valid');
                    confirmNewPwInput.classList.add('is-invalid');
                }
            }
        });
    }

    /* ── Change Password: Confirm Match Validation ── */
    if (confirmNewPwInput) {
        confirmNewPwInput.addEventListener('input', function() {
            if (this.value) {
                const matches = this.value === newPwInput.value;
                this.setCustomValidity(matches ? '' : 'Passwords do not match.');
                if (matches) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    }

    /* ── Change Password: Form Validation ── */
    const changePwForm = document.getElementById('changePasswordForm');
    if (changePwForm) {
        changePwForm.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('was-validated');
                const firstInvalid = this.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
                return;
            }
            this.classList.add('was-validated');
            // Show loading state
            const btn = document.getElementById('changePwBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Updating...';
            }
        });
    }

    /* ── Reset form state when modal is closed ── */
    const pwModalEl = document.getElementById('changePasswordModal');
    if (pwModalEl) {
        pwModalEl.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('changePasswordForm');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
                form.querySelectorAll('.is-valid, .is-invalid').forEach(el => el.classList.remove('is-valid', 'is-invalid'));
            }
            // Reset strength indicators
            ['pw-rule-length', 'pw-rule-letter', 'pw-rule-number'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.className = 'text-secondary';
                    el.querySelector('i').className = 'bi bi-circle me-2';
                }
            });
            // Reset toggle icons
            ['toggleCurrentPassIcon', 'toggleNewPassIcon', 'toggleConfirmNewPassIcon'].forEach(id => {
                const icon = document.getElementById(id);
                if (icon) icon.className = 'bi bi-eye';
            });
            // Reset input types to password
            ['current_password', 'new_password', 'new_password_confirmation'].forEach(id => {
                const input = document.getElementById(id);
                if (input) input.type = 'password';
            });
            // Reset button state
            const btn = document.getElementById('changePwBtn');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Update Password';
            }
        });
    }
});
