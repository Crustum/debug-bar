<?php
/**
 * DebugBar icon element.
 *
 * Stroke SVG icon set for the shell, no runtime. Sizes map to `dk:`
 * utilities so the Tailwind pass generates them.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $name Icon name (search, eye, mail, activity, ellipsis, expand,
 *   expand-all, shrink, minus, plus, close, moon, sun, star, star-filled, chevron-down,
 *   warning, info, check, clock, memory, database, code, copy, link,
 *   external-link, download, server, monitor, smartphone, cake, refresh)
 * @var string $size One of `3`, `3.5`, `4`, `5` (default `5`)
 * @var string $class Extra classes (positioning, tones)
 */
$name = (string)($name ?? 'default');
$extra = trim((string)($class ?? ''));
$sizeClass = match ((string)($size ?? '5')) {
    '3' => 'dk:size-3',
    '3.5' => 'dk:size-3.5',
    '4' => 'dk:size-4',
    default => 'dk:size-5',
};
$paths = match ($name) {
    'search' => '<circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />',
    'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="2.5" />',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" />',
    'activity' => '<path d="M3 12h4l2.5-7 5 14 2.5-7H21" />',
    'ellipsis' => '<circle cx="5" cy="12" r="1.25" fill="currentColor" stroke="none" /><circle cx="12" cy="12" r="1.25" fill="currentColor" stroke="none" /><circle cx="19" cy="12" r="1.25" fill="currentColor" stroke="none" />',
    'expand' => '<rect x="5" y="5" width="14" height="14" rx="1" />',
    'expand-all' => '<rect x="3" y="10" width="11" height="11" rx="2" /><path d="M10 10V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-5" /><path d="M8.5 13.5v4M6.5 15.5h4" />',
    'shrink', 'minus' => '<path d="M5 12h14" />',
    'plus' => '<path d="M5 12h14M12 5v14" />',
    'close' => '<path d="m6 6 12 12M18 6 6 18" />',
    'moon' => '<path d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8a8.5 8.5 0 1 0 11.4 11.4Z" />',
    'sun' => '<circle cx="12" cy="12" r="3.5" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />',
    'star' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z" />',
    'star-filled' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z" fill="currentColor" />',
    'chevron-down' => '<path d="m6 9 6 6 6-6" />',
    'warning' => '<path d="M10.3 4.1 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.1a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" />',
    'info' => '<circle cx="12" cy="12" r="9" /><path d="M12 11v6M12 7h.01" />',
    'check' => '<path d="m5 12 4 4L19 6" />',
    'clock' => '<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />',
    'memory' => '<rect x="5" y="5" width="14" height="14" rx="2" /><path d="M9 9h6v6H9zM9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3" />',
    'database' => '<ellipse cx="12" cy="5" rx="8" ry="3" /><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7" />',
    'code' => '<path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14" />',
    'copy' => '<rect x="8" y="8" width="11" height="11" rx="2" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />',
    'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.8 1.7" /><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.8-1.7" />',
    'external-link' => '<path d="M14 4h6v6M20 4l-9 9" /><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5" />',
    'download' => '<path d="M12 3v12M7 10l5 5 5-5" /><path d="M4 19h16" />',
    'server' => '<rect x="3" y="3" width="18" height="7" rx="2" /><rect x="3" y="14" width="18" height="7" rx="2" /><path d="M7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6" />',
    'cake' => '<g fill="currentColor" stroke="none"><path d="M0 13.875v3.745c0 2.067 5.37 3.743 12 3.743V17.62c-6.63 0-12-1.68-12-3.743v-.002zm21.384 2.333L12 13.875v3.745l9.384 2.333C23.02 19.313 24 18.503 24 17.62v-3.745c0 .882-.98 1.692-2.616 2.333zM12 10.133v3.742c-6.627 0-12-1.677-12-3.744V6.38c0-2.064 5.37-3.743 12-3.743s12 1.68 12 3.744v3.75c0 .883-.98 1.69-2.616 2.334L12 10.13v.003z" /></g>',
    'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8M12 17v4" />',
    'smartphone' => '<rect x="5" y="2" width="14" height="20" rx="2" /><path d="M12 18h.01" />',
    'refresh' => '<path d="M21 4v6h-6" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L21 10" />',
    default => '<rect x="4" y="4" width="16" height="16" rx="4" /><path d="M8 9h8M8 13h8M8 17h5" />',
};
?>
<svg
    class="<?= h($sizeClass) ?> dk:shrink-0<?= $extra !== '' ? ' ' . h($extra) : '' ?>"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
><?= $paths ?></svg>
