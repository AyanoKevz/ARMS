document.addEventListener("DOMContentLoaded", function() {
    function bindFileInputs(container = document) {
        container.querySelectorAll('.real-file-input').forEach(input => {
            if (input.dataset.bound === 'true') return;
            input.dataset.bound = 'true';
            
            input.addEventListener('change', function () {
                const wrapper  = this.closest('.file-upload-wrapper');
                if (!wrapper) return;
                const nameSpan = wrapper.querySelector('.file-name-text');
                const fileBtn  = wrapper.querySelector('.custom-file-btn');

                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    if (!file.name.toLowerCase().endsWith('.pdf')) {
                        this.value = '';
                        this.classList.add('is-invalid');
                        const feedback = wrapper.querySelector('.file-invalid-feedback');
                        if (feedback) {
                            feedback.textContent = 'Only PDF files are allowed.';
                            feedback.style.display = 'block';
                        }
                        if (nameSpan) {
                            nameSpan.textContent = 'No file chosen';
                            nameSpan.classList.add('text-muted');
                            nameSpan.classList.remove('text-primary', 'fw-semibold');
                        }
                        if (fileBtn) {
                            fileBtn.classList.add('btn-outline-primary');
                            fileBtn.classList.remove('btn-primary', 'text-white');
                        }
                        return;
                    }
                    
                    if (nameSpan) {
                        nameSpan.textContent = file.name;
                        nameSpan.classList.remove('text-muted');
                        nameSpan.classList.add('text-primary', 'fw-semibold');
                    }
                    if (fileBtn) {
                        fileBtn.classList.remove('btn-outline-primary');
                        fileBtn.classList.add('btn-primary', 'text-white');
                    }
                } else {
                    if (nameSpan) {
                        nameSpan.textContent = 'No file chosen';
                        nameSpan.classList.add('text-muted');
                        nameSpan.classList.remove('text-primary', 'fw-semibold');
                    }
                    if (fileBtn) {
                        fileBtn.classList.add('btn-outline-primary');
                        fileBtn.classList.remove('btn-primary', 'text-white');
                    }
                }
            });
        });
    }

    bindFileInputs(document);

    const form = document.getElementById('batch-update-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const errorBanner  = document.getElementById('batch-form-error-banner');
            const errorMessage = document.getElementById('batch-form-error-message');

            if (errorBanner) errorBanner.style.display = 'none';

            // 1. Check HTML5 validity
            let isValid = this.checkValidity();

            // 2. Check if at least one file was selected or input modified
            let hasSelectedFile = false;
            form.querySelectorAll('input[type="file"]').forEach(fileInput => {
                if (fileInput.files && fileInput.files.length > 0) {
                    hasSelectedFile = true;
                }
            });

            let hasChangedField = false;
            form.querySelectorAll('input[type="text"], input[type="date"]').forEach(input => {
                if (input.defaultValue !== undefined && input.value.trim() !== input.defaultValue.trim()) {
                    hasChangedField = true;
                }
            });

            // Prevent submit if invalid OR no file selected / field updated
            if (!isValid || (!hasSelectedFile && !hasChangedField)) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('was-validated');

                if (!hasSelectedFile && !hasChangedField) {
                    if (errorMessage) {
                        errorMessage.textContent = 'Please upload at least one replacement PDF file or update credential information before submitting.';
                    }
                    if (errorBanner) errorBanner.style.display = 'block';

                    form.querySelectorAll('.real-file-input').forEach(input => {
                        input.classList.add('is-invalid');
                    });
                } else if (!isValid) {
                    if (errorMessage) {
                        errorMessage.textContent = 'Please complete all required fields correctly before submitting.';
                    }
                    if (errorBanner) errorBanner.style.display = 'block';
                }

                const firstInvalid = this.querySelector(':invalid, .is-invalid') || errorBanner;
                if (firstInvalid) {
                    if (typeof firstInvalid.focus === 'function') firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }

            // Valid submission: hide error banner & trigger loader
            if (errorBanner) errorBanner.style.display = 'none';
            this.classList.add('was-validated');
            const btn = document.getElementById('batchUpdateBtn');
            const text = document.getElementById('batchUpdateText');
            const spinner = document.getElementById('batchUpdateSpinner');
            if (btn) btn.disabled = true;
            if (text) text.classList.add('d-none');
            if (spinner) spinner.classList.remove('d-none');
        });
    }
});
