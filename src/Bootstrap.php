<?php declare(strict_types=1);

namespace Schnitzler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use josegonzalez\Dotenv\Loader;

/**
 * Class Bootstrap
 * @package Schnitzler
 */
class Bootstrap
{

    /**
     * @var Connection
     */
    public static $db;

    /**
     * @return Bootstrap
     */
    private function __construct()
    {
        // deliberately private
    }

    /**
     * @param string $baseDirectory
     */
    static public function boot(string $baseDirectory)
    {
        static::loadEnvVariables($baseDirectory);
        static::createTmpDirectory($baseDirectory);
        static::connectDatabase();
        static::createDatabaseTables();
    }

    /**
     * @param string $baseDirectory
     */
    private static function loadEnvVariables(string $baseDirectory)
    {
        $dotEnv = $baseDirectory . '/.env';
        if (!file_exists($dotEnv)) {
            throw new \RuntimeException('.env file is missing.', 1464163560892);
        }

        $loader = new Loader($dotEnv);
        $loader->parse();
        $loader->toEnv();
    }

    /**
     * @param string $baseDirectory
     */
    private static function createTmpDirectory(string $baseDirectory)
    {
        $baseDirectory = rtrim($baseDirectory, DIRECTORY_SEPARATOR);
        define('APP', $baseDirectory . DIRECTORY_SEPARATOR);
        define('APP_TMP', APP . 'tmp/');

        if (!file_exists(APP_TMP)) {
            mkdir(APP_TMP);
        }
    }

    /**
     * @throws DBALException
     */
    private static function connectDatabase()
    {
        $connectionParams = [
            'dbname' => $_ENV['DB_DATABASE'] ?? '',
            'user' => $_ENV['DB_USER'] ?? '',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'host' => $_ENV['DB_HOST'] ?? '',
            'driver' => 'pdo_mysql',
        ];

        static::$db = DriverManager::getConnection($connectionParams);
    }

    /**
     * @return void
     */
    private static function createDatabaseTables()
    {
        $schema = new Schema();

        if (!static::$db->getSchemaManager()->tablesExist(['extensions'])) {
            $extensions = $schema->createTable('extensions');
            $extensions->addColumn('uid', 'guid');
            $extensions->addColumn('versions', 'integer', ['unsigned' => true]);
            $extensions->addColumn('name', 'string');
            $extensions->setPrimaryKey(['uid']);
        }

        if (!static::$db->getSchemaManager()->tablesExist(['versions'])) {
            $versions = $schema->createTable('versions');
            $versions->addColumn('uid', 'guid');
            $versions->addColumn('name', 'string');
            $versions->addColumn('extension', 'guid');
            $versions->addColumn('downloaded', 'boolean');
            $versions->addColumn('analyzed', 'boolean');
            $versions->addForeignKeyConstraint('extensions', ['extension'], ['uid']);
            $versions->setPrimaryKey(['uid']);
        }

        foreach ($schema->toSql(static::$db->getDatabasePlatform()) as $query) {
            static::$db->executeQuery($query);
        }
    }

}
