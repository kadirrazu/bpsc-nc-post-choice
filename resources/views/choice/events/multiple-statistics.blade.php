@if($showTotals ?? true)<div class="row g-3 mb-3">
@foreach(['total_candidates'=>'Unique Candidates','submitted_candidates'=>'Unique Submissions'] as $key=>$label)
<div class="col-md-6"><div class="card card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($summary[$key]) }}</div></div></div>
@endforeach
</div>@endif
<div class="table-responsive mb-3"><table class="table table-vcenter mb-0"><thead><tr>
<th class="fw-bold">Post Code</th><th class="fw-bold">Applied Post</th><th class="fw-bold text-center">Candidates</th><th class="fw-bold text-center">Submitted</th><th class="fw-bold text-center">Not Submitted</th>
@if($showExports ?? false)<th class="fw-bold">Matched Candidate Exports</th>@endif
</tr></thead><tbody>
@forelse($postSummary as $stat)<tr><td><strong>{{ $stat['post_code'] }}</strong></td><td>{{ $stat['post_title'] }}</td><td class="text-center">{{ number_format($stat['total_candidates']) }}</td><td class="text-center text-success fw-bold">{{ number_format($stat['submitted_candidates']) }}</td><td class="text-center">{{ number_format($stat['not_submitted']) }}</td>
@if($showExports ?? false)<td><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary btn-sm" href="{{ route('choice-exports.matched',[$event,$stat['post_id'],'xlsx']) }}">XLSX</a><a class="btn btn-outline-primary btn-sm" href="{{ route('choice-exports.matched',[$event,$stat['post_id'],'dbf']) }}">DBF</a></div></td>@endif
</tr>@empty<tr><td colspan="{{ ($showExports ?? false) ? 6 : 5 }}" class="text-secondary">No applied posts yet.</td></tr>@endforelse
</tbody></table></div>
<p class="text-secondary small mb-0">Event-wide counts; search filters do not change these statistics. A candidate may appear in several post totals but is counted once in Unique Submissions. Cancelled submissions are excluded.</p>
@if($showExports ?? false)<p class="text-secondary small mt-2 mb-0">Exports include all confirmed imported candidates for the selected post, with matched registration and user columns for other posts. Unmatched values are blank. XLSX uses reg_POSTCODE / user_POSTCODE; DBF uses REG_POSTCODE / USR_POSTCODE (10-character values). Long or nonstandard post codes use R_P{post ID} / U_P{post ID} in DBF.</p>@endif
