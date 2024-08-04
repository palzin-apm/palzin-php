<?php


namespace Palzin;

use Palzin\Exceptions\PalzinException;
use Palzin\Models\Arrayable;
use Palzin\Transports\AsyncTransport;
use Palzin\Transports\TransportInterface;
use Palzin\Models\PerformanceModel;
use Palzin\Models\Error;
use Palzin\Models\Segment;
use Palzin\Models\Transaction;
use Palzin\Transports\CurlTransport;
use Palzin\Models\Partials\Host;


class Palzin
{
    /**
     * Agent configuration.
     *
     * @var Configuration
     */
    protected $configuration;

    /**
     * Transport strategy.
     *
     * @var TransportInterface
     */
    protected $transport;

    /**
     * Current transaction.
     *
     * @var Transaction
     */
    protected $transaction;

    /**
     * Runa callback before flushing data to the remote platform.
     *
     * @var callable
     */

    protected static $beforeCallbacks = [];

    /**
     * Logger constructor.
     *
     * @param Configuration $configuration
     * @throws Exceptions\PalzinException
     */
    public function __construct(Configuration $configuration)
    {
        switch ($configuration->getTransport()) {
            case 'async':
                $this->transport = new AsyncTransport($configuration);
                break;
            default:
                $this->transport = new CurlTransport($configuration);
        }

        $this->configuration = $configuration;
        register_shutdown_function(array($this, 'flush'));
    }


    /**
     * Set custom transport.
     *
     * @param TransportInterface|callable $transport
     * @return $this
     * @throws PalzinException
     */
    public function setTransport($resolver)
    {
        if (is_callable($resolver)) {
            $this->transport = $resolver($this->configuration);
        } elseif ($resolver instanceof TransportInterface) {
            $this->transport = $resolver;
        } else {
            throw new PalzinException('Invalid transport resolver.');
        }
        return $this;
    }

    /**
     * Create and start new Transaction.
     *
     * @param string $name
     * @return Transaction
     * @throws \Exception
     */
    public function startTransaction($name)
    {
        $this->transaction = new Transaction($name);
        $this->transaction->start();

        $this->addEntries($this->transaction);
        return $this->transaction;
    }

    /**
     * Get current transaction instance.
     *
     * @return null|Transaction
     */
    public function currentTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    /**
     * Get current transaction instance.
     *
     * @return null|Transaction
     */
    public function transaction(): ?Transaction
    {
        return $this->transaction;
    }

    /**
     * Determine if an active transaction exists.
     *
     * @return bool
     */
    public function hasTransaction(): bool
    {
        return isset($this->transaction);
    }

    /**
     * Determine if the current cycle hasn't started its transaction yet.
     *
     * @return bool
     */
    public function needTransaction(): bool
    {
        return $this->isRecording() && !$this->hasTransaction();
    }

    /**
     * Determine if a new segment can be added.
     *
     * @return bool
     */
    public function canAddSegments(): bool
    {
        return $this->isRecording() && $this->hasTransaction();
    }


    /**
     * Check if the monitoring is enabled.
     *
     * @return bool
     */
    public function isRecording(): bool
    {
        return $this->configuration->isEnabled();
    }

    /**
     * Enable Recording
     * @return Palzin
     */
    public function startRecording()
    {
        $this->configuration->setEnabled(true);
        return $this;
    }

    /**
     * stop recording
     * @return Palzin
     */
    public function stopRecording()
    {
        $this->configuration->setEnabled(false);
        return $this;
    }

    /**
     * Add new segment to the queue.
     *
     * @param string $type
     * @param null|string $label
     * @return PerformanceModel
     */
    public function startSegment($type, $label = null)
    {
        $segment = new Segment($this->transaction, addslashes($type), $label);
        $segment->start();

        $this->addEntries($segment);
        return $segment;
    }

    /**
     * Monitor the execution of a code block.
     *
     * @param callable $callback
     * @param string $type
     * @param null|string $label
     * @param bool $throw
     * @return mixed|void
     * @throws \Throwable
     */
    public function addSegment(callable $callback,string $type, $label = null, $throw = false)
    {
        if (!$this->hasTransaction()) {
            return $callback();
        }

        try {
            $segment = $this->startSegment($type, $label);
            return $callback($segment);
        } catch (\Throwable $exception) {
            if ($throw === true) {
                throw $exception;
            }

            $this->reportException($exception);
        } finally {
            $segment->end();
        }
    }

    /**
     * Error reporting.
     *
     * @param \Throwable $exception
     * @param bool $handled
     * @return Error
     * @throws \Exception
     */
    public function reportException(\Throwable $exception, $handled = true)
    {

        if ($this->hasTransaction()) {
            $this->startTransaction(get_class($exception));
        }

        $segment = $this->startSegment('exception', $exception->getMessage());

        $error = (new Error($exception, $this->transaction))
            ->setHandled($handled);

        $this->addEntries($error);

        $segment->addContext('Error', $error)->end();

        return $error;
    }

    /**
     * Add an entry to the queue.
     *
     * @param Arrayable[]|Arrayable $entries
     * @return Palzin
     */
    public function addEntries($entries)
    {
        if ($this->isRecording()) {
            $entries = is_array($entries) ? $entries : [$entries];
            foreach ($entries as $entry) {
                $this->transport->addEntry($entry);
            }
        }
        return $this;
    }

    /**

     * Define a callback to run before flushing data to the remote platform.
     *
     * @param callable $callback
     */
    public static function beforeFlush(callable $callback)
    {
        static::$beforeCallbacks[] = $callback;
    }

    /**
     * Flush data to the remote platform.
     *
     * @throws \Exception
     */
    public function flush()
    {
        if (!$this->isRecording() || !$this->hasTransaction()) {
            $this->reset();
            return;
        }


        if (!$this->transaction->isEnded()) {
            $this->transaction->end();
        }

        foreach (static::$beforeCallbacks as $callback) {
            if (call_user_func($callback, $this) === false) {
                $this->reset();
                return;
            }
        }

        $this->transport->flush();
        unset($this->transaction);
    }

    /**
     * Cancel the current transaction, segments, and errors.
     *
     * @return Inspector
     */
    public function reset()
    {
        $this->transport->resetQueue();
        unset($this->transaction);
        return $this;
    }
}
