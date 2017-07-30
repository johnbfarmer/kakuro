<?php 

namespace AppBundle\Process;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

use AppBundle\Helper\GridHelper;

class BaseProcess
{
    protected
        $parameters,
        $em,
        $connection,
        $logging = true,
        $log_file = 'app/log/app.log',
        $logger;

    public function __construct($parameters = [], $em = [])
    {
        $this->parameters = $parameters;
        $this->em = $em;
        $this->connection = GridHelper::getConnection();
    }

    protected function execute()
    {
        throw new \Exception("No execute method defined in child class");
    }

    protected function log($msg, $std_out = false)
    {
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }

        if ($this->logging) {
            GridHelper::log($msg);
            // $this->logger->info($msg);
        }

        if ($std_out) {
            print "$msg\n";
        }
    }

    protected function exec($sql, $log = true)
    {
        if ($log) {
            $this->log($sql);
        }
        $connection = $this->connection;
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    protected function fetch($sql, $log = false)
    {
        $stmt = $this->exec($sql, $log);
        $records = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $records;
    }

    protected function fetchAll($sql, $log = false)
    {
        $stmt = $this->exec($sql, $log);
        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $records;
    }

    protected function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public static function autoExecute($parameters, $em)
    {
        $class = get_called_class();
        $me = new $class($parameters, $em);
        $me->execute();
        return $me;
    }
}