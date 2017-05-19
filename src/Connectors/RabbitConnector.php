<?php
namespace EDMRabbitPackage\Connectors;

use EDMRabbitPackage\Exceptions\ResponseStatusNot200;
use EDMRabbitPackage\Exceptions\TimeOutException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


class RabbitConnector
{
    private $host;
    private $port;
    private $vhost;
    private $user;
    private $password;


    /**
     * RabbitConnector constructor.
     */
    public function __construct()
    {
        $this->host = \Config::get('rabbit.host');
        $this->port = \Config::get('rabbit.port');
        $this->vhost = \Config::get('rabbit.vhost');
        $this->user = \Config::get('rabbit.user');
        $this->password = \Config::get('rabbit.password');

    }

    public function makeConnection()
    {
        $connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password,$this->vhost);
        return $connection;
    }


    /**
     * USER SESSION QUEUE
     */
    public function createUserSessionQueue()
    {

        $c = $this->makeConnection();
        $ch = $c->channel();

        /*
            name: $queue    // should be unique in fanout exchange. Let RabbitMQ create
                            // a queue name for us
            passive: false  // don't check if a queue with the same name exists
            durable: false // the queue will not survive server restarts
            exclusive: true // the queue can not be accessed by other channels
            auto_delete: true //the queue will be deleted once the channel is closed.
        */
        list($queueName, ,) = $ch->queue_declare('', false, false, true, false);//TORNA IL NOME DELLA CODA AUTOMATICO?
        //bind coda su routing_key = nome utente
        /*
            name: $exchange
            type: direct
            passive: false // don't check if a exchange with the same name exists
            durable: false // the exchange will not survive server restarts
            auto_delete: true //the exchange will be deleted once the channel is closed.
        */
//        $channel->exchange_declare($exchange, 'fanout', false, false, true);
//        $channel->queue_bind($queueName, $exchange);
        //LEGGE LE NOTIFICHE SU ES!!!!!!


    }

    /**
     * SEND A SINGLE MESSAGE TO A QUEUE AND SET RESPONSE QUEUE
     * @param $body: array of:
     *             index = ES index
     *             type = ES type
     *             action = create/notify_upload etc...
     *             request = json equivalent of HTTP request
     *             response (for BE response only)
     *             status (for BE response only)
     *             errors (for BE response only)
     * @param string $queue
     * @return string
     */
    public function sendMessage(array $body, $queue = "test")
    {
        //MI CONNETTO A RABBIT E APRO IL CANALE
        $c = $this->makeConnection();
        $ch = $c->channel();

        //CREO IL MESSAGGIO
        $msg = new AMQPMessage( json_encode($body), [
            'type'          => 'REQUEST', //RESPONSE and LOG
            'app_id'		=> 'FE_'.env('RABBIT_ENV').'_'.gethostname(),
            'delivery_mode'	=> '2'
        ]);

        //PUBBLICO IL MESSAGGIO
        $ch->basic_publish($msg, '', $queue);


        //CHIUDO CONNESSIONE E CANALE
        $ch->close();
        $c->close();
        return true;
    }

    /**
     * SEND A SINGLE MESSAGE TO A QUEUE AND SET RESPONSE QUEUE
     * @param $body: array of:
     *             index = ES index
     *             type = ES type
     *             action = create/notify_upload etc...
     *             request = json equivalent of HTTP request
     *             response (for BE response only)
     *             status (for BE response only)
     *             errors (for BE response only)
     * @param string $queue
     * @return string
     */
    public function sendMessageWithResponse(array $body, $queue = "test")
    {
        //CREO IL CORRELATION UUID
        $corrId = sprintf ("%08X", sprintf("%03d%06d", rand(0,999), substr(time(), -6)));

        //CREO IL NOME DELLA CODA DI RISPOSTA
        $reply_to = 'FE_'.$corrId;

        //MI CONNETTO A RABBIT E APRO IL CANALE
        $c = $this->makeConnection();
        $ch = $c->channel();

        /*
           name: $queue    // should be unique in fanout exchange.
           passive: false  // don't check if a queue with the same name exists
           durable: false // the queue will not survive server restarts
           exclusive: true // the queue can not be accessed by other channels
           auto_delete: true //the queue will be deleted once the channel is closed.
       */

        //CREO LA CODA DI RISPOSTA
        $ch->queue_declare($reply_to, false, false, false, true);


        $ch->queue_bind($reply_to, 'amq.direct', $reply_to);

        /*
         * \\ AMQPMessage PROPS //
         *
         *  correlation_id	    => ID_UNIVOCO_TRANSAZIONE (sarà  propagato a tutti i consumer) --> printf "%07X\n", (sprintf "%03d%05d", int(rand(1000)), substr(time, -5))
         *  reply_to			=> CODA_RISPOSTA (RPC) ==> se si riutilizza il correlation_id specificare anche l'applicazione per evitare collisioni! ad es. FE_correlation_id
         *  type				=> {request|response}
         *  user_id			    => EDM_Username
         *  app_id			    => {FE|WF|PVS}_{prod|test}_{hostname}
         *  delivery_mode	    => 1: non persistent, 2: persistent
         *  priority			=> $integer, non implementato da RabbitMQ di default, da 0 (bassa) a 255 (max);
         *                       creare la coda con x-max-priority = N dove N Ã¨ la massima prioritÃ  per la coda per attivare la prioritÃ  sulla coda
         *                       ===> le code RPC non devono avere prioritÃ , le code WF potrebbero averle e potremmo stabilire una regola come segue:
         *                           0 LOWEST:	messaggi da API KDM
         *                           1 LOW:		messaggi da API PCMS
         *                           2 NORMAL:	messaggi da FE
         *                           3 HIGH:	messaggi da ADMIN
         */


        //CREO IL MESSAGGIO
        $msg = new AMQPMessage( json_encode($body), [
            'reply_to'      => $reply_to,
            'correlation_id'=> $corrId,
            'type'          => 'REQUEST', //RESPONSE and LOG
            'app_id'		=> 'FE_'.env('RABBIT_ENV').'_'.gethostname(),
            'delivery_mode'	=> '2'
        ]);

        //PUBBLICO IL MESSAGGIO
        $ch->basic_publish($msg, '', $queue);

        //CHIUDO CONNESSIONE E CANALE
        $ch->close();
        $c->close();
        return $corrId;
    }

    /**
     * READ A SINGLE MESSAGE FROM THE RESPONSE QUEUE
     * @param $corrId
     * @return string
     * @throws ResponseStatusNot200
     */
    public function     consumeQueueFromCorrelationId($corrId)
    {
        //RICAVO IL NOME DELLA CODA
        $reply_to = 'FE_'.$corrId;

        $timeout = 20;
        $c = $this->makeConnection();
        $ch = $c->channel();
        $messageReceived = '';

        $callback = function($data) use (&$messageReceived, &$exit , $corrId) {
            while($exit == 0) {
                if($data->get('correlation_id') == $corrId ){
//                    dd($data);
                    $messageReceived = json_decode($data->body);
//                    MESSAGE ACKNOWLEDGE
                    $data->delivery_info['channel']->basic_ack(
                        $data->delivery_info['delivery_tag']);
                    $exit =1;
                }
            }
        };

//        $ch->basic_qos(null,1,null); // return only one message


        /*
            reply_to: Queue from where to get the messages
            consumer_tag: Consumer identifier
            no_local: Don't receive messages published by this consumer.
            no_ack: Tells the server if the consumer will acknowledge the messages.
            exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
            nowait:
            callback: A PHP Callback
        */
        $ch->basic_consume($reply_to, '', false, false, false, false, $callback);

        $exit = 0;

        while(count($ch->callbacks)) {
            try{
                $ch->wait(null, false, $timeout);
            }catch(AMQPTimeoutException $e){
                $ch->close();
                $c->close();
                throw new TimeOutException ($e);
            }
        }

        $ch->close();
        $c->close();

        if($messageReceived->status != '200'){
            throw new ResponseStatusNot200($messageReceived->errors);
        }

        return ($messageReceived->response);


    }
}