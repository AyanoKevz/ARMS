{{--
    Pending for Interview: applicants approved for interview who have not
    chosen a slot on the tracking page yet. Longest waiting first. Shown on
    Manage Interview Slots.

    Expects $pending (applications with user profiles, latestStatus and
    assignedEvaluator loaded) and $tableId (unique on the page).
--}}
<div class="x_panel">
    <div class="x_title">
        <h2><i class="fas fa-user-clock me-1"></i> Pending for Interview <small>{{ $pending->count() }}</small></h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">
        <p class="prac-admin-hint">
            Approved for interview but no slot chosen yet on the tracking page. Longest waiting first.
        </p>

        <div class="table-responsive">
            <table id="{{ $tableId }}" class="table table-striped table-bordered jambo_table table-compact dynamic-table"
                   data-order='[[3, "asc"]]'>
                <thead>
                    <tr class="headings">
                        <th class="column-title">Tracking No</th>
                        <th class="column-title">Applicant</th>
                        <th class="column-title">Type</th>
                        <th class="column-title">Approved for Interview</th>
                        <th class="column-title text-center">Status</th>
                        <th class="column-title">In-Charge</th>
                        <th class="column-title text-center no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $app)
                        @php $since = $app->latestStatus?->created_at ?? $app->updated_at; @endphp
                        <tr>
                            <td><strong>{{ $app->tracking_number }}</strong></td>
                            <td>{{ $app->user?->name ?? '—' }}</td>
                            <td>{{ \App\Support\AdminAccess::divisionTypeLabel($app->accreditation_type_id) }}</td>
                            <td data-order="{{ $since->format('Y-m-d H:i:s') }}">
                                {{ $since->format('M d, Y') }}
                                <span class="prac-cell-sub">Waiting {{ $since->diffForHumans(null, true) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="prac-status prac-status-pending-for-interview">Pending for Interview</span>
                            </td>
                            <td>{{ $app->assignedEvaluator?->name ?? '—' }}</td>
                            <td class="text-center">
                                @include('admin.accreditation.division._view_action', ['app' => $app])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
