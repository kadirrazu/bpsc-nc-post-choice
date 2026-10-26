@props(['name'=>'choices'])
@php
$paths=[
    'choices'=>'<rect x="4" y="3" width="16" height="19" rx="2"/><path d="m7 8 1 1 2-2m-3 7 1 1 2-2m3-5h4m-4 6h4M7 19h10"/>',
    'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'archive'=>'<rect x="3" y="3" width="18" height="4" rx="1"/><path d="M5 7v13h14V7M10 11h4"/>',
    'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
    'user'=>'<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
    'user-plus'=>'<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2m4-13v6m-3-3h6"/>',
    'key'=>'<circle cx="8" cy="15" r="5"/><path d="m11.5 11.5 8-8L22 6l-2 2-2-2m-2 2 2 2"/>',
    'login'=>'<path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5M3 12h12m-4-4 4 4-4 4"/>',
    'logout'=>'<path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5m-1-9h12m-4-4 4 4-4 4"/>',
    'world'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a18 18 0 0 1 0 18 18 18 0 0 1 0-18"/>',
    'edit'=>'<path d="m16 4 4 4M4 20l4-1L21 6a2.8 2.8 0 0 0-4-4L4 15l-1 6z"/>',
    'trash'=>'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
    'check'=>'<path d="m5 12 4 4L19 6"/>',
];
@endphp
<svg {{ $attributes->merge(['class'=>'icon','width'=>24,'height'=>24,'viewBox'=>'0 0 24 24','fill'=>'none','stroke'=>'currentColor','stroke-width'=>1.8,'stroke-linecap'=>'round','stroke-linejoin'=>'round','aria-hidden'=>'true','focusable'=>'false']) }}>{!! $paths[$name] ?? $paths['choices'] !!}</svg>
