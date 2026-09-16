<?php
    header('Content-Type: application/json');
    require_once('bd.php');
    $input = json_decode(file_get_contents('php://input'), true);
    $comando = $input['comando'] ?? 'atualizar';
    $status = false;

    $hora = date('Y-m-d');
    $sql = "SELECT dados, comando FROM historico WHERE hora = ? ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hora);
    $stmt->execute();
    $result = $stmt->get_result();
    $maquinas = [];

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maquinas = json_decode($row['dados'], true);
        $status = $row['comando'];
    }

    $dados['hora'] = ($maquinas['hora']) ?? [];
    $len = (isset($dados['hora'])) ? count($dados['hora']) : 0;

    if($comando === 'acao') {
        $status = ($status) ? false : true;
        $updateSql = "UPDATE historico SET comando = ? WHERE hora = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $status, $hora);
        $updateStmt->execute();
    }
    else if($comando === 'listar') {
        $in = '_' . $input['rede'];
        $out = $input['rede'];
        $qtd = (isset($input['qtd'])) ? $input['qtd'] : 10;
        $dados = ['hora' => array_slice($dados['hora'] ?? [], -$qtd), 'saida' => [], 'entrada' => []];
    
        for($i = max($len-$qtd, 0); $i < $len; $i++) {
              $dados['saida'][] = (isset($maquinas[$out][$i])) ? implode(',', array_map(function($v){ return number_format($v, 2, '.', ''); }, $maquinas[$out][$i])) : '';
              $dados['entrada'][] = (isset($maquinas[$in][$i])) ? implode(',', array_map(function($v){ return number_format($v, 2, '.', ''); }, $maquinas[$in][$i])) : '';
        }          
    } else {
        $nomes = ['_despacho','_prioridade', '_esteira', '_elevador', '_expedicaoA', '_expedicaoB',
                    'despacho','prioridade', 'esteira', 'elevador', 'expedicaoA', 'expedicaoB'];
        $dados['hora'] = array_slice($dados['hora'] ?? [], -100);

        for($i = max($len-100, 0); $i < $len; $i++) {   
            foreach($nomes as $nome) {
                $saidas = (isset($maquinas[$nome][0])) ? count($maquinas[$nome][0]) : 0; 
                if(empty($dados[$nome]) && $saidas > 0) $dados[$nome] = []; 

                for($j = 0; $j < $saidas; $j++) {
                    if(empty($dados[$nome][$j])) $dados[$nome][$j] = [];
                    $dados[$nome][$j][] = $maquinas[$nome][$i][$j] ?? 0;
                }
            }
        }        
    }

    echo json_encode(['maquinas' => $dados, 'status' => $status]);