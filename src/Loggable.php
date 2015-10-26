<?php
namespace Glowtech\JsonLogger;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Glowtech\JsonLogger\Util\JSONFormatter;

trait Loggable
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var string  Storage of last called class from which $this->log is called
     */
    private $callingClass;

    /**
     * @var string  Storage of last called method before $this->log is called
     */
    private $callingMethod;

    /**
     * @var
     */
    private $description;

    /**
     * @var
     */
    private $tags;

    /**
     * @var boolean  Whether to serialize the provided context variable into PHP serialised format
     */
    private $serialiseContext;

    /**
     * Get log file name
     *
     * @return string
     */
    abstract protected function getLogfileName();

    /**
     * Get module name
     *
     * @return string
     */
    abstract protected function getLogModule();

    private function initLogger()
    {
        if (!empty($this->logger)) {
            return;
        }

        $this->logger = new Logger($this->getLogModule());

        $folderName = $this->getLogModule();
        $logFolder = $this->getLogsPath() . $folderName;

        // make sure path exists
        if (!realpath($logFolder)) {
            mkdir($logFolder, 0755, true);

            if (!realpath($logFolder)) {
                throw new Exception("Log folder [$logFolder] cannot be created");
            }
        }

        $streamHandlers = $this->getStreamHandlers($logFolder);
        foreach($streamHandlers as $handler) {
            $this->logger->pushHandler($handler);
        }
    }

    /**
     * @param $logFolder
     *
     * @return StreamHandler[]
     */
    protected function getStreamHandlers($logFolder)
    {
        // create Monolog file stream
        $stream = new StreamHandler($logFolder . DIRECTORY_SEPARATOR . $this->getLogfileName());
        $stream->pushProcessor(array($this, 'streamProcessor'));
        $stream->setFormatter(new JSONFormatter());

        return [$stream];
    }

    /**
     * Callback to manipulate the data to be written to log
     *
     * @param $record
     *
     * @return mixed
     */
    public function streamProcessor($record)
    {
        if (empty($record)) {
            return $record;
        }

        if (!isset($record['extra'])) {
            $record['extra'] = [];
        }

        $extra                =& $record['extra'];
        $extra['file']        = get_called_class();
        $extra['method']      = $this->callingMethod;
        $extra['tags']        = $this->tags;
        $extra['description'] = $this->description;
        $extra['env']         = $this->getEnvironment();

        return $record;
    }

    protected function getLogger()
    {
        $this->initLogger();
        return $this->logger;
    }

    public function log(
        $level = Logger::INFO,
        $message = '[no message]',
        $context = array(),
        $description = null,
        $tags = array(),
        $serialiseContext = false
    ) {
        $this->initLogger();

        // collect pertinent information for log
        $this->callingClass = $this->callingMethod = null;
        $this->tags         = $tags;
        $this->description  = $description;

        $backtrace           = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $this->callingClass  = isset($backtrace[1]['class']) ? $backtrace[1]['class'] : null;
        $this->callingMethod = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : null;

        // optionally serialise the context data
        if ($serialiseContext && !empty($context)) {
            $context = ['context-serialised' => serialize($context)];
        }

        try {
            $this->logger->log($level, $message, is_array($context) ? $context : [$context]);
        } catch (Exception $e) {
            // something went wrong - investigation will be conducted when it is seen that no new logs are created
        }
    }

    /**
     * The base directory where the logs are to be written
     *
     * @return string
     */
    abstract protected function getLogsPath();

    /**
     * @return string
     */
    abstract protected function getEnvironment();
}