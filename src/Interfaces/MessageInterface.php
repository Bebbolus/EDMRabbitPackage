<?php

namespace EDMRabbitPackage\Interfaces;

use EDMRabbitPackage\Connectors\RabbitConnector;

class MessageInterface
{
    private $body;
    private $queue;

    public function __construct($index, $type, $action, $request, $user)
    {
        $this->body =
            [
                'index'     => $index,
                'type'      => $type,
                'action'    => $action,
                'request'   => $request,
                'user'      => $user
            ];
        $this->queue = $type;
    }


    public function sendWithResponse()
    {
        $connector = new RabbitConnector();
        $corrId = $connector->sendMessageWithResponse($this->body, $this->queue);
        return $connector->consumeQueueFromCorrelationId($corrId);
    }

    public function send()
    {
        $connector = new RabbitConnector();
        $connector->sendMessage($this->body, $this->queue);
        return true;
    }

}