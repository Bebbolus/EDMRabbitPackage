<?php

namespace EDMRabbitPackage\Models;

use EDMRabbitPackage\Connector\RabbitConnector;

class QMessage
{
    private $index;
    private $type;
    private $action;
    private $request;
    private $user;

    public function __construct($index, $type, $action, $request, $user)
    {
        $this->index = $index;
        $this->type = $type;
        $this->action = $action;
        $this->request = $request;
        $this->user = $user;
    }


    public function sendRealTimeMessage()
    {
        $connector = new RabbitConnector();
        $corrId = $connector->sendMessageWithResponse([
            'index' => $this->index,
            'type' => $this->type,
            'action' => $this->action,
            'request' => $this->request,
            'user' => $this->user
        ], "document");
        return $connector->receiveMessage($corrId);
    }

    public function sendAsyncMessage()
    {
        $connector = new RabbitConnector();
        $connector->sendMessage([
            'index' => $this->index,
            'type' => $this->type,
            'action' => $this->action,
            'request' => $this->request,
            'user' => $this->user
        ], "document");
        return true;
    }

}