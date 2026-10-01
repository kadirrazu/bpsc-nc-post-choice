@extends('choice.candidate.layout')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><h2 class="h3 mb-0">Choice Submission Receipt</h2><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('candidate.receipt-pdf',$event) }}" target="_blank" rel="noopener">Print / View PDF</a><a class="btn btn-outline-primary" href="{{ route('candidate.receipt-pdf',[$event,'download'=>1]) }}">Download PDF</a></div></div>
<p class="text-secondary">Your final choices are submitted. Use the PDF for A4 printing with page numbers and print timestamp.</p>
<div class="card card-body receipt-screen">@include('choice.candidate.receipt-body')<div class="receipt-screen-footer">Print Timestamp: {{ $printTimestamp }}</div></div>
<style>
.receipt-screen { font-size:12pt; }
.receipt-screen h2 { font-size:12pt; font-weight:bold; margin:12px 0 8px; }
.receipt-screen table { width:100%; border-collapse:collapse; margin-bottom:16px; }
.receipt-screen th,.receipt-screen td { border:1px solid #c7cdd4; padding:9px 11px; text-align:left; vertical-align:top; overflow-wrap:anywhere; }
.receipt-screen .receipt-details,.receipt-screen .receipt-choices { font-size:10.5pt; line-height:1.3; table-layout:fixed; }
.receipt-screen .receipt-details th { width:23%; background:#f5f7f9; }
.receipt-screen .receipt-details td { width:77%; }
.receipt-screen .receipt-details th,.receipt-screen .receipt-details td,.receipt-screen .receipt-choices th,.receipt-screen .receipt-choices td { padding:9px 10px; vertical-align:middle; }
.receipt-screen .receipt-choices .receipt-number { text-align:center; vertical-align:middle; }
.receipt-screen .receipt-choice-heading { white-space:nowrap; }
.receipt-screen-footer { color:#666; font-size:9pt; }
@media(max-width:575px) { .receipt-screen { padding:12px; } .receipt-screen th,.receipt-screen td { padding:6px; } }
</style>
@endsection
