<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>BPSC Choice Submission Receipt</title>
@php
    // Reserve extra header height for longer event titles on every page.
    $eventTitleLines=max(1,(int)ceil(mb_strwidth($event->title,'UTF-8')/65));
    $headerReserve=84+($eventTitleLines-1)*15;
@endphp
<style>
/* Half-inch outer print boundary; bottom content reserve protects signature/footer. */
@page { size:A4 portrait; margin:{{ 36+$headerReserve }}pt 36pt 108pt 36pt; }
body { font-family:"Times New Roman",serif; font-size:12pt; font-weight:normal; color:#111; margin:0; line-height:1.2; }
header { position:fixed; top:-{{ $headerReserve }}pt; left:0; right:0; text-align:center; margin:0; }
header hr { border:0; border-top:0.6pt solid #777; margin:7pt 0 0; }
h1 { font-size:14pt; font-weight:bold; margin:0 0 4pt; }
header p { font-size:12pt; font-weight:normal; line-height:1.15; margin:2pt 0; }
h2 { font-size:12pt; font-weight:bold; margin:10pt 0 5pt; page-break-after:avoid; }
table { width:100%; border-collapse:collapse; margin-bottom:8pt; table-layout:fixed; }
th,td { border:0.6pt solid #aab2bb; padding:4pt 5pt; vertical-align:middle; text-align:left; word-wrap:break-word; }
th { font-weight:bold; background:#f3f5f7; }
.receipt-details,.receipt-choices { font-size:10.5pt; line-height:1.3; }
.receipt-details th { width:32%; white-space:nowrap; }
.receipt-details td { width:68%; }
.receipt-details th,.receipt-details td,.receipt-choices th,.receipt-choices td { padding:5pt 7pt; vertical-align:middle; }
.receipt-choices .receipt-number { text-align:center; vertical-align:middle; }
.receipt-choice-heading { white-space:nowrap; }
/* Keep the choice header repeatable without wrapping the whole table in a block. */
.receipt-choices { page-break-inside:auto; }
.receipt-choices thead { display:table-header-group; }
.receipt-choices tbody { display:table-row-group; page-break-inside:auto; }
.receipt-choices thead tr { page-break-inside:avoid; page-break-after:avoid; }
thead { display:table-header-group; }
tr { page-break-inside:avoid; }
</style></head><body><header><h1>Bangladesh Public Service Commission (BPSC)</h1><p>Choice Submission Receipt</p><p><strong>{{ $event->title }}</strong></p><p>Post Code: {{ $event->post_code }} | {{ $event->unit }}</p><hr></header>
@include('choice.candidate.receipt-body')
</body></html>
