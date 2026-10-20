<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Cake\View\JsonView;
use Composer\Console\Application;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Provides utility features need by the toolbar.
 *
 * @ported-from \DebugKit\Controller\ComposerController
 */
class ComposerController extends DebugBarController
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName(JsonView::class);
    }

    /**
     * Check outdated composer dependencies
     *
     * @return void
     * @throws \RuntimeException
     */
    public function checkDependencies(): void
    {
        $this->request->allowMethod('post');

        $input = new ArrayInput([
            'command' => 'outdated',
            '--no-interaction' => true,
            '--direct' => filter_var($this->request->getData('direct'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $output = $this->executeComposerCommand($input);
        $dependencies = array_filter(explode("\n", $output->fetch()));
        $semverCompatible = [];
        $bcBreaks = [];
        foreach ($dependencies as $dependency) {
            if (str_contains($dependency, 'php_network_getaddresses')) {
                throw new RuntimeException('You have to be connected to the internet');
            }

            if (str_contains($dependency, '<highlight>')) {
                $semverCompatible[] = $dependency;
                continue;
            }

            $bcBreaks[] = $dependency;
        }

        $packages = [];
        if ($semverCompatible !== []) {
            $packages['semverCompatible'] = trim(implode("\n", $semverCompatible));
        }

        if ($bcBreaks !== []) {
            $packages['bcBreaks'] = trim(implode("\n", $bcBreaks));
        }

        $this->viewBuilder()->setOption('serialize', ['packages']);
        $this->set('packages', $packages);
    }

    /**
     * Audit locked dependencies for known security advisories.
     *
     * Runs `composer audit` against the lock file (network-dependent, hence
     * on demand only) and normalizes the report to per-package advisories
     * with display tones. Offline or old-composer failures stay a 200 with
     * an `error` message instead of fake data.
     *
     * @return void
     */
    public function audit(): void
    {
        $this->request->allowMethod('post');

        $input = new ArrayInput([
            'command' => 'audit',
            '--locked' => true,
            '--format' => 'json',
            '--no-interaction' => true,
        ]);

        try {
            $output = $this->executeComposerCommand($input);
            $report = json_decode($output->fetch(), true);
        } catch (RuntimeException) {
            $report = null;
        }

        $vulnerabilities = [];
        $advisories = [];
        $error = 'Could not audit dependencies (composer audit failed — offline or composer < 2.4?).';
        if (is_array($report)) {
            foreach ((array)($report['advisories'] ?? []) as $package => $items) {
                $normalized = [];
                foreach ((array)$items as $item) {
                    $item = (array)$item;
                    $severity = strtolower((string)($item['severity'] ?? ''));
                    $normalized[] = [
                        'severity' => $severity !== '' ? $severity : 'unknown',
                        'tone' => match ($severity) {
                            'critical', 'high' => 'bad',
                            'medium' => 'warn',
                            default => 'neutral',
                        },
                        'title' => (string)($item['title'] ?? ''),
                        'cve' => (string)($item['cve'] ?? ''),
                        'link' => (string)($item['link'] ?? ''),
                    ];
                }

                if ($normalized !== []) {
                    $vulnerabilities[] = (string)$package;
                    $advisories[(string)$package] = $normalized;
                }
            }

            $error = null;
        }

        $this->viewBuilder()->setOption('serialize', ['vulnerable', 'advisories', 'count', 'error']);
        $this->set('vulnerable', $vulnerabilities);
        $this->set('advisories', $advisories);
        $this->set('count', count($vulnerabilities));
        $this->set('error', $error);
    }

    /**
     * Runs one composer command and captures its output.
     *
     * @param \Symfony\Component\Console\Input\ArrayInput $input An array describing the command input
     * @return \Symfony\Component\Console\Output\BufferedOutput Aa Console command buffered result
     */
    private function executeComposerCommand(ArrayInput $input): BufferedOutput
    {
        $bin = implode(DIRECTORY_SEPARATOR, [ROOT, 'vendor', 'bin', 'composer']);
        putenv('COMPOSER_HOME=' . $bin);
        putenv('COMPOSER_CACHE_DIR=' . CACHE);

        $dir = (string)getcwd();
        chdir(ROOT);
        $timeLimit = ini_get('max_execution_time');
        set_time_limit(300);
        $memoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        $output = new BufferedOutput();
        $application = new Application();
        $application->setAutoExit(false);
        $application->run($input, $output);

        chdir($dir);
        set_time_limit((int)$timeLimit);
        ini_set('memory_limit', $memoryLimit);

        return $output;
    }
}
