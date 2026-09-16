<?php
  date_default_timezone_set('America/Sao_Paulo');
  $servername = "localhost";
  $username = "root";
  $password = ""; 
  $dbname = "planta_industrial";
  $conn = new mysqli($servername, $username, $password, $dbname);

  if ($conn->connect_error) {
    echo json_encode(['error' => "Falha na conexão -> " . $conn->connect_error]);
    exit();
  }
?>