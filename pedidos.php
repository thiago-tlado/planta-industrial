<?php
    header('Content-Type: application/json');
    require_once('bd.php');
    $input = json_decode(file_get_contents('php://input'), true);
    $comando = isset($input['comando']) ? $input['comando'] : 'inserir';

    if($comando === 'inserir') {
        $pedido = isset($input['pedido']) ? $input['pedido'] : [];
        $pedido = array_map('intval', $pedido);
        $pedido = json_encode($pedido);
        $status = 'Pendente';
        $hora = date('Y-m-d H:i:s');
        $insertSql = "INSERT INTO pedidos (pedido, estatus, hora) VALUES (?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sss", $pedido, $status, $hora);
        $insertStmt->execute();
        echo listarPedidos();
        exit();
    } 
    elseif($comando === 'deletar') {
        $id = isset($input['id']) ? $input['id'] : 0;
        $deleteSql = "DELETE FROM pedidos WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        echo listarPedidos();
        exit();
    }
    elseif($comando === 'listar') {
        echo listarPedidos();
        exit();
    }

    function listarPedidos() {
        global $conn;
        $sql = "SELECT id, pedido, feitos, estatus, hora FROM pedidos ORDER BY hora DESC";
        $result = $conn->query($sql);
        $lista = [];
        while ($row = $result->fetch_assoc()) {
            $pedido = json_decode($row['pedido'], true);
            $feitos = json_decode($row['feitos'], true);
            if(empty($feitos)) $feitos = [0, 0, 0];
            if($pedido === $feitos) $row['estatus'] = str_replace('Fabricando', 'Finalizado', $row['estatus']);
            $lista[] = [
                'id' => $row['id'],
                'pedido' => $pedido,
                'feitos' => $feitos,
                'estatus' => $row['estatus'],
                'hora' => $row['hora']
            ];
        }
        return json_encode(['status' => 'sucesso', 'pedidos' => $lista]);
    }