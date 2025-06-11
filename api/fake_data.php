<?php
include 'db.php'; // Kết nối đến database
require_once __DIR__ . '/../vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;


$server = '192.168.192.101';
$port = 2003;
$clientId = 'sensor_publisher_' . rand(1, 999);
$username = 'hoanghuy';
$password = 'hoanghuy';
$clean_session = false;

// publish sensor data
$topic_temp = 'home/temperature';
$topic_humidity = 'home/humidity';
$topic_light = 'home/light';

// Create MQTT connection settings
$connectionSettings = (new ConnectionSettings)
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(60)
    ->setLastWillTopic('home/status')
    ->setLastWillMessage('offline')
    ->setLastWillQualityOfService(1);

// Create MQTT client and connect
try {
    $mqtt = new MqttClient($server, $port, $clientId);
    $mqtt->connect($connectionSettings, $clean_session);
    echo "Connected to MQTT broker at $server:$port\n";
} catch (Exception $e) {
    echo "Error connecting to MQTT broker: " . $e->getMessage() . "\n";
    // Continue without MQTT if connection fails
}

// Handle shutdown to disconnect MQTT gracefully
function shutdown_handler() {
    global $mqtt;
    try {
        if (isset($mqtt) && $mqtt->isConnected()) {
            echo "Disconnecting from MQTT broker...\n";
            $mqtt->disconnect();
        }
    } catch (Exception $e) {
        echo "Error during MQTT disconnection: " . $e->getMessage() . "\n";
    }
}
register_shutdown_function('shutdown_handler');

// Register signal handler for Ctrl+C
if (function_exists('pcntl_signal')) {
    function signal_handler($signal) {
        echo "Exiting...\n";
        exit(0);
    }
    pcntl_signal(SIGINT, 'signal_handler');
}

while (true) {
    $temperature = rand(19, 40);  
    $humidity = rand(30, 90);    
    $light = rand(100, 1000);    

    $sql = "INSERT INTO sensors (temperature, humidity, light) VALUES ('$temperature', '$humidity', '$light')";
    $conn->query($sql);

    echo "Added data: " . $temperature . "°C, " . $humidity . "%, " . $light . " lx\n";
    
    // Publish sensor data to MQTT
    if (isset($mqtt) && $mqtt->isConnected()) {
        try {
            // Create and publish temperature payload
            $temp_payload = json_encode(['temperature' => (float)$temperature]);
            $mqtt->publish(
                $topic_temp,         // topic
                $temp_payload,       // message
                0,                  // quality of service
                true                // retain
            );
            echo "Published temperature to MQTT: $temp_payload\n";
            
            // Create and publish humidity payload
            $humid_payload = json_encode(['humidity' => (float)$humidity]);
            $mqtt->publish(
                $topic_humidity,     // topic
                $humid_payload,      // message
                0,                  // quality of service
                true                // retain
            );
            echo "Published humidity to MQTT: $humid_payload\n";
            
            // Create and publish light payload
            $light_payload = json_encode(['light' => (int)$light]);
            $mqtt->publish(
                $topic_light,        // topic
                $light_payload,      // message
                0,                  // quality of service
                true                // retain
            );
            echo "Published light to MQTT: $light_payload\n";
            
            // Add alerts for when thresholds are exceeded
            if ($temperature > 30.0) {
                echo "ALERT: Temperature above 30°C - D4 LED should be blinking!\n";
            }
            if ($humidity > 50.0) {
                echo "ALERT: Humidity above 50% - D5 LED should be blinking!\n";
            }
            if ($light > 400) {
                echo "ALERT: Light above 400 lx - D6 LED should be blinking!\n";
            }
        } catch (Exception $e) {
            echo "MQTT publish error: " . $e->getMessage() . "\n";
            
            // Try to reconnect on error
            try {
                $mqtt->disconnect();
                $mqtt = new MqttClient($server, $port, $clientId);
                $mqtt->connect($connectionSettings, $clean_session);
                echo "Reconnected to MQTT broker\n";
            } catch (Exception $reconnectError) {
                echo "Failed to reconnect to MQTT: " . $reconnectError->getMessage() . "\n";
            }
        }
    }

    // Process signals if available (allows Ctrl+C to work properly)
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    
    // Chờ 2 giây trước khi tiếp tục
    sleep(2);
}
?>