@extends('layouts.admin')

@section('title', 'Pending Applications')

@section('content')
<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3>Pending Applications</h3>
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
                    <h2>New Applications Awaiting Evaluation</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <style>
                        .table-compact th, .table-compact td {
                            padding: 8px 10px !important;
                            vertical-align: middle !important;
                            font-size: 0.85rem;
                        }
                        .btn-xs {
                            padding: 2px 8px !important;
                            font-size: 0.75rem !important;
                            line-height: 1.5;
                            border-radius: 3px;
                        }
                    </style>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered jambo_table bulk_action table-compact">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking #</th>
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Representative Name</th>
                                    <th class="column-title">Date Submitted</th>
                                    <th class="column-title text-center">Status</th>
                                    <th class="column-title no-link last text-center"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($applications as $app)
                                    @php
                                        $org  = $app->user->organizationProfile;
                                        $rep  = $org?->authorizedRepresentatives?->first();
                                        $isOrg = $app->user->profile_type === 'Organization';
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong>{{ $app->tracking_number }}</strong></td>
                                        <td>
                                            @if($isOrg)
                                                {{ $org->name ?? 'N/A' }}
                                            @else
                                                {{ ($app->user->individualProfile->first_name ?? '') . ' ' . ($app->user->individualProfile->last_name ?? '') }}
                                            @endif
                                        </td>
                                        <td>{{ $rep->full_name ?? '—' }}</td>
                                        <td>{{ $app->created_at->format('M d, Y') }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark">{{ $app->latestStatus?->status?->name ?? 'Pending' }}</span>
                                        </td>
                                        <td class="last text-center">
                                            <form action="{{ route('admin.hcd.applications.update_evaluation', $app->id) }}" method="POST" onsubmit="return confirm('Move this application to Under Evaluation?');">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-xs m-0">
                                                    <i class="fas fa-play me-1"></i> Start Evaluation
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No pending applications found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Pagination Links --}}
                    <div class="d-flex justify-content-end mt-3">
                        {{ $applications->links('pagination::bootstrap-5') }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
