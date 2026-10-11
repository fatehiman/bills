@php
    $paths = [
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'drop' => '<path d="M12 2.5c3.5 4.2 6 7.4 6 11a6 6 0 0 1-12 0c0-3.6 2.5-6.8 6-11z"/>',
        'flame' => '<path d="M12 22c4 0 7-2.8 7-6.8 0-3-1.8-5-3.4-6.8-.5 1.8-1.4 2.8-2.6 3.3C13.4 8 12.2 4.8 9.5 2 9.5 6 5 8.5 5 15.2 5 19.2 8 22 12 22z"/>',
        'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'signal' => '<rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/>',
        'doc' => '<path d="M6 2h9l4 4v16H6z"/><path d="M14 2v5h5M9 13h7M9 17h7"/>',
        'receipt' => '<path d="M5 2h14v20l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h3"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4M4 5v4h4M4 13a8 8 0 0 0 14.5 4M20 19v-4h-4"/>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h8"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a7 7 0 0 1 4 6"/>',
        'logout' => '<path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/>',
        'plug' => '<path d="M9 2v6M15 2v6M6 8h12v4a6 6 0 0 1-12 0zM12 18v4"/>',
        'home' => '<path d="m3 11 9-8 9 8M5 10v10h5v-6h4v6h5V10"/>',
        'alert' => '<path d="M12 3 2 21h20zM12 10v5M12 18v.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
@endphp
<svg class="i" viewBox="0 0 24 24" aria-hidden="true">{!! $paths[$name] ?? $paths['doc'] !!}</svg>
