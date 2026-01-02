<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $finger_id = isset($_POST['id_finger']) ? (int)$_POST['id_finger'] : 0;
    if ($finger_id < 1 || $finger_id > 127) {
        echo json_encode(['error' => 'Invalid finger ID']);
        exit;
    }
    $nodemcu_ip = "192.168.1.100"; // Replace with your NodeMCU’s IP
    $url = "http://$nodemcu_ip/enroll";
    $data = json_encode(['finger_id' => $finger_id]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        echo json_encode(['error' => 'Failed to connect to NodeMCU']);
    } else {
        echo $response;
    }
    curl_close($ch);
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>