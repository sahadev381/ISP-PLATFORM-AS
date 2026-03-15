<?php
$data = json_decode(file_get_contents("php://input"), true);

if ($data['event'] === 'subscribed') {
    file_put_contents("user_id.txt", $data['user']['id']);
}

