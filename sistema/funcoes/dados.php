<?php
    function listarTreinamento($entradas, $saidas) {
        $len = min(count($entradas), count($saidas));
        $lista = [];

        for ($i = 0; $i < $len; $i++) {
            $lista[] = [
                'id' => $i,
                'entrada' => implode(',', $entradas[$i]),
                'saida' => implode(',', $saidas[$i])
            ];
        }
        return $lista;
    }

    header('Content-Type: application/json');
    require_once('bd.php');

    $input = json_decode(file_get_contents('php://input'), true);
    $rede = isset($input['rede']) ? $input['rede'] : '';
    $versao = isset($input['versao']) ? $input['versao'] : '';
    $comando = isset($input['comando']) ? $input['comando'] : '';
    $coment = '...';

    $sql = "SELECT versao, hora FROM configs WHERE rede = ? ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $rede);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $v = json_decode($row['versao'], true);
        $t = date('d/m/Y H:i:s', strtotime($row['hora']));
        $coment = 'v'. $v.' '. $t;
    }

    $sql = "SELECT rede, versao, treino FROM treinamento WHERE rede = ? ORDER BY versao DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $rede);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($comando === 'inserir') {
        $entrada = isset($input['entrada']) ? $input['entrada'] : '';
        $saida = isset($input['saida']) ? $input['saida'] : '';

        if (!empty(trim($entrada)) && !empty(trim($saida))) {   
            $novaEntrada = array_map('floatval', array_map('trim', explode(',', $entrada)));
            $novoAlvo = array_map('floatval', array_map('trim', explode(',', $saida)));
            $ultimaVersao = 0;

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    if($row['versao'] == $versao) {
                        $json = $row['treino'];
                        $jsonDados = json_decode($json, true);
                        $jsonDados['entradas'][] = $novaEntrada;
                        $jsonDados['alvos'][] = $novoAlvo;
                        $json = json_encode($jsonDados);
                        $updateSql = "UPDATE treinamento SET treino = ? WHERE rede = ? AND versao = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("ssi", $json, $rede, $versao);
                        $updateStmt->execute();
                        $lista = listarTreinamento($jsonDados['entradas'], $jsonDados['alvos']);
                        echo json_encode(['status' => 'sucesso', 'coment' => $coment, 'versao' => $versao, 'lista' => $lista]);
                        exit();
                    }
                    elseif ($row['versao'] > $ultimaVersao) $ultimaVersao = $row['versao'];                    
                }
            }

            $entradas = [$novaEntrada];
            $alvos = [$novoAlvo];
            $novaVersao = $ultimaVersao + 1;           
            $json = json_encode(['entradas' => $entradas, 'alvos' => $alvos]);
            $insertSql = "INSERT INTO treinamento (rede, versao, treino) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("sis", $rede, $novaVersao, $json);
            $insertStmt->execute();
            $lista = listarTreinamento($entradas, $alvos);
            echo json_encode(['status' => 'sucesso', 'coment' => $coment, 'versao' => $novaVersao, 'lista' => $lista]);
        }
    } else {
        if ($result->num_rows > 0) {
            $treinos = [];
            $ultimaVersao = 0;
            while ($row = $result->fetch_assoc()) {
                if($row['rede'] == $rede) {
                    if($row['versao'] > $ultimaVersao) $ultimaVersao = $row['versao'];
                    if($comando === 'treinos') $treinos[] = $row['versao'];
                    elseif($row['versao'] == $versao) {
                        if($comando === 'remover') {
                            $updateSql = "DELETE FROM treinamento WHERE rede = ? AND versao = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            $updateStmt->bind_param("si", $rede, $versao);
                            $updateStmt->execute();
                            unset($treinos[array_search($versao, $treinos)]);
                            echo json_encode(['status' => 'sucesso']);
                            exit();
                        }

                        $json = $row['treino'];
                        $jsonDados = json_decode($json, true);

                        if($comando === 'clonar') {
                            $novaVersao = $ultimaVersao + 1;
                            $novoJson = json_encode(['entradas' => $jsonDados['entradas'], 'alvos' => $jsonDados['alvos']]);
                            $insertSql = "INSERT INTO treinamento (rede, versao, treino) VALUES (?, ?, ?)";
                            $insertStmt = $conn->prepare($insertSql);
                            $insertStmt->bind_param("sis", $rede, $novaVersao, $novoJson);
                            $insertStmt->execute();
                            $lista = listarTreinamento($jsonDados['entradas'], $jsonDados['alvos']);
                            echo json_encode(['status' => 'sucesso', 'coment' => $coment, 'versao' => $novaVersao, 'lista' => $lista]);
                            exit();
                        }

                        if($comando === 'deletar') {
                            $pos = isset($input['pos']) ? intval($input['pos']) : 0;
                            unset($jsonDados['entradas'][$pos]);
                            unset($jsonDados['alvos'][$pos]);
                            $jsonDados['entradas'] = array_values($jsonDados['entradas']);
                            $jsonDados['alvos'] = array_values($jsonDados['alvos']);
                            $json = json_encode($jsonDados);
                            $updateSql = "UPDATE treinamento SET treino = ? WHERE rede = ? AND versao = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            $updateStmt->bind_param("ssi", $json, $rede, $versao);
                            $updateStmt->execute();
                            $lista = listarTreinamento($jsonDados['entradas'], $jsonDados['alvos']);
                            echo json_encode(['status' => 'sucesso', 'coment' => $coment, 'lista' => $lista]);
                            exit();
                        } 

                        if($comando === 'listar') {
                            $lista = listarTreinamento($jsonDados['entradas'], $jsonDados['alvos']);
                            echo json_encode(['status' => 'sucesso', 'coment' => $coment, 'lista' => $lista]);
                            exit();
                        }
                    }
                }
            }

            echo json_encode(['status' => 'sucesso', 'coment' => $coment, 'treinos' => $treinos]);   
            exit();         
        } else {
            echo json_encode(['status' => 'erro']);
        }
    }