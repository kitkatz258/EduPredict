{{-- Read by resources/js/feedback.js and shown as toasts. Breeze status tokens stay inline. --}}
@php
    $flashStatus = session('status');
    $statusTokens = ['profile-updated', 'password-updated', 'verification-link-sent'];
    $toastStatus = is_string($flashStatus) && $flashStatus !== '' && ! in_array($flashStatus, $statusTokens, true);
@endphp
@if (session('success') || $toastStatus)
    <div data-flash="success" hidden>{{ session('success') ?? $flashStatus }}</div>
@endif
@if (session('error'))
    <div data-flash="error" hidden>{{ session('error') }}</div>
@endif
