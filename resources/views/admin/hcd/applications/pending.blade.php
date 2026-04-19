@extends('admin.layouts.app')

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
                    <div class="table-responsive">
                        <table class="table table-striped jambo_table bulk_action">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Tracking Number </th>
                                    <th class="column-title">Applicant Type </th>
                                    <th class="column-title">Date Submitted </th>
                                    <th class="column-title">Status </th>
                                    <th class="column-title no-link last"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($applications as $app)
                                    <tr class="even pointer">
                                        <td class=" ">{{ $app->tracking_number }}</td>
                                        <td class=" ">{{ $app->user->profile_type ?? 'Unknown' }}</td>
                                        <td class=" ">{{ $app->created_at->format('M d, Y') }}</td>
                                        <td class=" ">
                                            <span class="badge bg-warning text-dark">{{ $app->status }}</span>
                                        </td>
                                        <td class=" last">
                                            <form action="{{ route('admin.hcd.applications.update_evaluation', $app->id) }}" method="POST" onsubmit="return confirm('Update this application to Under Evaluation?');">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm m-0">
                                                    Update to Evaluated
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
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
