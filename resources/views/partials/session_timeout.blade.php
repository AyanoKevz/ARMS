{{--
    Idle Session Warning
    ────────────────────
    Included from layouts/portal.blade.php for authenticated users only.

    Laravel expires a session after config('session.lifetime') minutes of no
    requests. Without this, the first thing an idle user sees is a bounce to the
    login page, mid-task, with unsaved form input gone. This warns them first and
    lets them extend the session.

    Config travels on the root element's data attributes; the behaviour lives in
    public/js/session-timeout.js.
--}}
<div id="arms-idle-warning"
     data-lifetime-seconds="{{ (int) config('session.lifetime') * 60 }}"
     data-warn-seconds="120"
     data-keepalive-url="{{ route('session.keep_alive') }}"
     hidden>

    <div class="arms-idle-backdrop"></div>

    <div class="arms-idle-dialog" role="alertdialog" aria-modal="true"
         aria-labelledby="arms-idle-title" aria-describedby="arms-idle-desc">
        <div class="arms-idle-head">
            <i class="bi bi-clock-history"></i>
            <span id="arms-idle-title">Still there?</span>
        </div>

        <div class="arms-idle-body">
            <p id="arms-idle-desc" class="mb-2">
                You have been inactive for a while. For security, you will be signed
                out automatically and any unsaved changes on this page will be lost.
            </p>
            <div class="arms-idle-count">
                Signing out in <span id="arms-idle-remaining">2:00</span>
            </div>
        </div>

        <div class="arms-idle-foot">
            <button type="button" id="arms-idle-logout" class="arms-idle-btn arms-idle-btn-ghost">
                Sign out now
            </button>
            <button type="button" id="arms-idle-stay" class="arms-idle-btn arms-idle-btn-primary">
                <i class="bi bi-check2-circle me-1"></i>Stay signed in
            </button>
        </div>
    </div>
</div>

{{-- Submitted by the script when the countdown runs out or "Sign out now" is clicked. --}}
<form id="arms-idle-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>

<style>
    #arms-idle-warning[hidden] { display: none !important; }

    #arms-idle-warning {
        position: fixed;
        inset: 0;
        z-index: 20000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .arms-idle-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(9, 30, 62, .72);
    }

    .arms-idle-dialog {
        position: relative;
        width: 100%;
        max-width: 420px;
        background: #fff;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 18px 50px rgba(0, 0, 0, .35);
        animation: armsIdleIn .18s ease-out;
    }

    @keyframes armsIdleIn {
        from { opacity: 0; transform: translateY(8px) scale(.98); }
        to   { opacity: 1; transform: none; }
    }

    .arms-idle-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        background: linear-gradient(135deg, #1A4A8A, #0D2B55);
        color: #fff;
        font-weight: 700;
        font-size: 1rem;
    }

    .arms-idle-head i { font-size: 1.15rem; }

    .arms-idle-body {
        padding: 18px 20px 6px;
        color: #2A3F54;
        font-size: .9rem;
        line-height: 1.5;
    }

    .arms-idle-count {
        font-size: .85rem;
        font-weight: 600;
        color: #842029;
        background: #fff5f5;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        padding: 8px 12px;
        text-align: center;
    }

    #arms-idle-remaining {
        font-variant-numeric: tabular-nums;
        font-size: 1rem;
    }

    .arms-idle-foot {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 16px 20px 18px;
    }

    .arms-idle-btn {
        border-radius: 8px;
        border: 1px solid transparent;
        padding: 8px 16px;
        font-size: .87rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }

    .arms-idle-btn-ghost {
        background: #fff;
        border-color: #ced4da;
        color: #6c757d;
    }

    .arms-idle-btn-ghost:hover { background: #f1f3f5; color: #2A3F54; }

    .arms-idle-btn-primary {
        background: #1A4A8A;
        color: #fff;
    }

    .arms-idle-btn-primary:hover { background: #14396b; }
</style>
