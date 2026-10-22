<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Model\Entity;

use Cake\ORM\Entity;

/**
 * Panel entity class.
 *
 * @property int $id
 * @property int $request_id
 * @property string $panel
 * @property string $title
 * @property string $summary
 * @property string $element
 * @property string $content
 *
 * @property \Crustum\DebugBar\Model\Entity\Request $request
 * @ported-from \DebugKit\Model\Entity\Panel
 */
class Panel extends Entity
{
    /**
     * Some fields should not be in JSON/array exports.
     *
     * @var list<string>
     */
    protected array $hidden = ['content'];

    /**
     * Read the stream contents or inflate deflated data.
     *
     * Over certain sizes PDO will return file handles.
     * For backwards compatibility and consistency we smooth over that difference here.
     *
     * @param mixed $content Content
     * @return string
     */
    protected function _getContent(mixed $content): string
    {
        if (is_resource($content)) {
            rewind($content);
            $read = stream_get_contents($content);
            $content = is_string($read) ? $read : '';
        }

        if (is_string($content) && function_exists('gzinflate')) {
            // phpcs:disable
            $contentInflated = @gzinflate($content);
            // phpcs:enable
            if ($contentInflated !== false) {
                return $contentInflated;
            }
        }

        return $content;
    }

    /**
     * Deflate the string data before saving it into database
     *
     * @param mixed $content Content
     * @return mixed
     */
    protected function _setContent(mixed $content): mixed
    {
        if (is_string($content) && function_exists('gzdeflate')) {
            $contentDeflated = gzdeflate($content, 9);
            if ($contentDeflated !== false) {
                $content = $contentDeflated;
            }
        }

        return $content;
    }
}
