<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Event\EventList;
use Cake\Event\EventManager;
use Closure;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Event\CallsiteEventList;
use Crustum\DebugBar\Service\AppPath;
use Crustum\DebugBar\Service\CallsiteCapture;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

/**
 * Events panel.
 *
 * Groups tracked event-manager dispatches upstream-style: occurrence
 * counts with first/last sequences, per-listener registrations resolved
 * by reflection (duplicate registrations surfaced), and shape-only
 * payloads (field names plus types — values are never stored). Tracking
 * rides the manager's own event list, enabled here when the application
 * did not set its own; our list subclass additionally remembers the true
 * dispatch site per event, so dispatch locations point at the code that
 * dispatched (application, plugin or core) instead of the subject class
 * file. Synchronous CakePHP listeners of a non-stopped event all ran, so
 * they count as completed; stopped propagation marks the group partial
 * instead of guessing which listener ran.
 */
class EventsPanel extends DebugPanel
{
    /**
     * Owner of global event recording when a third-party manager replaces
     * the accumulator (its `addEventToList` override never fills Cake's
     * list, so this panel would stay empty forever).
     *
     * @var string|null
     */
    protected static ?string $recordingOwner = null;

    /**
     * Subscribes to the event manager with callsite tracking.
     *
     * @return void
     */
    public function initialize(): void
    {
        $manager = EventManager::instance();
        if (!$manager->getEventList() instanceof EventList) {
            $manager->setEventList(new CallsiteEventList());
        }

        static::$recordingOwner = self::recordingOwner($manager);
    }

    /**
     * Get the data for this panel.
     *
     * Groups tracked dispatches upstream-style: occurrence counts with
     * first/last sequences, per-listener registrations resolved by
     * reflection (duplicate registrations surfaced), and shape-only
     * payloads (field names plus types — values are never stored).
     * Synchronous CakePHP listeners of a non-stopped event all ran, so
     * they count as completed; stopped propagation marks the group
     * partial instead of guessing which listener ran.
     *
     * @return array
     */
    public function data(): array
    {
        if (static::$recordingOwner !== null) {
            return [
                'events' => [],
                'summary' => [
                    'count' => 0,
                    'retained_count' => 0,
                    'application_count' => 0,
                    'framework_count' => 0,
                    'dispatches' => 0,
                    'duplicates' => 0,
                ],
                'recording_owner' => static::$recordingOwner,
            ];
        }

        $manager = EventManager::instance();
        $list = $manager->getEventList();
        $tracked = $list instanceof EventList ? $this->events($list) : [];
        if ($list instanceof EventList) {
            $list->flush();
        }

        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        foreach ($tracked as $index => $event) {
            $name = (string)$event['name'];
            $groups[$name] ??= $event + [
                'occurrence_count' => 0,
                'dispatches' => 0,
                'first_sequence' => $index + 1,
                'last_sequence' => $index + 1,
                'stopped' => false,
                'occurrences' => [],
                'site_labels' => [],
            ];

            $groups[$name]['occurrence_count']++;
            $groups[$name]['dispatches']++;
            $groups[$name]['last_sequence'] = $index + 1;
            $groups[$name]['stopped'] = (bool)$groups[$name]['stopped'] || (bool)$event['stopped'];
            $groups[$name]['occurrences'][] = ['sequence' => $index + 1, 'at_ms' => null];
            $site = $event['dispatch_site'] ?? null;
            if (is_array($site)) {
                $label = CallsiteCapture::label([
                    'file' => isset($site['file']) && is_string($site['file']) ? $site['file'] : null,
                    'line' => isset($site['line']) ? (int)$site['line'] : null,
                ]);
                if ($label !== '') {
                    $groups[$name]['site_labels'][] = $label;
                }
            }
        }

        $events = [];
        $position = 0;
        foreach (array_values($groups) as $group) {
            $position++;
            $name = (string)$group['name'];
            $listeners = $this->listeners($name);
            $listenerCount = count($listeners);
            $duplicateRegistrations = $listenerCount - count(array_unique(array_column($listeners, 'label')));
            $stopped = (bool)$group['stopped'];
            $completed = $stopped ? 0 : $listenerCount;
            $dispatchSources = $this->dispatchSources($group);
            $events[] = $group + [
                'id' => $position,
                'key' => 'event-' . $position,
                'display_name' => $this->shortName($name),
                'short' => $this->shortName($name),
                'namespace' => $this->namespaceOf($name) !== '' ? rtrim($this->namespaceOf($name), '.') : null,
                'search' => strtolower($name . ' ' . $group['subject'] . ' ' . implode(
                    ' ',
                    array_column($listeners, 'label'),
                )),
                'listeners' => $listeners,
                'listener_count' => $listenerCount,
                'completed_listener_count' => $completed,
                'queued_listener_count' => 0,
                'duplicate_registration_count' => $duplicateRegistrations,
                'duplicate_registrations' => $duplicateRegistrations,
                'duplicate' => $group['occurrence_count'] > 1 || $duplicateRegistrations > 0,
                'listener_outcome' => $listenerCount === 0 ? 'observed' : ($stopped ? 'partial' : 'completed'),
                'listener_outcome_label' => $listenerCount === 0
                    ? 'No listeners'
                    : ($stopped ? 'Partial' : 'Completed'),
                'listener_summary' => $stopped
                    ? 'Propagation was stopped; some listeners may not have run.'
                    : $this->listenerSummary($completed),
                'dispatch_sources' => $dispatchSources,
                'dispatch_source_count' => count($dispatchSources),
                'dispatch_source_omitted_count' => 0,
                'occurrence_omitted_count' => 0,
                'first_at_ms' => null,
                'broadcast' => false,
                'related_inspector' => null,
                'source' => $this->groupSource($group),
            ];
        }

        $application = 0;
        $framework = 0;
        foreach ($events as $event) {
            if ($event['source'] === 'framework') {
                $framework++;
            } else {
                $application++;
            }
        }

        return [
            'events' => $events,
            'summary' => [
                'count' => count($events),
                'retained_count' => count($events),
                'application_count' => $application,
                'framework_count' => $framework,
                'dispatches' => array_sum(array_column($events, 'occurrence_count')),
                'duplicates' => count(array_filter($events, fn(array $event): bool => (bool)$event['duplicate'])),
            ],
            'recording_owner' => null,
        ];
    }

