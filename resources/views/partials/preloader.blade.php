<div id="arms-page-preloader">
    <div class="arms-loader-card">
        <img src="{{ asset('images/oshc-logo.png') }}" class="arms-loader-logo" alt="OSHC Logo">
        <div class="arms-loader-spinner">
            <div class="loader"><div class="dot"></div></div>
            <div class="loader"><div class="dot"></div></div>
            <div class="loader"><div class="dot"></div></div>
            <div class="loader"><div class="dot"></div></div>
            <div class="loader"><div class="dot"></div></div>
            <div class="loader"><div class="dot"></div></div>
        </div>
        <div class="arms-loader-text">Loading...</div>
    </div>
</div>

<style>
/* ══ ARMS PAGE PRELOADER ══ */
#arms-page-preloader {
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

#arms-page-preloader.fade-out {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.arms-loader-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 260px;
    height: 260px;
}

.arms-loader-logo {
    height: 44px;
    width: auto;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 5;
    animation: logoPulse 3.0s ease-in-out infinite alternate;
}

@keyframes logoPulse {
    0% { 
        transform: translate(-50%, -50%) scale(0.88); 
        opacity: 0.88; 
        filter: drop-shadow(0 2px 6px rgba(0, 102, 204, 0.15));
    }
    50% {
        transform: translate(-50%, -50%) scale(1.06); 
        opacity: 1; 
        filter: drop-shadow(0 0 18px rgba(38, 143, 228, 0.5));
    }
    100% { 
        transform: translate(-50%, -50%) scale(0.92); 
        opacity: 0.92; 
        filter: drop-shadow(0 4px 10px rgba(0, 102, 204, 0.25));
    }
}

.arms-loader-spinner {
    position: relative;
    width: 180px;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Uiverse.io by mrhyddenn animation - Vibrant Blue Loaders */
.arms-loader-spinner .loader {
    height: 5px;
    width: 1px;
    position: absolute;
    animation: rotate0234 4.5s linear infinite;
}

.arms-loader-spinner .loader .dot {
    top: 80px;
    height: 9px;
    width: 9px;
    background: #268fe4;
    box-shadow: 0 0 10px rgba(38, 143, 228, 0.8), 0 0 16px rgba(0, 102, 204, 0.4);
    border-radius: 50%;
    position: relative;
}

.arms-loader-text {
    position: absolute;
    bottom: -55px;
    font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: #0b3d91;
}

@keyframes rotate0234 {
    30% {
        transform: rotate(220deg);
    }
    40% {
        transform: rotate(450deg);
        opacity: 1;
    }
    75% {
        transform: rotate(720deg);
        opacity: 1;
    }
    76% {
        opacity: 0;
    }
    100% {
        opacity: 0;
        transform: rotate(0deg);
    }
}

.arms-loader-spinner .loader:nth-child(1) { animation-delay: 0.15s; }
.arms-loader-spinner .loader:nth-child(2) { animation-delay: 0.3s; }
.arms-loader-spinner .loader:nth-child(3) { animation-delay: 0.45s; }
.arms-loader-spinner .loader:nth-child(4) { animation-delay: 0.6s; }
.arms-loader-spinner .loader:nth-child(5) { animation-delay: 0.75s; }
.arms-loader-spinner .loader:nth-child(6) { animation-delay: 0.9s; }
</style>

<script>
(function() {
    const preloader = document.getElementById('arms-page-preloader');
    
    function showPreloader() {
        if (preloader) {
            preloader.style.display = 'flex';
            preloader.classList.remove('fade-out');
        }
    }

    function hidePreloader() {
        if (preloader && !preloader.classList.contains('fade-out')) {
            preloader.classList.add('fade-out');
            setTimeout(function() {
                if (preloader.classList.contains('fade-out')) {
                    preloader.style.display = 'none';
                }
            }, 350);
        }
    }

    // Initial page load completion
    if (document.readyState === 'complete') {
        hidePreloader();
    } else {
        window.addEventListener('load', hidePreloader);
        setTimeout(hidePreloader, 2000);
    }

    // Handle browser back/forward buttons
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            hidePreloader();
        }
    });

    // Show preloader when clicking internal navigation links
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && link.href && !link.target && !link.hasAttribute('download')) {
            const url = new URL(link.href, window.location.href);
            // Only trigger for same-domain page navigations
            if (url.origin === window.location.origin && 
                url.pathname !== window.location.pathname &&
                !url.hash.startsWith('#') && 
                !link.getAttribute('href').startsWith('#') &&
                !link.getAttribute('href').startsWith('javascript:')) {
                showPreloader();
            }
        }
    });

    // Show preloader when submitting forms
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form && !form.target && form.checkValidity && form.checkValidity()) {
            showPreloader();
        }
    });
})();
</script>
