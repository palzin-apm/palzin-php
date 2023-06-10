<?php


namespace Palzin\Transports;


use Palzin\Configuration;
use Palzin\Exceptions\PalzinException;
use Palzin\OS;

class AsyncTransport extends AbstractApiTransport
{
    /**
     * CURL command path.
     *
     * @var string
     */
    protected $curlPath = 'curl';

    /**
     * AsyncTransport constructor.
     *
     * @param Configuration $configuration
     * @throws PalzinException
     */
    public function __construct($configuration)
    {
        if (!function_exists('proc_open')) {
            throw new PalzinException("PHP function 'proc_open' is not available.");
        }

        parent::__construct($configuration);
    }

    /**
     * List of available transport's options with validation regex.
     *
     * ['param-name' => 'regex']
     *
     * @return mixed
     */
    protected function getAllowedOptions()
    {
        return array_merge(parent::getAllowedOptions(), [
            'curlPath' => '/.+/',
        ]);
    }

    /**
     * Send a portion of the load to the remote service.
     *
     * @param string $data
     * @return void|mixed
     */
    public function sendChunk($data)
    {
        $curl = $this->buildCurlCommand($data);
        
        if (OS::isWin()) {
            $cmd = "start /B {$curl} > NUL";
        } else {
            $cmd = "({$curl} > /dev/null 2>&1";

            // Delete temporary file after data transfer
            if (substr($data, 0, 1) === '@') {
                $cmd.= '; rm ' . str_replace('@', '', $data);
            }

            $cmd .= ')&';
        }

        proc_close(proc_open($cmd, [], $pipes));
    }

    /**
     * Carl command is agnostic between Win and Unix.
     *
     * @param $data
     * @return string
     */
    protected function buildCurlCommand($data): string
    {
        $curl = "{$this->curlPath} -X POST --ipv4  --max-time 5";

        foreach ($this->getApiHeaders() as $name => $value) {
            $curl .= " --header \"$name: $value\"";
        }

        //$curl .= " --data {$this->getPayload($data)} {$this->config->getUrl()}";
        $curl .= " --data {$data} {$this->config->getUrl()}";

        if ($this->proxy) {
            $curl .= " --proxy \"{$this->proxy}\"";
        }

        return $curl;
    }

    /**
     * Escape character to use in the CLI.
     *
     * Compatible to send data via file path: @../file/path.dat
     *
     * @param $string
     * @return mixed
     */
//    protected function getPayload($string)
//    {
//        return OS::isWin()
//            // https://stackoverflow.com/a/30224062/5161588
//            ? '"' . str_replace('"', '""', $string) . '"'
//            // http://stackoverflow.com/a/1250279/871861
//            : "'" . str_replace("'", "'\"'\"'", $string) . "'";
//    }
}