    /**
     * Detects a third-party owner of global event recording.
     *
     * Cake's list mechanism only works when `addEventToList` is the core
     * implementation — an override (e.g. a recording manager installed by
     * another debugging plugin) diverts every dispatch elsewhere and the
     * core list stays empty no matter what this panel does.
     *
     * @param \Cake\Event\EventManager $manager Global event manager.
     * @return string|null Owner short class name or null when Cake accumulates.
     */
    protected static function recordingOwner(EventManager $manager): ?string
    {
        try {
            $method = new ReflectionMethod($manager, 'addEventToList');
        } catch (Throwable) {
            return null;
        }

        if ($method->getDeclaringClass()->getName() === EventManager::class) {
            return null;
        }

        return $method->getDeclaringClass()->getShortName();
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        return '';
    }

    /**
     * Reads tracked events off the list.
     *
     * Payload shapes keep argument positions, types and public field
     * names — values are never stored.
     *
     * @param \Cake\Event\EventList $list Tracked events.
     * @return array<int, array<string, mixed>>
     */
    protected function events(EventList $list): array
    {
        $events = [];
        foreach ($list as $event) {
            try {
                $subject = $event->getSubject();
            } catch (Throwable) {
                $subject = null;
            }

            $data = (array)$event->getData();
            $events[] = [
                'name' => $event->getName(),
                'subject' => $subject === null ? null : $subject::class,
                'stopped' => $event->isStopped(),
                'data_keys' => array_keys($data),
                'data_shapes' => $this->payloadShape($data),
                'payload_shape' => $this->payloadShape($data),
                'data_count' => count($data),
                'dispatch_site' => $list instanceof CallsiteEventList ? $list->siteFor($event) : null,
            ];
        }

        return $events;
    }

