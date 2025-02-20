<?php
require '../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class ChatWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        switch ($data['type']) {
            case 'auth':
                $this->userConnections[$data['user_id']] = $from;
                break;
                
            case 'message':
                $this->broadcastMessage($data);
                break;
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
    
    protected function broadcastMessage($data) {
        // Get all participants of the conversation
        // Send message to all connected participants
        foreach ($this->userConnections as $userId => $conn) {
            if (in_array($userId, $data['participants'])) {
                $conn->send(json_encode($data));
            }
        }
    }
}

// Create WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatWebSocket()
        )
    ),
    8080
);

$server->run(); 