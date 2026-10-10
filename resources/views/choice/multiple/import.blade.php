@if(in_array($event->status,['DRAFT','PUBLISHED'],true) && $event->end_at->gte(now()))
<details class="card card-body mb-4" @if($summary['total']===0) open @endif>
<summary class="h3 mb-0">Import Candidates — Multiple Applied Posts</summary>
<form class="mt-3" method="post" enctype="multipart/form-data" action="{{ route('choice-multiple.preview',[$event,$post]) }}">@csrf
<p class="text-secondary">Upload one file for this applied post. CSV / XLS / XLSX · Maximum 5 MB and 10,000 rows.</p>
<p><strong>Required:</strong> user, reg, name, b_date, ssc_roll, ssc_year. Parent names and all other fields are optional. Keep identifiers as Text, including SSC roll, to preserve leading zeros.</p>
<p class="text-secondary">Exact match: name + full birth date + SSC roll + SSC year. Optional fields are supporting information. Partial matches require administrator review. Name matching for automatic links is case-sensitive; birth dates are normalized before comparison.</p>
<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-primary" href="{{ route('choice-multiple.sample',[$event,$post]) }}">Download Sample Excel (XLSX)</a><a class="btn btn-outline-secondary" href="{{ route('choice-import.template',['post_id'=>$post->id]) }}">Download CSV Template</a></div>
<label for="multiple-file" class="form-label">Candidate file for {{ $post->post_code }} — {{ $post->title }}</label>
<input id="multiple-file" class="form-control mb-3" name="file" type="file" accept=".csv,.xls,.xlsx" required>
<button class="btn btn-primary">Validate & Preview Matches</button>
</form></details>
@else<div class="alert alert-info">Import is unavailable for closed, archived or cancelled events.</div>@endif