    /**
     * Builds the shape-only payload description for one dispatch.
     *
     * @param array<string, mixed> $data Event data.
     * @return array<int, array{position: int, name: string, type: string, fields: array<int, string>, field_count: int}>
     */
    protected function payloadShape(array $data): array
    {
        $shape = [];
        $position = 0;
        foreach ($data as $name => $value) {
            $position++;
            $fields = [];
            if (is_array($value)) {
                $keys = array_keys($value);
                $fields = array_slice(array_map(strval(...), $keys), 0, 10);
                $fieldCount = count($keys);
            } elseif (is_object($value)) {
                $vars = array_keys(get_object_vars($value));
                $fields = array_slice($vars, 0, 10);
                $fieldCount = count($vars);
            } else {
                $fieldCount = 0;
            }

            $shape[] = [
                'position' => $position,
                'name' => (string)$name,
                'type' => get_debug_type($value),
                'fields' => $fields,
                'field_count' => $fieldCount,
            ];
        }

        return $shape;
    }

    /**
     * Builds the dispatch-location rows for one group.
     *
     * Real dispatch sites captured at dispatch time win — grouped by
     * location so a hot event shows every site with its own count. Only
     * when no site was captured (a foreign list was already installed)
     * does the subject class file stand in, honestly coarse.
     *
     * @param array<string, mixed> $group Grouped dispatches.
     * @return array<int, array{source: string, count: int}> Source rows.
     */
    protected function dispatchSources(array $group): array
    {
        $labels = array_values((array)($group['site_labels'] ?? []));
        if ($labels !== []) {
            $counts = array_count_values($labels);
            arsort($counts);
            $rows = [];
            foreach ($counts as $label => $count) {
                $rows[] = ['source' => (string)$label, 'count' => $count];
            }

            return $rows;
        }

        $subject = (string)($group['subject'] ?? '');
        $occurrences = (int)($group['occurrence_count'] ?? 0);

        return $this->subjectSource($subject, $occurrences);
    }

    /**
     * Classifies one group by dispatch sites, subject fallback second.
     *
     * Application wins from either side: a controller dispatch funneled
     * through a core trait still belongs to the application when the
     * subject is application code, and an application dispatch site keeps
     * plugin-dispatched events visible under the application filter.
     *
     * @param array<string, mixed> $group Grouped dispatches.
     * @return string `application` or `framework`.
     */
    protected function groupSource(array $group): string
    {
        $labels = array_values((array)($group['site_labels'] ?? []));
        foreach ($labels as $label) {
            $parts = CallsiteCapture::split((string)$label);
            if ($parts['file'] !== '' && AppPath::isApp($parts['file'])) {
                return 'application';
            }
        }

        if ($this->source((string)($group['subject'] ?? '')) === 'application') {
            return 'application';
        }

        return 'framework';
    }

    /**
     * Derives a dispatch source from the dispatching subject class.
     *
     * The tracked list carries no callsite, so the subject's own
     * definition file stands in — navigable and honest about its
     * granularity. Unresolvable subjects keep the empty state.
     *
     * @param string $subject Dispatching subject class.
     * @param int $occurrences Dispatch count for the group.
     * @return array<int, array{source: string, count: int}> Source rows.
     */
    protected function subjectSource(string $subject, int $occurrences): array
    {
        if ($subject === '' || (!class_exists($subject) && !interface_exists($subject))) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($subject);
            $file = (string)$reflection->getFileName();
        } catch (Throwable) {
            return [];
        }

        if ($file === '') {
            return [];
        }

        $line = $reflection->getStartLine();
        $source = CallsiteCapture::label(['file' => $file, 'line' => $line === false ? null : $line]);

