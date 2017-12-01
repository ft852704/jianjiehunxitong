<?php
$input_data = json_decode(file_get_contents('php://input' ), true);
var_dump($input_data);
?>