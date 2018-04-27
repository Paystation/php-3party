<?php
require_once '../src/init.php';

$postBody = file_get_contents("php://input");
$paystation->savePostResponse($postBody);
