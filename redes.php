<?php
    header('Content-Type: application/json');
    include_once 'RN.php';
    include_once 'bd.php';    

    $input = json_decode(file_get_contents('php://input'), true);
    $rede = isset($input['rede']) ? $input['rede'] : '';
    $versao = isset($input['versao']) ? $input['versao'] : '';
    $comando = isset($input['comando']) ? $input['comando'] : 'treinar';

    $sql = "SELECT treino FROM treinamento WHERE rede = ? AND versao = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $rede, $versao);
    $stmt->execute();
    $result = $stmt->get_result();    

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if($comando === 'resetar') {
            $dados = '';
            $updateSql = "UPDATE treinamento SET dados = ? WHERE rede = ? AND versao = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssi", $dados, $rede, $versao);
            $updateStmt->execute();
            echo json_encode(['message' => 'Treinamento reiniciado.']);
            exit();
        }

        $json = $row['treino'];
        $jsonDados = json_decode($json, true);
        $entradas = isset($jsonDados['entradas']) ? $jsonDados['entradas'] : [];
        $alvos = isset($jsonDados['alvos']) ? $jsonDados['alvos'] : [];
        $entrada_tamanho = (isset($entradas[0]) ? count($entradas[0]) : 0);
        $saida_tamanho = (isset($alvos[0]) ? count($alvos[0]) : 0);
        
        $func_oculta = 'tanh';
        $func_saida = ($rede === 'prioridade') ? 'softmax' : 'sigmoid';
        $rn = new RN($entrada_tamanho, $saida_tamanho, $func_oculta, $func_saida, $rede, $versao);   
        $json = [];             

        if($comando === 'teste') {
            $teste = isset($input['teste']) ? $input['teste'] : [];
            $teste = explode(',', $teste);
            $json = $rn->forward($teste);
        }
        else if($comando === 'treinar') {
            $epocas = isset($input['epocas']) ? $input['epocas'] : 10000;
            $taxa = isset($input['taxa']) ? $input['taxa'] : 0.5;               
            $json = $rn->treinamento($entradas, $alvos, $epocas, $taxa);
        }
        else if($comando === 'iniciar') $json = $rn->carregarDados();  
        else if($comando === 'usar') {
            $json = $rn->carregarDados(); 
            $json['coment'] = 'v'. $versao.' '. date('d/m H:i:s');
            $dados = [];
            $dados['peso_entrada_oculta'] = end($json['peso_entrada_oculta']);
            $dados['bias_oculta'] = end($json['bias_oculta']);
            $dados['peso_oculta_saida'] = end($json['peso_oculta_saida']);
            $dados['bias_saida'] = end($json['bias_saida']);
            $json['dados'] = $dados;
            $dados = json_encode($dados);

            $insertSql = "INSERT INTO configs (rede, versao, dados) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("sis", $rede, $versao, $dados);
            $insertStmt->execute();  
        } 

        echo json_encode($json);
    } else {     
        echo json_encode(['message' => 'Arquivo de treino não encontrado.']);
    }
?>