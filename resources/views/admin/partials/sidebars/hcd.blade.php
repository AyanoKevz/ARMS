<!-- HCD Admin Sidebar -->
<li><a href="#"><i class="fas fa-user-circle"></i> Profile </a></li>
<li><a href="#"><i class="fas fa-users-cog"></i> HCD Admin List </a></li>
<li><a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>

<li><a><i class="fas fa-folder-plus"></i>New Applications <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu">
        <li><a href="{{ route('admin.hcd.applications.pending') }}">Pending</a></li>
        <li><a href="#">Under Review</a></li>
    </ul>
</li>

<li><a><i class="fas fa-sync-alt"></i> Renewal <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu">
        <li><a href="#">Pending</a></li>
        <li><a href="#">Under Review</a></li>
    </ul>
</li>

<li><a href="#"><i class="fas fa-calendar-check"></i> Schedule Interviews </a></li>

<li><a href="#"><i class="fas fa-certificate"></i> Active FatPro </a></li>