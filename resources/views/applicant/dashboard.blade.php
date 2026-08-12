@extends('layouts.applicant')

@section('title', 'Applicant Dashboard')

@push('styles')
{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
<style>
    .instructor-acc-card {
        border: 1px solid #e0e6ed;
        border-radius: 8px;
        margin-bottom: 1rem;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .instructor-acc-header {
        background: #f8fafc;
        border-bottom: 1px solid #e0e6ed;
        padding: 12px 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        user-select: none;
        transition: background-color 0.2s ease;
    }
    .instructor-acc-header:hover {
        background: #f1f5f9;
    }
    .instructor-acc-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1a3353;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
</style>
@endpush

@section('content')
@php
$myAccreditation = $myAccreditation ?? \App\Models\Accreditation::where('user_id', auth()->id())
    ->whereIn('status', ['active', 'expired', 'revoked'])
    ->orderBy('id', 'desc')
    ->first();

$instructors = $instructors ?? \App\Models\Instructor::where('user_id', auth()->id())
    ->with('credentials')
    ->orderBy('id', 'desc')
    ->get()
    ->unique(function ($item) {
        return strtolower(trim($item->first_name) . '|' . trim($item->middle_name) . '|' . trim($item->last_name));
    })
    ->sortBy('last_name')
    ->values();
@endphp

<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3>Applicant Dashboard</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">

        {{-- Accreditation Summary --}}
        @if($myAccreditation)
        <div class="col-md-12 col-sm-12">
            <div class="x_panel" style="border-left: 4px solid var(--portal-gold); border-top: none;">
                <div class="x_title border-0 mb-0 pb-0">
                    <h2 class="fw-bold" style="color: #2A3F54;"><i class="fas fa-award text-warning me-2"></i> Accreditation Summary</h2>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content mt-2">
                    <div class="row text-center text-md-start">
                        <div class="col-md mb-2 mb-md-0 border-end">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Accreditation Number</p>
                            <p class="fw-bold fs-5 mb-0" style="color: #0b3d91;">{{ $myAccreditation->accreditation_number ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md mb-2 mb-md-0 border-end">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Date Accredited</p>
                            <p class="fw-bold fs-5 mb-0" style="color: #2A3F54;">
                                {{ $myAccreditation->date_of_accreditation ? \Carbon\Carbon::parse($myAccreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}
                            </p>
                        </div>
                        <div class="col-md mb-2 mb-md-0 border-end">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Validity Period</p>
                            <p class="fw-bold fs-5 mb-0" style="color: #2A3F54;">
                                {{ $myAccreditation->validity_date ? \Carbon\Carbon::parse($myAccreditation->validity_date)->format('F d, Y') : 'N/A' }}
                            </p>
                        </div>
                        @if($myAccreditation->status === 'revoked')
                        <div class="col-md mb-2 mb-md-0 border-end">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Revoked Date</p>
                            <p class="fw-bold fs-5 mb-0 text-danger">
                                {{ $myAccreditation->updated_at ? $myAccreditation->updated_at->format('F d, Y') : 'N/A' }}
                            </p>
                        </div>
                        @endif
                        <div class="col-md">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Status</p>
                            <p class="mb-0 mt-1">
                                @if($myAccreditation->status === 'active')
                                <span class="badge bg-success" style="font-size: 0.9rem; padding: 6px 12px;">Active</span>
                                @elseif($myAccreditation->status === 'expired')
                                <span class="badge bg-warning text-dark" style="font-size: 0.9rem; padding: 6px 12px;">Expired</span>
                                @elseif($myAccreditation->status === 'revoked')
                                <span class="badge bg-danger" style="font-size: 0.9rem; padding: 6px 12px;">Revoked</span>
                                @else
                                <span class="badge bg-secondary" style="font-size: 0.9rem; padding: 6px 12px;">{{ ucfirst($myAccreditation->status) }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- FATPRO Instructors & Credentials --}}
        @if($instructors->isNotEmpty())
        <div class="col-md-12 col-sm-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2><i class="fas fa-chalkboard-teacher me-2" style="color: var(--portal-gold, #d4ac4b);"></i> Instructors &amp; Credentials</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <div class="accordion" id="instructorsDashboardAccordion">
                        @foreach($instructors as $index => $instructor)
                        <div class="instructor-acc-card">
                            <div class="instructor-acc-header" data-bs-toggle="collapse" data-bs-target="#inst-body-{{ $instructor->id }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}">
                                <div class="instructor-acc-title">
                                    <i class="fas fa-user text-secondary me-1"></i>
                                    <span>{{ $instructor->last_name }}, {{ $instructor->first_name }} {{ $instructor->middle_name }}</span>
                                    @php
                                        $saClass = match($instructor->status) {
                                            'approved' => 'bg-success',
                                            'returned' => 'bg-warning text-dark',
                                            'rejected' => 'bg-danger',
                                            default    => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $saClass }} ms-2" style="font-size: 0.75rem;">{{ ucfirst($instructor->status) }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
                                    <a href="{{ route('applicant.instructors.show', $instructor->id) }}" class="btn btn-primary btn-xs m-0 px-2 py-1" title="Go to Instructor Page">
                                        <i class="fas fa-eye me-1"></i> View Instructor
                                    </a>
                                    <i class="bi {{ $index === 0 ? 'bi-chevron-up' : 'bi-chevron-down' }} ms-2 text-muted" id="inst-chevron-{{ $instructor->id }}" data-bs-toggle="collapse" data-bs-target="#inst-body-{{ $instructor->id }}" style="cursor: pointer;"></i>
                                </div>
                            </div>

                            <div id="inst-body-{{ $instructor->id }}" class="collapse {{ $index === 0 ? 'show' : '' }}" data-bs-parent="#instructorsDashboardAccordion">
                                <div class="p-3">
                                    <div class="table-responsive">
                                         <table id="instructor-cred-table-{{ $instructor->id }}" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%" data-order="[]">
                                             <thead>
                                                 <tr class="headings">
                                                     <th class="column-title">Credential / Certificate Type</th>
                                                     <th class="column-title">Certificate Number</th>
                                                     <th class="column-title text-center">Issued On</th>
                                                     <th class="column-title text-center">Valid Until</th>
                                                     <th class="column-title">Training Date(s)</th>
                                                 </tr>
                                             </thead>
                                             <tbody>
                                                 @php
                                                     $sortedDashCredentials = $instructor->credentials->sortBy(function($c) {
                                                         return match($c->type) {
                                                             'NTTC'  => 1,
                                                             'TM1'   => 2,
                                                             'EMS'   => 3,
                                                             'BOSH'  => 99,
                                                             default => 50,
                                                         };
                                                     });
                                                 @endphp
                                                 @forelse($sortedDashCredentials as $credItem)
                                                 <tr class="even pointer">
                                                     <td><strong>
                                                         @if($credItem->type === 'EMS') TESDA Emergency Medical Services NC II or III Certificate
                                                         @elseif($credItem->type === 'TM1') TESDA Trainers Methodology Certificate 1
                                                         @elseif($credItem->type === 'NTTC') TESDA National TVET Trainer Certificate
                                                         @elseif($credItem->type === 'BOSH') BOSH SO1 or SO2 Certificate
                                                         @else {{ $credItem->type }} Credential
                                                         @endif
                                                     </strong></td>
                                                     <td>{{ $credItem->number ?? '—' }}</td>
                                                     <td class="text-center">{{ $credItem->issued_date ? \Carbon\Carbon::parse($credItem->issued_date)->format('M d, Y') : '—' }}</td>
                                                     <td class="text-center">
                                                         @if($credItem->validity_date)
                                                             @php
                                                                 $isPast = \Carbon\Carbon::parse($credItem->validity_date)->endOfDay()->isPast();
                                                             @endphp
                                                             <div>{{ \Carbon\Carbon::parse($credItem->validity_date)->format('M d, Y') }}</div>
                                                             @if($isPast)
                                                                 <span class="badge bg-danger text-white mt-1" style="font-size:.72rem; padding: 3px 8px; border-radius: 4px;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Expired</span>
                                                             @else
                                                                 <span class="badge bg-success text-white mt-1" style="font-size:.72rem; padding: 3px 8px; border-radius: 4px;"><i class="bi bi-check-circle-fill me-1"></i>Valid</span>
                                                             @endif
                                                         @else
                                                             —
                                                         @endif
                                                     </td>
                                                     <td>{{ $credItem->training_dates ?? '—' }}</td>
                                                 </tr>
                                                 @empty
                                                 <tr>
                                                     <td colspan="5" class="text-center text-muted py-3">No credentials recorded for this instructor.</td>
                                                 </tr>
                                                 @endforelse
                                             </tbody>
                                         </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script src="{{ asset('js/table-component.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adjust dynamic tables and toggle chevrons on collapse show/hide
        document.querySelectorAll('[id^="inst-body-"]').forEach(function(body) {
            const instId = body.id.replace('inst-body-', '');
            const chevron = document.getElementById('inst-chevron-' + instId);
            
            body.addEventListener('show.bs.collapse', function() {
                if (chevron) {
                    chevron.classList.remove('bi-chevron-down');
                    chevron.classList.add('bi-chevron-up');
                }
                setTimeout(function() {
                    if (window.jQuery && jQuery.fn.dataTable) {
                        jQuery(jQuery.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
                    }
                }, 150);
            });
            body.addEventListener('hide.bs.collapse', function() {
                if (chevron) {
                    chevron.classList.remove('bi-chevron-up');
                    chevron.classList.add('bi-chevron-down');
                }
            });
        });
    });
</script>
@endpush