<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Model\Table;

use Cake\Database\Schema\TableSchema;
use PDOException;
use RuntimeException;

/**
 * A set of methods for building a database table when it is missing.
 *
 * Because the toolbar store doesn't come with a pre-built SQLite database,
 * we'll need to make it as we need it.
 *
 * This trait lets us dump fixture schema into a given database at runtime.
 *
 * @ported-from \DebugKit\Model\Table\LazyTableTrait
 */
trait LazyTableTrait
{
    /**
     * Ensures the tables for the given fixtures exist in the schema.
     *
     * If the tables do not exist, they will be created on the current model's connection.
     *
     * @param array $fixtures The fixture names to check and/or insert.
     * @return void
     * @throws \RuntimeException When fixtures are missing/unknown/fail.
     */
    public function ensureTables(array $fixtures): void
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = $this->getConnection();
        $schema = $connection->getSchemaCollection();

        try {
            $existing = $schema->listTables();
        } catch (PDOException $pdoException) {
            if (str_contains($pdoException->getMessage(), 'schema has changed')) {
                $existing = $schema->listTables();
            } else {
                throw $pdoException;
            }
        }

        try {
            $config = require dirname(__DIR__, 2) . '/schema.php';
            $driver = $connection->getDriver();
            foreach ($config as $table) {
                if (in_array($table['table'], $existing, true)) {
                    continue;
                }

                if (!in_array($table['table'], $fixtures, true)) {
                    continue;
                }

                $schema = new TableSchema($table['table'], $table['columns']);
                foreach ($table['constraints'] as $name => $itemConfig) {
                    $schema->addConstraint($name, $itemConfig);
                }

                foreach ($schema->createSql($connection) as $sql) {
                    $driver->execute($sql);
                }
            }
        } catch (PDOException $pdoException) {
            if (strpos($pdoException->getMessage(), 'unable to open')) {
                throw new RuntimeException('Could not create a SQLite database. ' .
                'Ensure that your webserver has write access to the database file and folder it is in.', $pdoException->getCode(), $pdoException);
            }

            throw $pdoException;
        }
    }
}
