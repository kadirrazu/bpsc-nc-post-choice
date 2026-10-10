@extends('layouts.app')
@section('title','Multiple-Post Import Review')
@section('content')
<div class="card card-body mb-3"><div class="d-flex flex-wrap justify-content-between gap-3"><div><h1 class="h2">Review Candidate Matches</h1><p class="mb-0">{{ $event->title }} · {{ $post->post_code }} — {{ $post->title }}</p></div><a class="btn btn-outline-secondary align-self-start" href="{{ route('choice-import.index',[$event,$post]) }}">Back to Candidates</a></div><p class="text-secondary mt-2 mb-0">{{ $pending['filename'] }} · Preview expires in 60 minutes. Save review decisions on each page before confirming. Existing candidate identity is retained; each application's original identity is preserved.</p></div>
<div class="row g-3 mb-3">@foreach(['new'=>'New Candidates','exact'=>'Exact Matches','review'=>'Review Rows','pending'=>'Awaiting Decision'] as $key=>$label)<div class="col-6 col-lg-3"><div class="card card-body"><div class="text-secondary">{{ $label }}</div><div class="h1 mb-0">{{ number_format($stats[$key]) }}</div></div></div>@endforeach</div>
<div class="alert alert-info">Only exact name, birth date, SSC roll and SSC year matches are linked automatically when you confirm import. Partial matches never link automatically. For every review row, select a candidate, create a separate candidate, or skip it.</div>
<form method="post" action="{{ route('choice-multiple.decisions',[$event,$post]) }}">@csrf @method('PUT')<input type="hidden" name="nonce" value="{{ $pending['nonce'] }}"><input type="hidden" name="page" value="{{ $rows->currentPage() }}">
<div class="card mb-3"><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>File Row</th><th>Incoming Candidate</th><th>Matching / Review</th></tr></thead><tbody>
@foreach($rows as $i=>$item)
<tr><td class="align-top">{{ $item['line'] }}<br><span class="badge {{ $item['kind']==='exact' ? 'bg-green-lt' : ($item['kind']==='review' ? 'bg-yellow-lt':'bg-blue-lt') }}">{{ ucfirst($item['kind']) }}</span></td>
<td class="align-top"><strong>{{ $item['row']['name'] }}</strong><div class="small text-secondary">User: {{ $item['row']['user'] }} · Reg: {{ $item['row']['reg'] }}</div><div class="small">DOB: {{ $item['row']['b_date'] }}<br>SSC: {{ $item['row']['ssc_roll'] }} / {{ $item['row']['ssc_year'] }}</div><div class="small text-secondary">Father: {{ $item['row']['fname'] ?: '—' }}<br>Mother: {{ $item['row']['mname'] ?: '—' }}</div></td>
<td style="min-width:320px">
@if($item['kind']==='new')<p class="mb-0">No matching candidate found. A new candidate will be created.</p>
@elseif($item['kind']==='exact')<p class="mb-2">Link to Candidate #{{ $item['matches'][0] }} — all four mandatory values match exactly.</p>
@else
<label class="form-label" for="decision-{{ $i }}">Administrator decision</label>
<select id="decision-{{ $i }}" name="decisions[{{ $i }}]" class="form-select mb-2 multiple-match-decision"><option value="">Choose a decision</option>
@foreach($item['matches'] as $id)<option value="link:{{ $id }}" @selected(old('decisions.'.$i,$item['decision'])==='link:'.$id) @disabled(in_array($post->id,$people[$id]['post_ids']??[],true))>Link to #{{ $id }} — {{ $people[$id]['name'] }}{{ in_array($post->id,$people[$id]['post_ids']??[],true) ? ' (already applied to this post)' : '' }}</option>@endforeach
<option value="new" @selected(old('decisions.'.$i,$item['decision'])==='new')>Create a separate candidate</option><option value="skip" @selected(old('decisions.'.$i,$item['decision'])==='skip')>Skip this row</option></select>
@endif
@foreach($item['matches'] as $id)
<details class="mb-2"><summary class="text-primary">Compare with #{{ $id }} — {{ $people[$id]['name'] }}</summary>
@if(isset($item['reasons'][$id]))<p class="small text-secondary my-2">{{ $item['reasons'][$id] }}</p>@endif
<div class="table-responsive mt-2"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Field</th><th>File</th><th>Existing Candidate</th></tr></thead><tbody>
@php($version=$item['comparisons'][$id]??0)
@php($comparison=$version>0 ? ($people[$id]['aliases'][$version-1]??$people[$id]) : $people[$id])
@foreach($fields as $field)@php($incoming=(string)($item['row'][$field]??''))@php($existing=(string)($comparison[$field]??''))
<tr><th>{{ $field }}</th><td>{{ $incoming ?: '—' }}</td><td class="{{ $incoming!=='' && $existing!=='' ? ($incoming===$existing ? 'text-success':'text-danger') : 'text-secondary' }}">{{ $existing ?: '—' }}</td></tr>
@endforeach</tbody></table></div></details>
@endforeach
</td></tr>
@endforeach</tbody></table></div><div class="card-footer">@include('choice.import.pagination',['paginator'=>$rows])</div></div>
@if(collect($rows->items())->contains(fn($item)=>$item['kind']==='review'))<button class="btn btn-primary mb-3">Save Review Decisions on This Page</button>@endif
</form>
<form method="post" action="{{ route('choice-multiple.confirm',[$event,$post]) }}" class="card card-body">@csrf<input type="hidden" name="nonce" value="{{ $pending['nonce'] }}">
@if($stats['pending'])<p class="text-warning">{{ $stats['pending'] }} review rows still need a saved decision. Import cannot be confirmed yet.</p>@else<p>{{ count($pending['plan'])-$stats['skipped'] }} applications are ready to import; {{ $stats['skipped'] }} rows will be skipped.</p>@endif
<label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmation" value="1" required><span class="form-check-label">I have reviewed the matching results and saved my decisions.</span></label>
<p id="multiple-unsaved" class="text-warning" hidden>Save your changed review decisions before confirming import.</p>
<button id="multiple-confirm" class="btn btn-success align-self-start" @disabled($stats['pending']>0)>Confirm Import</button></form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.multiple-match-decision').forEach(function (select) {
    select.addEventListener('change', function () {
        document.getElementById('multiple-confirm').disabled = true;
        document.getElementById('multiple-unsaved').hidden = false;
    });
});
</script>
@endpush
