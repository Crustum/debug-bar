<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

/**
 * The panels table collects the information for each panel on
 * each request.
 *
 * @property \Crustum\DebugBar\Model\Table\RequestsTable&\Cake\ORM\Association\BelongsTo $Requests
 * @method \Crustum\DebugBar\Model\Entity\Panel get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, ...$args)
 * @method \Crustum\DebugBar\Model\Entity\Panel newEntity($data = null, array $options = [])
 * @method \Crustum\DebugBar\Model\Entity\Panel[] newEntities(array $data, array $options = [])
 * @method \Crustum\DebugBar\Model\Entity\Panel|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Crustum\DebugBar\Model\Entity\Panel patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Crustum\DebugBar\Model\Entity\Panel[] patchEntities($entities, array $data, array $options = [])
 * @method \Crustum\DebugBar\Model\Entity\Panel findOrCreate($search, callable $callback = null, array $options = [])
 * @ported-from \DebugKit\Model\Table\PanelsTable
 */
class PanelsTable extends Table
{
    use LazyTableTrait;
    use SqlTraceTrait;

    /**
     * initialize method
     *
     * @param array $config Config data.
     * @return void
     */
    public function initialize(array $config): void
    {
        $this->belongsTo('Crustum/DebugBar.Requests');
        $this->ensureTables(['requests', 'panels']);
    }

    /**
     * Find panels by request id
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query
     * @param string|int $requestId The request id
     * @return \Cake\ORM\Query\SelectQuery The query.
     */
    public function findByRequest(SelectQuery $query, string|int $requestId): SelectQuery
    {
        return $query->where(['Panels.request_id' => $requestId])
            ->orderBy(['Panels.title' => 'ASC']);
    }

    /**
     * DebugBar tables are special.
     *
     * @return string
     */
    public static function defaultConnectionName(): string
    {
        return 'debug_bar';
    }
}
