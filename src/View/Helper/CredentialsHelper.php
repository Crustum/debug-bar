<?php
declare(strict_types=1);

namespace Crustum\DebugBar\View\Helper;

use Cake\Utility\Hash;
use Cake\View\Helper;
use function Cake\Core\h;

/**
 * CredentialsHelper
 *
 * Filter sensitive data in screen, data will be displayed on mouse click
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Crustum\DebugBar\View\Helper\ToolbarHelper $Toolbar
 * @ported-from \DebugKit\View\Helper\CredentialsHelper
 */
class CredentialsHelper extends Helper
{
    /**
     * Helpers property
     *
     * @var array<int|string, string|array<string, mixed>>
     */
    public array $helpers = ['Html', 'Crustum/DebugBar.Toolbar'];

    /**
     * Replace credentials in url's by *****
     * Example mysql://username:password@localhost/my_db -> mysql://******@localhost/my_db
     *
     * @param mixed $in variable to filter
     * @return mixed
     */
    public function filter(mixed $in): mixed
    {
        $regexp = '/^([^:;]+:\/\/)([^:;]+:?.*?)@(.*)$/i';
        if (!is_string($in) || empty($in)) {
            return $in;
        }

        preg_match_all($regexp, $in, $tokens);
        if ($tokens[0] === []) {
            return h($in);
        }

        $protocol = Hash::get($tokens, '1.0');
        $credentials = Hash::get($tokens, '2.0');
        $tail = Hash::get($tokens, '3.0');
        $link = $this->Html->tag('a', '******', [
            'class' => 'filtered-credentials',
            'title' => h($credentials),
            'onclick' => 'this.textContent = this.title',
        ]);

        return h($protocol) . $link . '@' . h($tail);
    }
}
