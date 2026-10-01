<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
<p class="text-secondary mb-0 small">Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }} matching records</p>
@if($paginator->hasPages())
@php($start=max(1,$paginator->currentPage()-2))
@php($end=min($paginator->lastPage(),$paginator->currentPage()+2))
<nav aria-label="Candidate pages"><ul class="pagination pagination-sm mb-0 flex-wrap">
<li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">@if($paginator->onFirstPage())<span class="page-link" aria-disabled="true">Previous</span>@else<a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif</li>
@if($start>1)<li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>@if($start>2)<li class="page-item disabled"><span class="page-link">…</span></li>@endif @endif
@foreach($paginator->getUrlRange($start,$end) as $page=>$url)<li class="page-item {{ $page===$paginator->currentPage() ? 'active' : '' }}">@if($page===$paginator->currentPage())<span class="page-link" aria-current="page">{{ $page }}</span>@else<a class="page-link" href="{{ $url }}">{{ $page }}</a>@endif</li>@endforeach
@if($end<$paginator->lastPage())@if($end<$paginator->lastPage()-1)<li class="page-item disabled"><span class="page-link">…</span></li>@endif<li class="page-item"><a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}">{{ $paginator->lastPage() }}</a></li>@endif
<li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">@if($paginator->hasMorePages())<a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="page-link" aria-disabled="true">Next</span>@endif</li>
</ul></nav>@endif
</div>
