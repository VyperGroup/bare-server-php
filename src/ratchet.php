<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class WebSocketServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg);

            if ($data->type !== 'connect' || !is_array($data->forwardHeaders)) {
                throw new \Exception('Invalid message');
            }

            $client = new Client();
            $request = new Request('GET', $data->remote, $data->headers);
            $response = $client->send($request);

            // Add forwarded headers to the response
            foreach ($data->forwardHeaders as $header) {
                if ($response->hasHeader($header)) {
                    $from->addHeader($header, $response->getHeader($header));
                }
            }

            $openMessage = json_encode([
                'type' => 'open',
                'protocol' => '',
                'setCookies' => $response->getHeader('Set-Cookie'),
            ]);

            $from->send($openMessage);

            // Forward messages from client to remote
            $from->on('message', function ($msg) use ($client, $data) {
                $client->send(new Request('POST', $data->remote, [], $msg));
            });

            // Forward messages from remote to client
            $client->on('message', function ($msg) use ($from) {
                $from->send($msg);
            });
        } catch (\Exception $e) {
            echo "An error has occurred: {$e->getMessage()}\n";
            $from->close();
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}
php>