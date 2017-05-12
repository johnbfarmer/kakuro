<?php 

namespace AppBundle\Process;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class BaseProcess
{
    protected
        $parameters,
        $em,
        $logging = true,
        $log_file = 'app/log/app.log',
        $logger;

    public function __construct($parameters = [], $em = [])
    {
        $this->parameters = $parameters;
        $this->em = $em;
        $this->logger = new Logger('Kakuro');
        $formatter = new LineFormatter(null, null, true);
        $stream = new StreamHandler($this->log_file, Logger::INFO);
        $stream->setFormatter($formatter);
        $this->logger->pushHandler($stream);
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
            $this->logger->info($msg);
        }

        if ($std_out) {
            print "$msg\n";
        }
    }

    public static function autoExecute($parameters, $em)
    {
        $class = get_called_class();
        $me = new $class($parameters, $em);
        $me->execute();
        return $me;
    }
}