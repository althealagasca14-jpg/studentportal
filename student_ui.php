<?php
// Small inline SVG set shared only by the student interface.
function kns_ui_icon(string $name, string $class = ''): string
{
    $paths = [
        'cap' => '<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11v6c4 3 8 3 12 0v-6M22 9v7"/>',
        'home' => '<path d="m3 10 9-7 9 7v10H3V10Z"/><path d="M9 20v-7h6v7"/>',
        'book' => '<path d="M4 4h7a3 3 0 0 1 3 3v14a4 4 0 0 0-3-2H4V4Zm16 0h-4a2 2 0 0 0-2 2v15a4 4 0 0 1 3-2h3V4Z"/>',
        'record' => '<path d="M5 3h14v18H5a2 2 0 0 1 0-4h14M5 3a2 2 0 0 0-2 2v14"/><path d="M8 7h7M8 11h7"/>',
        'user' => '<circle cx="12" cy="7" r="3"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
        'chat' => '<path d="M4 4h16v12H9l-5 5V4Z"/><path d="M8 8h8M8 12h5"/>',
        'search' => '<circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/>',
        'filter' => '<path d="M3 4h18l-7 8v7l-4 2V12L3 4Z"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'logout' => '<path d="M9 3H4v18h5m5-15 6 6-6 6M8 12h12"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 8-3 8-3 10h18c0-2-3-2-3-10M10 22h4"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
        'trend' => '<path d="m3 18 7-7 4 4 7-11m-7 0h7v7"/>',
        'close' => '<path d="m5 5 14 14M19 5 5 19"/>',
        'menu' => '<path d="M3 5h18M3 12h18M3 19h18"/>',
        'photo' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="2"/><path d="m3 17 6-6 4 4 3-3 5 5"/>',
        'file' => '<path d="M5 2h9l5 5v15H5V2Z"/><path d="M14 2v6h5M8 13h8M8 17h8"/>',
        'send' => '<path d="m3 10 18-7-7 18-3-8-8-3Z"/><path d="m11 13 10-10"/>'
    ];
    $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    return '<svg class="ui-icon ' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['record']) . '</svg>';
}
