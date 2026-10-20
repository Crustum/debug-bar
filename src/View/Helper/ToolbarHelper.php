<?php
declare(strict_types=1);

namespace Crustum\DebugBar\View\Helper;

use Cake\Error\Debug\ArrayItemNode;
use Cake\Error\Debug\ArrayNode;
use Cake\Error\Debug\HtmlFormatter;
use Cake\Error\Debug\NodeInterface;
use Cake\Error\Debug\ScalarNode;
use Cake\View\Helper;

/**
 * Provides Base methods for content specific debug toolbar helpers.
 * Acts as a facade for other toolbars helpers as well.
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\FormHelper $Form
 * @property \Cake\View\Helper\UrlHelper $Url
 * @ported-from \DebugKit\View\Helper\ToolbarHelper
 */
class ToolbarHelper extends Helper
{
    /**
     * helpers property
     *
     * @var array<int|string, string|array<string, mixed>>
     */
    public array $helpers = ['Html', 'Form', 'Url'];

    /**
     * Whether or not the top level keys should be sorted.
     *
     * @var bool
     */
    protected bool $sort = false;

    /**
     * set sorting of values
     *
     * @param bool $sort Whether or not sort values by key
     * @return void
     */
    public function setSort(bool $sort): void
    {
        $this->sort = $sort;
    }

    /**
     * Dump an array of nodes
     *
     * @param array<\Cake\Error\Debug\NodeInterface> $nodes An array of dumped variables.
     *   Variables should be keyed by the name they had in the view.
     * @return string Formatted HTML
     */
    public function dumpNodes(array $nodes): string
    {
        $formatter = new HtmlFormatter();
        if ($this->sort) {
            ksort($nodes);
        }

        $items = [];
        foreach ($nodes as $key => $value) {
            $items[] = new ArrayItemNode(new ScalarNode('string', $key), $value);
        }

        $root = new ArrayNode($items);

        return implode('', [
            '<div class="cake-debug-output" style="direction:ltr">',
            $formatter->dump($root),
            '</div>',
        ]);
    }

    /**
     * Dump an error node
     *
     * @param \Cake\Error\Debug\NodeInterface $node A error node containing dumped variables.
     * @return string Formatted HTML
     */
    public function dumpNode(NodeInterface $node): string
    {
        $formatter = new HtmlFormatter();

        return implode('', [
            '<div class="cake-debug-output" style="direction:ltr">',
            $formatter->dump($node),
            '</div>',
        ]);
    }
}
