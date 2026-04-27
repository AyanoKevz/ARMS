<!-- HCD Admin Sidebar -->
<li><a href="{{ route('admin.hcd.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>
<li><a href="{{ route('admin.hcd.directory.admins') }}"><i class="fas fa-users-cog"></i> HCD Admin List </a></li>

<li><a><i class="fas fa-folder-plus"></i>New Applications <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu">
        <li><a href="{{ route('admin.hcd.applications.pending') }}"><i class="fas fa-hourglass-half"></i> Pending</a></li>
        <li><a href="{{ route('admin.hcd.applications.under_review') }}"><i class="fas fa-search"></i> Under Review</a></li>
    </ul>
</li>

<li><a><i class="fas fa-sync-alt"></i> Renewal <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu">
        <li><a href="#"><i class="fas fa-hourglass-half"></i> Pending</a></li>
        <li><a href="#"><i class="fas fa-search"></i> Under Review</a></li>
    </ul>
</li>

<li><a><i class="fas fa-calendar-check"></i> Schedule Interviews <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu">
        <li><a href="{{ route('admin.hcd.interviews.pending') }}"><i class="fas fa-clock"></i> Pending to Schedule</a></li>
        <li><a href="{{ route('admin.hcd.interviews.scheduled') }}"><i class="fas fa-calendar-check"></i> Scheduled Interviews</a></li>
    </ul>
</li>
<li><a href="{{ route('admin.hcd.directory.fatpros') }}"><i class="fas fa-certificate"></i> Active FatPro </a></li>
<li><a href="#"><i class="fas fa-ban"></i> Revoked / Expired </a></li>