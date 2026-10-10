<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>BPSC Choice Submission Receipt</title>
@php
    // Reserve extra header height for longer event titles on every page.
    $eventTitleLines=max(1,(int)ceil(mb_strwidth($event->title,'UTF-8')/65));
    $headerReserve=84+($eventTitleLines-1)*15+($event->multiple_posts ? 8 : 0);
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
</style></head><body><header><h1>Bangladesh Public Service Commission (BPSC)</h1><p>Choice Event Administrative Record</p><p><strong>{{ $event->title }}</strong></p><p>Post Code: {{ $event->post_code }} | {{ $event->unit }}</p><hr></header>
<h2>Event Information</h2><table class="receipt-details"><tbody>
<tr><th>Event Title</th><td>{{ $event->title }}</td></tr><tr><th>Post Code</th><td>{{ $event->post_code }}</td></tr><tr><th>Unit</th><td>{{ $event->unit }}</td></tr><tr><th>Status</th><td>{{ $event->status }}</td></tr>
<tr><th>Total Candidates</th><td>{{ number_format($summary['total_candidates']) }}</td></tr>
<tr><th>Submitted Candidates</th><td>{{ number_format($summary['submitted_candidates']) }}</td></tr>
<tr><th>Schedule</th><td>{{ $event->start_at->format('d M Y H:i') }} — {{ $event->end_at->format('d M Y H:i') }} (Bangladesh time)</td></tr>
@if($event->instructions)<tr><th>Instructions</th><td>{{ $event->instructions }}</td></tr>@endif
</tbody></table>
@if($event->multiple_posts)
<h2>Applied Post Submission Statistics</h2>
<p style="font-size:10.5pt">Unique Submissions: <strong>{{ number_format($summary['submitted_candidates']) }}</strong> · Total Applications: <strong>{{ number_format(array_sum(array_column($postSummary,'total_candidates'))) }}</strong></p>
<table class="receipt-choices"><thead><tr><th style="width:13%">Post Code</th><th style="width:39%">Applied Post</th><th style="width:16%;text-align:center">Candidates</th><th style="width:16%;text-align:center">Submitted</th><th style="width:16%;text-align:center">Not Submitted</th></tr></thead><tbody>
@foreach($postSummary as $stat)<tr><td>{{ $stat['post_code'] }}</td><td>{{ $stat['post_title'] }}</td><td style="text-align:center">{{ number_format($stat['total_candidates']) }}</td><td style="text-align:center">{{ number_format($stat['submitted_candidates']) }}</td><td style="text-align:center">{{ number_format($stat['not_submitted']) }}</td></tr>@endforeach
</tbody></table><p style="font-size:9.5pt">A candidate can count under several posts; unique submissions count each person once. Cancelled submissions are excluded.</p>
@endif
<h2>Choice Code Reference</h2>
<table class="receipt-choices"><thead><tr><th style="width:10%;text-align:center">Order</th><th style="width:14%;text-align:center">Code</th><th>Choice Title / Eligibility Post</th><th style="width:14%;text-align:center">Posts</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td style="text-align:center">{{ $row['order'] }}</td><td style="text-align:center">{{ $row['code'] }}</td><td><span data-choice-title class="{{ preg_match('/[\x{0980}-\x{09FF}]/u',$row['title']) ? 'choice-title-bn' : '' }}">{{ $row['title'] }}</span><br><small>Post: {{ $row['post_code'] }} — {{ $row['post_title'] }}@if($row['organization']) · {{ $row['organization'] }}@endif @if($row['ministry']) · {{ $row['ministry'] }}@endif</small></td><td style="text-align:center">{{ $row['post_count'] ?? '—' }}</td></tr>@empty<tr><td colspan="4">No choices configured.</td></tr>@endforelse
</tbody></table>
</body></html>
