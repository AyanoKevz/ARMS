@extends('layouts.admin')

@section('title', 'Revoked & Expired FATPro')

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
            <h3>Revoked & Expired FATPro Registry</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2>First Aid Training Providers</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    {{-- Status Filter (Moved to DataTables Toolbar via JS) --}}
                    <div id="statusFilterContainer" class="d-none">
                        <form action="{{ route('admin.hcd.directory.fatpros.inactive') }}" method="GET" class="d-flex align-items-center">
                            <label for="status" class="me-2 fw-bold" style="font-size: 0.85rem; margin-bottom: 0;">Status:</label>
                            <select name="status" id="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto; padding: 4px 30px 4px 10px; height: 33px;">
                                <option value="" {{ request('status') === '' ? 'selected' : '' }}>All</option>
                                <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>Revoked</option>
                                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                            </select>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table id="inactive_fatpro_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">FATPro Name</th>
                                    <th class="column-title">Head Name</th>
                                    <th class="column-title">Organization Email</th>
                                    <th class="column-title text-center">Status</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($accreditations as $acc)
                                    @php
                                        $user = $acc->user;
                                        $org = $user->organizationProfile;
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong>{{ $user->name }}</strong></td>
                                        <td>{{ $org->head_name ?? '—' }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td class="text-center">
                                            @if($acc->status === 'revoked')
                                                <span class="badge bg-danger text-white">Revoked</span>
                                            @elseif($acc->status === 'expired')
                                                <span class="badge bg-warning text-dark">Expired</span>
                                            @else
                                                <span class="badge bg-secondary text-white">{{ ucfirst($acc->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="last text-center" style="white-space:nowrap;">
                                            <a href="{{ route('admin.hcd.applications.show', $acc->application_id) }}"
                                               class="btn btn-info btn-xs m-0"
                                               title="View Application">
                                                View
                                            </a>
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
$(document).ready(function() {
    // Wait for DataTables to initialize (which happens in table-component.js)
    // Then move our status filter into the toolbar
    setTimeout(function() {
        const filterContainer = $('#statusFilterContainer');
        if (filterContainer.length && $('.dt-toolbar-left').length) {
            filterContainer.removeClass('d-none').detach().appendTo('.dt-toolbar-left');
        }
    }, 100); // Small delay to ensure DT has finished its layout
});
</script>
@endpush
