<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

/**
 * Mini launcher contract for the host iframe.
 *
 * The mini launcher is the 40x40 icon rendered by `inject-iframe.js` on the
 * host page. The toolbar itself always stays bottom-docked; only this icon
 * moves. Positions mirror the six toolbar placements minus the centered
 * bar forms, with `middle-*` pinning the icon to the viewport middle edge.
 */
class MiniLauncher
{
    /**
     * Allowed host positions for the mini icon.
     *
     * @var array<string>
     */
    public const POSITIONS = [
        'bottom-right',
        'bottom-left',
        'top-right',
        'top-left',
        'middle-right',
        'middle-left',
    ];

    /**
     * Default host position.
     *
     * @var string
     */
    public const DEFAULT_POSITION = 'bottom-right';

    /**
     * Allowed mini click actions.
     *
     * @var array<string>
     */
    public const ACTIONS = [
        'bar',
        'inspector',
    ];

    /**
     * Default mini click action.
     *
     * @var string
     */
    public const DEFAULT_ACTION = 'bar';

    /**
     * Sanitizes a host mini position.
     *
     * @param mixed $position Candidate position.
     * @return string Valid position or the default.
     */
    public static function sanitizePosition(mixed $position): string
    {
        if (is_string($position) && in_array($position, self::POSITIONS, true)) {
            return $position;
        }

        return self::DEFAULT_POSITION;
    }

    /**
     * Sanitizes a mini click action.
     *
     * @param mixed $action Candidate action.
     * @return string Valid action or the default.
     */
    public static function sanitizeAction(mixed $action): string
    {
        if (is_string($action) && in_array($action, self::ACTIONS, true)) {
            return $action;
        }

        return self::DEFAULT_ACTION;
    }
}
