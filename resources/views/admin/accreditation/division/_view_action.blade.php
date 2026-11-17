{{-- View: the application's details page. Only new OSH Practitioner and
     OSH Consultant applications have one so far; renewals show a dash. --}}
@if($app->isPractitioner() && $app->application_type === 'new')
    <a href="{{ route('admin.accreditation.practitioner.show', $app->id) }}" class="btn btn-primary btn-xs m-0">
        <i class="fas fa-eye me-1"></i>View
    </a>
@else
    <span class="text-muted">—</span>
@endif
