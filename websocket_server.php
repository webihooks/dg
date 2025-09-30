<?php
require 'vendor/autoload.php';

use WebSocket\Server;

$server = new Server("localhost", 8080);

echo "WebSocket server running on port 8080\n";

while ($true) {
    try {
        $connection = $server->accept();
        $message = $server->receive();
        
        if ($message) {
            $data = json_decode($message, true);
            
            // Broadcast to all connected clients
            foreach ($server->getConnections() as $client) {
                if ($client !== $connection) {
                    $server->send($client, $message);
                }
            }
        }
        
        $server->disconnect();
    } catch (Exception $e) {
        // Log error and continue
        error_log("WebSocket error: " . $e->getMessage());
    }
}
?>