<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=20261026">
<link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=20261026">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=20261026">
<meta name="theme-color" content="#eaf2fb">
<script src="{{ asset('js/password-visibility.js') }}?v=20261026" defer></script>
<style>
.password-field { position:relative; }
.password-field .form-control { padding-right:3rem; }
.password-field .form-control.is-invalid { background-position:right 2.75rem center; padding-right:4.5rem; }
.password-toggle { position:absolute; right:1px; top:1px; bottom:1px; width:2.75rem; display:flex; align-items:center; justify-content:center; border:0; border-radius:0 5px 5px 0; background:transparent; color:#526b88; cursor:pointer; }
.password-toggle:hover { background:#edf4fc; color:#245587; }
.password-toggle:focus-visible { outline:2px solid #356ba6; outline-offset:-3px; }
.password-toggle .icon { width:20px; height:20px; }
.password-field ~ .invalid-feedback { display:block; }
</style>
