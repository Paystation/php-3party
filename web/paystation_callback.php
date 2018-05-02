<?php
require_once __DIR__ . '/../src/init.php';

$postBody = file_get_contents("php://input");
$paystation->savePostResponse($postBody);
