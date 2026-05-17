 @extends('layouts.admin')

 @section('title', 'List of Scheduled Interviews')

 @push('styles')
 {{-- DataTables CSS --}}
 <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
 <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
 <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
 <link rel="stylesheet" href="{{ asset('css/table-component.css') }}">
 <style>
     /* Ensure FullCalendar event titles wrap and are fully seeable */
     #interview_calendar .fc-event,
     #interview_calendar .fc-event-main,
     #interview_calendar .fc-event-main-frame,
     #interview_calendar .fc-event-title-container,
     #interview_calendar .fc-event-title {
         white-space: normal !important;
         word-break: break-word !important;
         overflow: visible !important;
     }
     #interview_calendar .fc-event {
         padding: 3px 6px !important;
         font-size: 0.82rem !important;
         border-radius: 5px !important;
         cursor: pointer;
         line-height: 1.3 !important;
         margin: 2px 0 !important;
         box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
         transition: transform 0.15s ease, box-shadow 0.15s ease !important;
         width: fit-content !important;
         display: inline-block !important;
     }
     #interview_calendar .fc-event:hover {
         transform: translateY(-1px);
         box-shadow: 0 3px 6px rgba(0,0,0,0.15) !important;
     }
     #interview_calendar .fc-event-title {
         font-weight: 600 !important;
         color: #ffffff !important;
     }
     #interview_calendar .fc-event-time {
         font-weight: bold !important;
         color: #e2e8f0 !important;
         font-size: 0.75rem !important;
         display: inline-block !important;
         margin-right: 6px !important;
         text-transform: uppercase !important;
     }
     .fc-theme-bootstrap5 a {
         text-decoration: none !important;
     }
 </style>
 @endpush

 @section('content')
 <div class="">
     <div class="page-title">
         <div class="title_left">
             <h3>List of Scheduled Interviews</h3>
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
                     <h2>
                         Applicants with Set Interview Schedules
                     </h2>
                     <ul class="nav navbar-right panel_toolbox">
                         <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                     </ul>
                     <div class="clearfix"></div>
                 </div>

                 <div class="x_content">
                     <div class="table-responsive">
                         <table id="scheduled_interviews_table"
                             class="table table-striped table-bordered jambo_table bulk_action table-compact dynamic-table"
                             data-date-index="4"
                             style="width:100%">
                             <thead>
                                 <tr class="headings">
                                     <th class="column-title">Tracking No</th>
                                     <th class="column-title">Type</th>
                                     <th class="column-title">FATPro Name</th>
                                     <th class="column-title">Organization Email</th>
                                     <th class="column-title text-center">Interview Date</th>
                                     <th class="column-title text-center">Interview Time</th>
                                     <th class="column-title text-center">Mode</th>
                                     <th class="column-title">Venue</th>
                                     <th class="column-title no-link last text-center no-sort"><span class="nobr">Action</span></th>
                                 </tr>
                             </thead>

                             <tbody>
                                 @foreach($applications as $app)
                                 @php
                                 $org = $app->user->organizationProfile;
                                 $isOrg = $app->user->profile_type === 'Organization';
                                 $ind = $app->user->individualProfile;
                                 $schedule = $app->interview;
                                 @endphp
                                 <tr class="even pointer">
                                     <td><strong>{{ $app->tracking_number }}</strong></td>
                                     <td>
                                         @php
                                         $badgeClass = match($app->application_type) {
                                         'new' => 'bg-primary',
                                         'renewal' => 'bg-success',
                                         'reinstatement' => 'bg-warning text-dark',
                                         default => 'bg-secondary'
                                         };
                                         @endphp
                                         <span class="badge {{ $badgeClass }}">
                                             {{ ucfirst($app->application_type) }}
                                         </span>
                                     </td>
                                     <td>
                                         @if($isOrg && $org)
                                         {{ $org->name ?? 'N/A' }}
                                         @else
                                         {{ trim(($ind->first_name ?? '') . ' ' . ($ind->last_name ?? '')) ?: 'N/A' }}
                                         @endif
                                     </td>
                                     <td>{{ $isOrg && $org ? ($org->email ?? '—') : ($app->user->email ?? '—') }}</td>
                                     <td class="text-center">
                                         {{ $schedule?->interview_date?->format('M d, Y') ?? '—' }}
                                     </td>
                                     <td class="text-center">
                                         @if($schedule?->interview_time)
                                         {{ \Carbon\Carbon::parse($schedule->interview_time)->format('h:i A') }}
                                         @else
                                         —
                                         @endif
                                     </td>
                                     <td class="text-center">
                                         @if($schedule?->mode)
                                         <span class="badge {{ $schedule->mode === 'online' ? 'bg-info' : 'bg-secondary' }} text-white">
                                             {{ strtoupper($schedule->mode) }}
                                         </span>
                                         @else
                                         —
                                         @endif
                                     </td>
                                     <td>{{ $schedule?->venue ?? '—' }}</td>
                                     <td class="last text-center">
                                         <a href="{{ route('admin.hcd.applications.show', $app->id) }}"
                                             class="btn btn-info btn-xs m-0 fw-bold">
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

             <!-- Calendar Section -->
             <div class="x_panel mt-4">
                 <div class="x_title">
                     <h2>Interview Schedule Calendar</h2>
                     <ul class="nav navbar-right panel_toolbox">
                         <li><a class="collapse-link"><i class="fas fa-chevron-up"></i></a></li>
                     </ul>
                     <div class="clearfix"></div>
                 </div>
                 <div class="x_content">
                     <div id="interview_calendar"></div>
                 </div>
             </div>

         </div>
     </div>
 </div>
 @endsection

 @push('scripts')
 <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
 <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
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
         var calendarEl = document.getElementById('interview_calendar');

         var events = [
            @foreach($applications as $app)
            @php
            $schedule = $app->interview;
            $org = $app->user->organizationProfile;
            $isOrg = $app->user->profile_type === 'Organization';
            $ind = $app->user->individualProfile;
            $name = '';
            if ($isOrg && $org) {
                $name = $org->name;
            } else {
                $name = trim(($ind->first_name ?? '') . ' ' . ($ind->last_name ?? ''));
            }
            @endphp
            @if($schedule && $schedule->interview_date)
            {
                title: '{{ $schedule->interview_time ? "" : $name . " (" . $app->tracking_number . ")" }}',
                start: '{{ \Carbon\Carbon::parse($schedule->interview_date)->format("Y-m-d") }}T{{ $schedule->interview_time ? \Carbon\Carbon::parse($schedule->interview_time)->format("H:i:00") : "00:00:00" }}',
                url: '{{ route("admin.hcd.applications.show", $app->id) }}',
                allDay: {{ $schedule->interview_time ? 'false' : 'true' }},
                display: 'block',
                backgroundColor: '#0b3d91',
                borderColor: '#082d6b',
                textColor: '#ffffff',
                extendedProps: {
                    fullTitle: '{{ addslashes($name) }} ({{ $app->tracking_number }})'
                }
            },
            @endif
             @endforeach
         ];

         var calendar = new FullCalendar.Calendar(calendarEl, {
             initialView: 'dayGridMonth',
             eventDisplay: 'block',
             eventTimeFormat: {
                 hour: '2-digit',
                 minute: '2-digit',
                 meridiem: 'short'
             },
             headerToolbar: {
                 left: 'prev,next today',
                 center: 'title',
                 right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
             },
             themeSystem: 'bootstrap5',
             events: events,
             height: 'auto',
             eventDidMount: function(info) {
                 var fullTitle = info.event.extendedProps.fullTitle;
                 var timeText = info.timeText ? info.timeText + ' - ' : '';
                 
                 // Initialize Bootstrap Tooltip on hover
                 if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                     new bootstrap.Tooltip(info.el, {
                         title: timeText + fullTitle,
                         placement: 'top',
                         trigger: 'hover',
                         container: 'body'
                     });
                 } else {
                     info.el.title = timeText + fullTitle; // Fallback
                 }
             },
             eventClick: function(info) {
                 if (info.event.url) {
                     window.location.href = info.event.url;
                     info.jsEvent.preventDefault();
                 }
             }
         });
         calendar.render();
     });
 </script>
 @endpush