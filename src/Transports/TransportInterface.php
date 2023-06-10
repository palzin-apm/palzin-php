<?php


namespace Palzin\Transports;


interface TransportInterface
{
    /**
     * Add an Arrayable entity in the queue.
     *
     * @param \Palzin\Models\Arrayable $entry
     * @return mixed
     */
    public function addEntry(Arrayable $entry);

    /**
     * Send data to Palzin.
     *
     * This method is invoked after your application has sent
     * the response to the client.
     *
     * So this is the right place to perform the data transfer.
     *
     * @return mixed
     */
    public function flush();
}