        return [['source' => $source, 'count' => $occurrences]];
    }

    /**
     * Resolves the registered listeners for one event name.
     *
     * Each entry carries a display label, an optional application source
     * and the registration count for its label, so double registrations
     * of the same callable surface instead of hiding.
     *
     * @param string $name Event name.
     * @return array<int, array{label: string, name: string, queued: bool, file: string, line: int|null, source: array{file: string, line: int|null}|null, app: bool, registrations: int}>
     */
    protected function listeners(string $name): array
    {
        $resolved = [];
        foreach (EventManager::instance()->listeners($name) as $callable) {
            $entry = $this->resolveListener($callable);
            if ($entry === null) {
                continue;
            }

            $resolved[] = $entry;
        }

        $counts = array_count_values(array_column($resolved, 'label'));
        foreach ($resolved as &$entry) {
            $entry['registrations'] = $counts[$entry['label']] ?? 1;
        }

        unset($entry);

        return $resolved;
    }

    /**
     * Resolves one listener callable to a label plus source.
     *
     * @param mixed $callable Registered listener.
     * @return array{label: string, name: string, queued: bool, file: string, line: int|null, source: array{file: string, line: int|null}|null, app: bool, registrations: int}|null
     */
    protected function resolveListener(mixed $callable): ?array
    {
        if (is_array($callable) && array_key_exists('callable', $callable)) {
            $callable = $callable['callable'];
        }

        try {
            if ($callable instanceof Closure) {
                $reflection = new ReflectionFunction($callable);
                $file = (string)$reflection->getFileName();
                $line = $reflection->getStartLine() === false ? null : $reflection->getStartLine();

                return $this->listenerEntry('Closure', $file, $line);
            }

            if (is_array($callable)) {
                $target = $callable[0] ?? null;
                $method = (string)($callable[1] ?? '');
                $class = is_object($target) ? $target::class : (string)$target;
                if ($class === '' || $method === '') {
                    return null;
                }

                $file = '';
                $line = null;
                try {
                    $reflection = new ReflectionMethod($class, $method);
                    $file = (string)$reflection->getFileName();
                    $line = $reflection->getStartLine() === false ? null : $reflection->getStartLine();
                } catch (Throwable) {
                    $file = '';
                    $line = null;
                }

                return $this->listenerEntry($class . '@' . $method, $file, $line);
            }

            if (is_string($callable) && $callable !== '') {
                return $this->listenerEntry($callable, '', null);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Builds one listener entry row.
     *
     * CakePHP listeners run synchronously inside dispatch — there are no
     * queued listeners to distinguish.
     *
     * @param string $label Display label.
     * @param string $file Source file or empty.
     * @param int|null $line Source line.
     * @return array{label: string, name: string, queued: bool, file: string, line: int|null, source: array{file: string, line: int|null}|null, app: bool, registrations: int}
     */
    protected function listenerEntry(string $label, string $file, ?int $line): array
    {
        return [
            'label' => $label,
            'name' => $label,
            'queued' => false,
            'file' => $file,
            'line' => $line,
            'source' => $file !== '' ? ['file' => $file, 'line' => $line] : null,
            'app' => AppPath::isApp($file === '' ? null : $file),
            'registrations' => 1,
        ];
    }

    /**
     * Shortens an event name to its last segment.
     *
     * @param string $name Full event name.
     * @return string Short display name.
     */
    protected function shortName(string $name): string
    {
        $short = str_replace('\\', '/', $name);
        $position = strrpos($short, '/');
        if ($position !== false) {
            return substr($short, $position + 1);
        }

        $position = strrpos($short, '.');

        return $position === false ? $name : substr($short, $position + 1);
    }

    /**
     * Returns the namespace (or prefix) above the short name.
     *
     * @param string $name Full event name.
     * @return string Leading segments, possibly empty.
     */
    protected function namespaceOf(string $name): string
    {
        $short = $this->shortName($name);
        if ($short === $name) {
            return '';
        }

        return substr($name, 0, -strlen($short));
    }

    /**
     * Summarizes completed registrations like upstream.
     *
     * @param int $completed Completed registration count.
     * @return string Summary sentence.
     */
    protected function listenerSummary(int $completed): string
    {
        if ($completed === 0) {
            return 'No listeners registered.';
        }

        return $completed . ' ' . ($completed === 1 ? 'registration completed.' : 'registrations completed.');
    }

    /**
     * Classifies a subject class as application or framework code.
     *
     * Subject-less (server-level) events land in framework — there is no
     * application class to attribute them to.
     *
     * @param string $class Subject class name.
     * @return string `application` or `framework`.
     */
    protected function source(string $class): string
    {
        if ($class === '') {
            return 'framework';
        }

        return str_starts_with($class, 'Cake\\') ? 'framework' : 'application';
    }
}
