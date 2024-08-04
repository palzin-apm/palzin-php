<?php

namespace Palzin\Models;

use Palzin\Models\Partials\Host;

class Error extends Arrayable
{
    use HasContext;

    /**
     * name of the model.
     */
    const MODEL_NAME = 'error';

    /**
     * Error constructor.
     *
     * @param \Throwable $throwable
     * @param Transaction $transaction
     */
    public function __construct(\Throwable $throwable, Transaction $transaction)
    {
        $this->model = self::MODEL_NAME;
        $this->timestamp = microtime(true);

        $this->host = new Host();

        $this->message = $throwable->getMessage()
            ? $throwable->getMessage()
            : get_class($throwable);

        $this->class = get_class($throwable);
        $this->file = $throwable->getFile();
        $this->line = $throwable->getLine();
        $this->code = $throwable->getCode();
        $this->stack = $this->stackTraceToArray($throwable);

        $this->transaction = $transaction->only(['name', 'hash']);
    }

    /**
     * Determine if the exception is handled/unhandled.
     *
     * @param bool $value
     * @return $this
     */
    public function setHandled(bool $value)
    {
        $this->handled = $value;
        return $this;
    }

    /**
     * Serialize stack trace to array
     *
     * @param \Throwable $throwable
     * @return array
     */
    public function stackTraceToArray(\Throwable $throwable)
    {
        $stack = [];
        $counter = 0;

        $stack[] = [
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'code' => $this->getCode($throwable->getFile(), $throwable->getLine()),
        ];

        foreach ($throwable->getTrace() as $trace) {
            // Exception object `getTrace` does not return file and line number for the first line
            // http://php.net/manual/en/exception.gettrace.php#107563
            if (isset($topFile, $topLine) && $topFile && $topLine) {
                $trace['file'] = $topFile;
                $trace['line'] = $topLine;

                unset($topFile, $topLine);
            }

            $stack[] = [
                'class' => isset($trace['class']) ? $trace['class'] : null,
                'function' => isset($trace['function']) ? $trace['function'] : null,
                'args' => $this->stackTraceArgsToArray($trace),
                'type' => $trace['type'] ?? 'function',
                'file' => $trace['file'] ?? '[internal]',
                'line' => $trace['line'] ?? '0',
                'code' => isset($trace['file'])
                    ? $this->getCode($trace['file'], $trace['line'] ?? '0')
                    : [],
                'in_app' => isset($trace['file'])
                    ? strpos($trace['file'], 'vendor') === false
                    : false,
            ];

            // Reporting limit
            if (++$counter >= 50) {
                break;
            }
        }

        return $stack;
    }

    /**
     * Serialize stack trace function arguments
     *
     * @param array $trace
     * @return array
     */
    protected function stackTraceArgsToArray(array $trace)
    {
        $params = [];

        if (!isset($trace['args'])) {
            return $params;
        }

        foreach ($trace['args'] as $arg) {
            if (is_array($arg)) {
                $params[] = 'array(' . count($arg) . ')';
            } else if (is_object($arg)) {
                $params[] = get_class($arg);
            } else if (is_string($arg)) {
                $params[] = 'string(' . $arg . ')';
            } else if (is_int($arg)) {
                $params[] = 'int(' . $arg . ')';
            } else if (is_float($arg)) {
                $params[] = 'float(' . $arg . ')';
            } else if (is_bool($arg)) {
                $params[] = 'bool(' . ($arg ? 'true' : 'false') . ')';
            } else if ($arg instanceof \__PHP_Incomplete_Class) {
                $params[] = 'object(__PHP_Incomplete_Class)';
            } else {
                $params[] = gettype($arg);
            }
        }

        return $params;
    }

    /**
     * Extract code source from file.
     *
     * @param $filePath
     * @param $line
     * @param int $linesAround
     * @return mixed
     */
    public function getCode($filePath, $line, $linesAround = 5)
    {

        try {
            $file = new \SplFileObject($filePath);
            $file->setMaxLineLen(250);
            $file->seek(PHP_INT_MAX);

            $codeLines = [];

            $from = max(0, $line - $linesAround);
            $to = min($line + $linesAround, $file->key());

            $file->seek($from);

            while ($file->key() < $to && !$file->eof()) {

                // `key()` returns 0 as the first line
                $codeLines[] = [
                    'line' => $file->key() + 1,
                    'code' => rtrim($file->current()),
                ];
                $file->next();
            }

            return $codeLines;
        } catch (\Exception $e) {
            return null;
        }
    }
}
