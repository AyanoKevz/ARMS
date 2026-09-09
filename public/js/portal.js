/**
 * ARMS Portal JS
 * Contains: File Viewer Modal logic.
 * CSS: See public/css/portal.css  (FILE VIEWER MODAL section)
 */

(function () {
    'use strict';

    /* ─────────────────────────────────────────────
       FILE VIEWER MODAL
       Intercepts all <a data-file-modal> clicks and
       opens the linked file inside #fileViewerModal.
    ───────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        var modal   = document.getElementById('fileViewerModal');
        var frame   = document.getElementById('fileViewerFrame');
        var img     = document.getElementById('fileViewerImage');
        var label   = document.getElementById('fileViewerModalLabel');
        var dlLink  = document.getElementById('fileViewerDownload');

        if (!modal || !frame) return;

        // Delegated click — works for dynamically rendered content too
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-file-modal]');
            if (!link) return;

            e.preventDefault();

            var url   = link.getAttribute('href');
            var title = link.getAttribute('data-file-title') || 'File Preview';

            if (label)  label.textContent = title;
            if (dlLink) dlLink.href = url;

            // Check if the URL points to an image
            var isImage = /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(url.split('?')[0]);

            if (isImage && img) {
                frame.style.display = 'none';
                frame.src = 'about:blank';
                img.src = url;
                img.style.display = 'block';
            } else {
                if (img) {
                    img.src = '';
                    img.style.display = 'none';
                }
                frame.src = url;
                frame.style.display = 'block';
            }

            if (window.bootstrap && window.bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });

        // Clear elements on modal close to stop PDF/media loading
        modal.addEventListener('hidden.bs.modal', function () {
            frame.src = 'about:blank';
            frame.style.display = 'block';
            if (img) {
                img.src = '';
                img.style.display = 'none';
            }
        });

        // Center image inside iframe if browser wraps direct image response (for dynamic routes)
        frame.addEventListener('load', function () {
            try {
                var doc = frame.contentDocument || frame.contentWindow.document;
                if (!doc || !doc.body) return;

                var imgEl = doc.querySelector('img');
                if (imgEl) {
                    var bodyText = doc.body.textContent ? doc.body.textContent.trim() : '';
                    var hasOnlyImg = (doc.body.children.length === 1 && doc.body.firstElementChild.tagName === 'IMG') ||
                                     (doc.body.children.length === 0 && bodyText === '') ||
                                     (doc.contentType && doc.contentType.indexOf('image/') === 0);

                    // Check for nested wrapper elements in some browser representations
                    if (!hasOnlyImg && doc.body.children.length > 0) {
                        var nonImgElements = Array.from(doc.body.querySelectorAll('*')).filter(function(el) {
                            return el.tagName !== 'IMG' && el.tagName !== 'STYLE' && el.tagName !== 'HEAD' && el.tagName !== 'BODY';
                        });
                        if (nonImgElements.length === 0 && bodyText === '') {
                            hasOnlyImg = true;
                        }
                    }

                    if (hasOnlyImg) {
                        doc.body.style.display = 'flex';
                        doc.body.style.alignItems = 'center';
                        doc.body.style.justifyContent = 'center';
                        doc.body.style.margin = '0';
                        doc.body.style.padding = '20px';
                        doc.body.style.height = '100vh';
                        doc.body.style.boxSizing = 'border-box';
                        doc.body.style.backgroundColor = '#121824';

                        imgEl.style.maxWidth = '100%';
                        imgEl.style.maxHeight = '100%';
                        imgEl.style.width = 'auto';
                        imgEl.style.height = 'auto';
                        imgEl.style.objectFit = 'contain';
                        imgEl.style.margin = '0';
                        imgEl.style.boxShadow = '0 10px 30px rgba(0,0,0,0.5)';
                        imgEl.style.borderRadius = '4px';
                    }
                }
            } catch (err) {
                console.warn('Could not style iframe body (possibly cross-origin):', err);
            }
        });
    });

    /* ─────────────────────────────────────────────
       COLLAPSED-SECTION VALIDATION

       A required field inside a collapsed accordion
       section is display:none, so the browser cannot
       focus it to report the message — the submit
       just dies silently. Re-open the section that
       holds the offending field first.

       "invalid" does not bubble, hence the capture.
    ───────────────────────────────────────────── */
    document.addEventListener('invalid', function (e) {
        var field = e.target;
        if (!field || !field.closest) return;

        var panel = field.closest('.collapse:not(.show)');

        while (panel) {
            // Class toggled directly rather than through the Collapse API: the
            // browser reports validity in this same tick, before an animation
            // would have finished revealing the field.
            panel.classList.remove('collapsing');
            panel.classList.add('show');

            var toggle = document.querySelector('[data-bs-target="#' + panel.id + '"]');
            if (toggle) {
                toggle.classList.remove('collapsed');
                toggle.setAttribute('aria-expanded', 'true');
            }

            panel = panel.parentElement ? panel.parentElement.closest('.collapse:not(.show)') : null;
        }
    }, true);

    /* ─────────────────────────────────────────────
       PDF UPLOAD GUARD

       Any <input type="file" data-validate-pdf> is
       checked the moment a file is picked, instead
       of leaving the applicant to discover after a
       long upload that the server rejected it.

       The ceiling comes from window.ARMS.limits
       (published by App\Support\UploadLimits), so
       the browser can never allow more than PHP and
       the max:15360 rule accept.
    ───────────────────────────────────────────── */
    document.addEventListener('change', function (e) {
        var input = e.target;

        if (!input || input.type !== 'file' || !input.hasAttribute('data-validate-pdf')) return;

        var file = input.files && input.files[0];
        if (!file) {
            input.classList.remove('is-invalid');
            return;
        }

        var maxBytes = (window.ARMS && window.ARMS.limits && window.ARMS.limits.maxFileBytes)
            || (15 * 1024 * 1024);
        var maxMB = (maxBytes / (1024 * 1024)).toFixed(0);

        function reject(message) {
            input.value = '';
            input.classList.add('is-invalid');

            if (window.ARMS && window.ARMS.showToast) {
                window.ARMS.showToast(message, 'error', 6000);
            } else {
                alert(message);
            }
        }

        // Browsers leave type blank for some PDFs, so the extension is the
        // reliable half of the check; the server re-checks the real MIME type.
        if (!/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf')) {
            reject(file.name + ' is not a PDF. Only PDF files can be uploaded.');
            return;
        }

        if (file.size > maxBytes) {
            var fileMB = (file.size / (1024 * 1024)).toFixed(1);
            reject(file.name + ' is ' + fileMB + ' MB. Maximum file size is ' + maxMB + ' MB.');
            return;
        }

        input.classList.remove('is-invalid');
    });

})();
