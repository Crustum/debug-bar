<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Compat;

use Cake\Core\Configure;
use Crustum\DebugBar\DebugPanel;

/**
 * Keeps third-party panels written against the previous vendor name working.
 *
 * Panels that extend `DebugKit\DebugPanel` or read the `DebugKit.panels`
 * configuration keep resolving after the rename: the old class name is
 * aliased to the current panel base class and the old configuration key
 * seeds the current one when it is not set directly.
 */
final class DebugKitCompat
{
    /**
     * Scalar keys mirrored from the previous vendor name.
     *
     * @var array<string>
     */
    private const FALLBACK_KEYS = [
        'forceEnable',
        'safeTld',
        'ignoreAuthorization',
        'includeSchemaReflection',
    ];

    /**
     * Registers the legacy class alias and configuration fallback.
     *
     * @return void
     */
    public static function register(): void
    {
        if (!class_exists('DebugKit\DebugPanel')) {
            class_alias(DebugPanel::class, 'DebugKit\DebugPanel');
        }

        if (!Configure::check('DebugBar.panels') && Configure::check('DebugKit.panels')) {
            $translated = [];
            foreach ((array)Configure::read('DebugKit.panels') as $name => $enabled) {
                if (is_string($name) && str_starts_with($name, 'DebugKit.')) {
                    $name = 'Crustum/DebugBar.' . substr($name, strlen('DebugKit.'));
                }

                $translated[$name] = $enabled;
            }

            Configure::write('DebugBar.panels', $translated);
        }

        foreach (self::FALLBACK_KEYS as $key) {
            if (!Configure::check("DebugBar.{$key}") && Configure::check("DebugKit.{$key}")) {
                Configure::write("DebugBar.{$key}", Configure::read("DebugKit.{$key}"));
            }
        }
    }
}
