@extends('layouts.admin')

@section('title', 'HCD Admin List')

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
            <h3>HCD Admin List</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2>Administrators in your Division</h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <li>
                            <button type="button" class="btn btn-primary btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#createAdminModal" style="background-color: #1a2e5a; border-color: #1a2e5a;">
                                <i class="fas fa-plus me-1"></i> Create Admin
                            </button>
                        </li>
                        <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                
                <div class="x_content">
                    <div class="table-responsive">
                        <table id="admins_table" class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table" style="width:100%">
                            <thead>
                                <tr class="headings">
                                    <th class="column-title">Name</th>
                                    <th class="column-title">Position</th>
                                    <th class="column-title">Division</th>
                                    <th class="column-title">Email</th>
                                    <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($admins as $adminUser)
                                    @php
                                        $profile = $adminUser->adminProfile;
                                    @endphp
                                    <tr class="even pointer">
                                        <td><strong>{{ $adminUser->name }}</strong></td>
                                        <td>{{ $profile->position ?? '—' }}</td>
                                        <td>{{ $profile->division->name ?? '—' }}</td>
                                        <td>{{ $adminUser->email }}</td>
                                        <td class="last text-center">
                                            <a href="{{ route('profile.show', $adminUser->id) }}" class="btn btn-info btn-xs m-0">
                                                <i class="fas fa-eye me-1"></i> View Profile
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

<!-- Create Admin Modal -->
<div class="modal fade" id="createAdminModal" tabindex="-1" aria-labelledby="createAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a2e5a 0%, #0d1f42 100%); color: white; border-bottom: none;">
                <h5 class="modal-title" id="createAdminModalLabel">Create New Admin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createAdminForm">
                @csrf
                <div class="modal-body">
                    <div id="createAdminAlert"></div>
                    
                    <div class="mb-3">
                        <label for="admin_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="admin_email" name="email" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="admin_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="admin_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="admin_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="admin_last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="admin_position" class="form-label">Position</label>
                        <input type="text" class="form-control" id="admin_position" name="position">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitAdmin" style="background-color: #D4AC4B; border-color: #D4AC4B; color: white; font-weight: bold;">
                        <span class="spinner-border spinner-border-sm d-none" id="adminSpinner" role="status" aria-hidden="true"></span>
                        Send Invitation
                    </button>
                </div>
            </form>
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
    $('#createAdminForm').on('submit', function(e) {
        e.preventDefault();
        
        let $form = $(this);
        let $btn = $('#btnSubmitAdmin');
        let $spinner = $('#adminSpinner');
        let $alert = $('#createAdminAlert');
        
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $alert.html('').removeClass('alert alert-danger alert-success');
        
        $.ajax({
            url: '{{ route('admin.hcd.directory.admins.invite') }}',
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                $alert.addClass('alert alert-success').html(response.message);
                $form[0].reset();
                setTimeout(function() {
                    $('#createAdminModal').modal('hide');
                    $alert.html('').removeClass('alert alert-success');
                }, 2000);
            },
            error: function(xhr) {
                let errorMsg = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMsg = Object.values(xhr.responseJSON.errors)[0][0];
                }
                $alert.addClass('alert alert-danger').html(errorMsg);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
            }
        });
    });
});
</script>
@endpush
