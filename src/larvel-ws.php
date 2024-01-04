<?php
namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use BeyondCode\LaravelWebSockets\Events\NewConnection;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\RFC6455\Messaging\TextMessage;
use Ratchet\ConnectionInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class HandleNewConnections
{
    public function handle(NewConnection $connection)
    {
        Log::info('Here is a new connection');

        $connection->socket->on('message', function (MessageInterface $msg) use ($connection) {
          try {
              $data = json_decode($msg->getPayload(), true);
      
              if ($data['type'] !== 'connect' || !is_array($data['forwardHeaders'])) {
                  throw new \Exception('Invalid message');
              }
      
              $client = new Client();
              $request = new Request('GET', $data['remote'], $data['headers']);
              $response = $client->send($request);
      
              // Add forwarded headers to the response
              foreach ($data['forwardHeaders'] as $header) {
                  if ($response->hasHeader($header)) {
                      $connection->socket->addHeader($header, $response->getHeader($header));
                  }
              }
      
              $openMessage = new TextMessage(json_encode([
                  'type' => 'open',
                  'protocol' => '',
                  'setCookies' => $response->getHeader('Set-Cookie'),
              ]));
      
              $connection->socket->send($openMessage);
      
              // Forward messages from client to remote
              $connection->socket->on('message', function (MessageInterface $msg) use ($client, $data) {
                $client->send(new Request('POST', $data['remote'], [], $msg->getPayload()));
            });
    
            // Forward messages from remote to client
            $client->on('message', function (MessageInterface $msg) use ($connection) {
                $connection->socket->send($msg);
            });
        } catch (\Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            $connection->socket->close();
        }
    });
    
    $connection->socket->on('close', function () use ($client) {
        $client->close();
    });
    }
}
?>