{{-- Read by resources/js/feedback.js and shown as toasts. --}}
@if (session('success') || session('status'))
    <div data-flash="success" hidden>{{ session('success') ?? session('status') }}</div>
@endif
@if (session('error'))
    <div data-flash="error" hidden>{{ session('error') }}</div>
@endif
