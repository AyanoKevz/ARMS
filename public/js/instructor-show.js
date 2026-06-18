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
                        alert('Only PDF files are allowed.');
                        this.value = '';
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
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('was-validated');
                
                const firstInvalid = this.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                this.classList.add('was-validated');
                const btn = document.getElementById('batchUpdateBtn');
                const text = document.getElementById('batchUpdateText');
                const spinner = document.getElementById('batchUpdateSpinner');
                if(btn) btn.disabled = true;
                if(text) text.classList.add('d-none');
                if(spinner) spinner.classList.remove('d-none');
            }
        });
    }
});
