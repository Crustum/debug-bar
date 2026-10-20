<?php
declare(strict_types=1);

namespace TestApp\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Browser articles table for the fixture flows.
 *
 * Lives under its own alias so the shared `Articles` locator name keeps
 * resolving to a plain table for unrelated suites. Real validation
 * rules so create/update flows produce explicit entity errors through
 * the standard ORM path.
 */
class BrowserArticlesTable extends Table
{
    /**
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('articles');
        $this->setPrimaryKey('id');
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('title', 'create')
            ->notEmptyString('title', 'Title is required.');

        $validator
            ->requirePresence('body', 'create')
            ->notEmptyString('body', 'Body is required.')
            ->minLength('body', 10, 'Body is too short.');

        return $validator;
    }
}
