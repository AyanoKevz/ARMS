<!-- Applicant Sidebar -->
<li><a href="{{ route('applicant.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>

<li><a><i class="fas fa-file-invoice"></i> Submission report <span class="fa fa-chevron-down"></span></a>
    <ul class="nav child_menu">
        <li><a href="#">Notice Conduct</a></li>
        <li><a href="#">Report to Changes</a></li>
        <li><a href="#">Post Training Report</a></li>
    </ul>
</li>
<li><a href="{{ route('applicant.renewal.index') }}"><i class="fas fa-sync-alt"></i> Renewal / Re-Instatement </a></li>
<li><a href="{{ route('applicant.instructors.index') }}"><i class="fas fa-chalkboard-teacher"></i> FATPRO Instructor </a></li>