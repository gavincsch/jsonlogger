<?php
namespace Glowtech\JsonLogger\Util;

use Monolog\Formatter\JsonFormatter as BaseJSONFormatter;

class JSONFormatter extends BaseJSONFormatter
{

    /**
     * Json encode the input array
     *
     * With the bobErrorHandler() installed, grabbing a partial json_encode() response is not
     * possible because bobErrorHandler() will throw an exception on E_WARNING.
     *
     * Temporarily overriding the error handler allows us to grab the result even if a warning
     * is generated.
     *
     * @param array $records
     * @return string
     */
    private function jsonEncodeWithErrorsSupressed(array $records) {
        set_error_handler(function($errno,$errstr) {
            /* json_encode warning */
        },E_WARNING);
        $result = json_encode($records);
        restore_error_handler();
        return $result;
    }

    public function format(array $record)
    {
        return $this->jsonEncodeWithErrorsSupressed($record).PHP_EOL;
    }

    public function formatBatch(array $records)
    {
        return $this->jsonEncodeWithErrorsSupressed($records).PHP_EOL;
    }
}