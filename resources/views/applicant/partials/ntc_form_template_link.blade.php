{{--
    Download link for the blank DOLE-OSHC form an upload field expects.

    Expects: $code (NtcDocumentType code — RTCMAN or PROG)
--}}
@if($url = \App\Models\NtcDocumentType::templateUrl($code))
<a href="{{ $url }}" target="_blank" rel="noopener" class="ntc-form-template-link">
    <i class="fas fa-file-download me-1"></i>Download the blank form
</a>
@endif
