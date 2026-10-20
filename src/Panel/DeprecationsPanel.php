<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Event\EventInterface;
use Cake\Utility\Hash;
use Crustum\DebugBar\DebugInclude;
use Crustum\DebugBar\DebugPanel;

/**
 * Provides a list of deprecated methods for the current request
 *
 * @ported-from \DebugKit\Panel\DeprecationsPanel
 */
class DeprecationsPanel extends DebugPanel
{
    /**
     * The list of depreated errors.
     *
     * @var array
     */
    protected static array $deprecatedErrors = [];

    /**
     * instance of DebugInclude
     *
     * @var \Crustum\DebugBar\DebugInclude
     */
    protected DebugInclude $_debug;

    /**
     * construct
     */
    public function __construct()
    {
        $this->_debug = new DebugInclude();
    }

    /**
     * Get a list of files that were deprecated and split them out into the various parts of the app
     *
     * @return array
     */
    protected function _prepare(): array
    {
        $errors = static::$deprecatedErrors;
        $return = ['cake' => [], 'app' => [], 'plugins' => [], 'vendor' => [], 'other' => []];

        foreach ($errors as $error) {
            $file = $error['file'];
            $line = $error['line'];

            $errorData = [
                'file' => $file,
                'line' => $line,
                'message' => $error['message'],
            ];

            $pluginName = $this->_debug->getPluginName($file);
            /** @var string|false $pluginName */
            if ($pluginName) {
                $errorData['niceFile'] = $this->_debug->niceFileName($file, 'plugin', $pluginName);
                $return['plugins'][$pluginName][] = $errorData;
            } elseif ($this->_debug->isAppFile($file)) {
                $errorData['niceFile'] = $this->_debug->niceFileName($file, 'app');
                $return['app'][] = $errorData;
            } elseif ($this->_debug->isCakeFile($file)) {
                $errorData['niceFile'] = $this->_debug->niceFileName($file, 'cake');
                $return['cake'][] = $errorData;
            } else {
                /** @var string|false $vendorName */
                $vendorName = $this->_debug->getComposerPackageName($file);

                if ($vendorName) {
                    $errorData['niceFile'] = $this->_debug->niceFileName($file, 'vendor', $vendorName);
                    $return['vendor'][$vendorName][] = $errorData;
                } else {
                    $errorData['niceFile'] = $this->_debug->niceFileName($file, 'root');
                    $return['other'][] = $errorData;
                }
            }
        }

        ksort($return['plugins']);
        ksort($return['vendor']);

        return $return;
    }

    /**
     * Add a error
     *
     * @param array $error The deprecated error
     * @return void
     */
    public static function addDeprecatedError(array $error): void
    {
        static::$deprecatedErrors[] = $error;
    }

    /**
     * Reset the tracked errors.
     *
     * @return void
     */
    public static function clearDeprecatedErrors(): void
    {
        static::$deprecatedErrors = [];
    }

    /**
     * Reset per-request state for the middleware loop.
     *
     * @return void
     */
    public static function clear(): void
    {
        static::clearDeprecatedErrors();
    }

    /**
     * Get the number of files deprecated in this request.
     *
     * @return string
     */
    public function summary(): string
    {
        $data = $this->_data;
        if ($data === []) {
            $data = $this->_prepare();
        }

        return (string)array_reduce($data, function ($carry, $item) {
            if (empty($item)) {
                return $carry;
            }

            if (Hash::dimensions($item) === 2) {
                return $carry + count($item);
            }

            foreach ($item as $group) {
                $carry += count($group);
            }

            return $carry;
        }, 0);
    }

    /**
     * Shutdown callback
     *
     * @param \Cake\Event\EventInterface $event Event
     * @return void
     */
    public function shutdown(EventInterface $event): void
    {
        $this->_data = $this->_prepare();
    }
}
