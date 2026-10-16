@if ($enquiryEvent = session()->pull('marketing_enquiry_event'))
    <div hidden data-marketing-events="{{ json_encode([$enquiryEvent], JSON_THROW_ON_ERROR) }}"></div>
@endif
