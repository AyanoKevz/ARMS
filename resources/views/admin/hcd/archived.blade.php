@extends('layouts.admin')

@section('title', 'Archived Applications')

@push('styles')
{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
@endpush

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3>Archived Applications</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="x_panel">
                <div class="x_title">
                    <h2>Rejected and Archived Applications</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                <div class="x_content">
                    <div class="table-responsive">
                        <table id="archived_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking No</th>
                                    <th class="column-title">Type</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Date Submitted</th>
                                    <th class="column-title">Date Archived</th>
                                    <th class="column-title text-center">Status</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($applications as $app)
                                    @php
                                        $org         = $app->user->organizationProfile;
                                        $isOrg       = $app->user->profile_type === 'Organization';
                                        $displayName = $isOrg
                                            ? ($org->name ?? 'N/A')
                                            : trim(($app->user->individualProfile->first_name ?? '') . ' ' . ($app->user->individualProfile->last_name ?? ''));
                                        $latestName  = $app->latestStatus?->status?->name;
                                        $isAccredited = (bool) $app->accreditation;
                                        $badgeClass  = match($app->application_type) {
                                            'new'           => 'bg-primary',
                                            'renewal'       => 'bg-success',
                                            'reinstatement' => 'bg-warning text-dark',
                                            default         => 'bg-secondary'
                                        };
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong>{{ $app->tracking_number }}</strong></td>
                                        <td>
                                            <span class="badge {{ $badgeClass }}">
                                                {{ ucfirst($app->application_type) }}
                                            </span>
                                        </td>
                                        <td>{{ $displayName }}</td>
                                        <td data-order="{{ $app->created_at->format('Y-m-d') }}">{{ $app->created_at->format('M d, Y') }}</td>
                                        <td data-order="{{ $app->updated_at->format('Y-m-d') }}">{{ $app->updated_at->format('M d, Y') }}</td>
                                        <td class="text-center">
                                            @if($isAccredited || $latestName === 'Approved')
                                                <span class="badge bg-success text-white">Approved</span>
                                            @elseif($latestName === 'Rejected')
                                                <span class="badge bg-danger text-white">Rejected</span>
                                            @else
                                                <span class="badge bg-secondary text-white">{{ $latestName ?? 'Archived' }}</span>
                                            @endif
                                        </td>
                                        <td class="last text-center">
                                            <div class="d-inline-flex gap-1 align-items-center justify-content-center">
                                                <a href="{{ route('admin.hcd.applications.show', $app->id) }}" class="btn btn-info btn-xs m-0 fw-bold">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                                {{-- Delete trigger — passes all data via attributes; modal lives outside the table --}}
                                                <button type="button"
                                                    class="btn btn-danger btn-xs m-0 fw-bold js-delete-trigger"
                                                    data-tracking="{{ $app->tracking_number }}"
                                                    data-name="{{ $displayName }}"
                                                    data-email="{{ $app->user->email ?? 'N/A' }}"
                                                    data-action="{{ route('admin.hcd.applications.destroy', $app->id) }}"
                                                    title="Delete permanently">
                                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══ Shared Delete Confirmation Modal — placed OUTSIDE the table to avoid overflow clipping ══ --}}
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.25);">

            <div class="modal-header border-0" style="background:linear-gradient(135deg,#dc2626,#991b1b);padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-trash-alt text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="deleteConfirmModalLabel">Permanently Delete</h5>
                        <small class="text-white-50" id="delete-modal-tracking"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" style="background:#fafafa;">
                <div class="d-flex align-items-center gap-3 mb-3 p-3" style="background:#fff;border-radius:10px;border:1px solid #fecaca;">
                    <i class="fas fa-user-circle fs-2 text-danger flex-shrink-0"></i>
                    <div>
                        <div class="fw-bold" id="delete-modal-name" style="color:#2A3F54;font-size:.95rem;"></div>
                        <small class="text-muted" id="delete-modal-email"></small>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 mb-1" style="background:#fef2f2;border-radius:8px;border-left:4px solid #ef4444;">
                    <i class="fas fa-exclamation-triangle text-danger mt-1 flex-shrink-0"></i>
                    <small class="text-dark">
                        <strong>This action cannot be undone.</strong><br>
                        Permanently deletes tracking <strong id="delete-modal-tracking-inline"></strong> and all associated applicant data, documents, and records.
                    </small>
                </div>
            </div>

            <div class="modal-footer border-0" style="background:#fafafa;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <form id="delete-confirm-form" method="POST" class="d-inline modal-submit-loading">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger fw-bold text-white px-4" style="border-radius:8px;">
                        <span class="btn-text"><i class="fas fa-trash-alt me-2"></i>Yes, Delete Permanently</span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span> Deleting...
                        </span>
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- jQuery (required by DataTables) --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

{{-- DataTables Core --}}
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

{{-- DataTables Extensions --}}
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

{{-- Reusable Table Component JS --}}
<script src="{{ asset('js/table-component.js') }}"></script>
<script>
// ── Populate shared delete modal from trigger button's data attributes ──────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-delete-trigger');
    if (!btn) return;

    const tracking = btn.dataset.tracking;

    document.getElementById('delete-modal-tracking').textContent         = tracking;
    document.getElementById('delete-modal-tracking-inline').textContent  = '#' + tracking;
    document.getElementById('delete-modal-name').textContent             = btn.dataset.name;
    document.getElementById('delete-modal-email').textContent            = btn.dataset.email;
    document.getElementById('delete-confirm-form').action                = btn.dataset.action;

    // Reset spinner state in case the modal was opened before and submitted
    const form        = document.getElementById('delete-confirm-form');
    const submitBtn   = form.querySelector('button[type="submit"]');
    const textSpan    = submitBtn.querySelector('.btn-text');
    const spinnerSpan = submitBtn.querySelector('.btn-spinner');
    if (textSpan)    textSpan.classList.remove('d-none');
    if (spinnerSpan) spinnerSpan.classList.add('d-none');
    submitBtn.disabled            = false;
    submitBtn.style.pointerEvents = '';
    submitBtn.style.opacity       = '';
    submitBtn.style.cursor        = '';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteConfirmModal')).show();
});

// ── Loading spinner on modal form submit ─────────────────────────────────────
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form.classList.contains('modal-submit-loading')) return;
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    const textSpan    = btn.querySelector('.btn-text');
    const spinnerSpan = btn.querySelector('.btn-spinner');
    if (textSpan)    textSpan.classList.add('d-none');
    if (spinnerSpan) spinnerSpan.classList.remove('d-none');
    btn.style.pointerEvents = 'none';
    btn.style.opacity       = '0.75';
    btn.style.cursor        = 'not-allowed';
    setTimeout(() => { btn.disabled = true; }, 0);
});
</script>
@endpush
