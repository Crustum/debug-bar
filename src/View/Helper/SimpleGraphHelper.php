<?php
declare(strict_types=1);

namespace Crustum\DebugBar\View\Helper;

use Cake\View\Helper;

/**
 * Class SimpleGraphHelper
 *
 * Allows creation and display of extremely simple graphing elements
 *
 * @ported-from \DebugKit\View\Helper\SimpleGraphHelper
 */
class SimpleGraphHelper extends Helper
{
    /**
     * Default settings to be applied to each Simple Graph
     *
     * Allowed options:
     *
     * - max => (float) Maximum value the bar scale represents (100%)
     * - valueType => string (value, percentage)
     * - style => array
     *
     * The track always fills its container; value and offset render as
     * percentages of `max`, so bars stretch with the panel like the
     * timeline waterfall instead of a fixed pixel width.
     *
     * @var array
     */
    protected array $_defaultSettings = [
        'max' => 100,
        'valueType' => 'value',
    ];

    /**
     * bar method
     *
     * @param float|int $value Value to be graphed
     * @param float|int $offset how much indentation
     * @param array $options Graph options
     * @return string Html graph
     */
    public function bar(float|int $value, float|int $offset, array $options = []): string
    {
        $settings = array_merge($this->_defaultSettings, $options);
        $max = (float)$settings['max'];
        $valueType = $settings['valueType'];

        if ($valueType === 'percentage') {
            $graphOffset = 0.0;
            $graphWidth = max(0.0, min(100.0, (float)$value));
        } else {
            $graphOffset = $max > 0 ? max(0.0, (float)$offset / $max * 100) : 0.0;
            $graphWidth = $max > 0 ? max(0.0, (float)$value / $max * 100) : 0.0;
        }

        return sprintf(
            '<div class="c-graph-bar" style="%s"><div class="c-graph-bar__value" style="%s" title="%s"> </div></div>',
            'width: 100%',
            sprintf('margin-left: %.4F%%; width: %.4F%%; min-width: 1px', $graphOffset, $graphWidth),
            "Starting {$offset}ms into the request, taking {$value}ms",
        );
    }
}
