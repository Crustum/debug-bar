<?php
/**
 * Request trace icon element.
 *
 * Duotone step icons for the request lifecycle timeline (Received, Matched,
 * Responded). Artwork: Phosphor Icons (MIT, https://github.com/phosphor-icons/core).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $name One of `received`, `matched`, `success`, `error`, `neutral`
 * @var string $class Extra classes (sizing, tones)
 */
$name = (string)($name ?? 'neutral');
$extra = trim((string)($class ?? ''));
$paths = match ($name) {
    'received' => '<path d="M200,112l-72,72L56,112Z" opacity="0.2" /><path d="M122.34,189.66a8,8,0,0,0,11.32,0l72-72A8,8,0,0,0,200,104H136V32a8,8,0,0,0-16,0v72H56a8,8,0,0,0-5.66,13.66ZM180.69,120,128,172.69,75.31,120ZM224,216a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,216Z" />',
    'matched' => '<path d="M224,64a24,24,0,1,1-24-24A24,24,0,0,1,224,64Z" opacity="0.2" /><path d="M232,64a32,32,0,1,0-40,31v17a8,8,0,0,1-8,8H96a23.84,23.84,0,0,0-8,1.38V95a32,32,0,1,0-16,0v66a32,32,0,1,0,16,0V144a8,8,0,0,1,8-8h88a24,24,0,0,0,24-24V95A32.06,32.06,0,0,0,232,64ZM64,64A16,16,0,1,1,80,80,16,16,0,0,1,64,64ZM96,192a16,16,0,1,1-16-16A16,16,0,0,1,96,192ZM200,80a16,16,0,1,1,16-16A16,16,0,0,1,200,80Z" />',
    'success' => '<path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2" /><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z" />',
    'error' => '<path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2" /><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-80V80a8,8,0,0,1,16,0v56a8,8,0,0,1-16,0Zm20,36a12,12,0,1,1-12-12A12,12,0,0,1,140,172Z" />',
    default => '<path d="M224,128a96,96,0,1,1-96-96A96,96,0,0,1,224,128Z" opacity="0.2" /><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Z" />',
};
?>
<svg
    class="dk:size-5 dk:shrink-0 dk:lg:size-6<?= $extra !== '' ? ' ' . h($extra) : '' ?>"
    viewBox="0 0 256 256"
    fill="currentColor"
    stroke="none"
    aria-hidden="true"
><?= $paths ?></svg>
