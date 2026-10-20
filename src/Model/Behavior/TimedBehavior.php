<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Model\Behavior;

use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;
use Crustum\DebugBar\DebugTimer;

/**
 * Class TimedBehavior
 *
 * @ported-from \DebugKit\Model\Behavior\TimedBehavior
 */
class TimedBehavior extends Behavior
{
    /**
     * beforeFind, starts a timer for a find operation.
     *
     * @param \Cake\Event\EventInterface $event The beforeFind event
     * @param \Cake\ORM\Query\SelectQuery $query SelectQuery
     * @return void
     */
    public function beforeFind(EventInterface $event, SelectQuery $query): void
    {
        /** @var \Cake\Datasource\RepositoryInterface $table */
        $table = $event->getSubject();
        $alias = $table->getAlias();
        DebugTimer::start($alias . '_find', $alias . '->find()');

        $formattedQuery = $query->formatResults(function ($results) use ($alias) {
            DebugTimer::stop($alias . '_find');

            return $results;
        });

        $event->setResult($formattedQuery);
    }

    /**
     * beforeSave, starts a time before a save is initiated.
     *
     * @param \Cake\Event\EventInterface $event The beforeSave event
     * @return void
     */
    public function beforeSave(EventInterface $event): void
    {
        /** @var \Cake\Datasource\RepositoryInterface $table */
        $table = $event->getSubject();
        $alias = $table->getAlias();
        DebugTimer::start($alias . '_save', $alias . '->save()');
    }

    /**
     * afterSave, stop the timer started from a save.
     *
     * @param \Cake\Event\EventInterface $event The afterSave event
     * @return void
     */
    public function afterSave(EventInterface $event): void
    {
        /** @var \Cake\Datasource\RepositoryInterface $table */
        $table = $event->getSubject();
        $alias = $table->getAlias();
        DebugTimer::stop($alias . '_save');
    }
}
