<?php
    header('Content-Type: application/json');
    require_once('bd.php');

    // Contagem cumulativa de produção/expedição, atualizada de forma incremental
    // (uma amostra por chamada) em vez de recalculada por varredura do histórico.
    function contagemPadrao() {
        return [
            'total' => [0, 0, 0], 'expA' => [0, 0, 0], 'expB' => [0, 0, 0], 'elev' => [0, 0, 0],
            'tempo' => [0, 0, 0, 0, 0, 0, 0],
            'lastMain' => 0, 'lastA' => 0, 'lastB' => 0, 'lastElev' => 0,
            'primeiroMain' => 0, 'ultimoMain' => 0,
            'primeiroA' => 0, 'ultimoA' => 0,
            'primeiroB' => 0, 'ultimoB' => 0,
            'primeiroElev' => 0, 'ultimoElev' => 0,
            'primeiroX' => 0, 'ultimoX' => 0,
            'primeiroY' => 0, 'ultimoY' => 0,
            'primeiroZ' => 0, 'ultimoZ' => 0
        ];
    }

    // Tempo médio entre itens = (último horário do dia - primeiro horário do dia) / (quantidade - 1).
    // Ex.: 3 itens com o 1º em t=0, 2º em t=30 e 3º em t=40 -> (40-0)/(3-1) = 20s de intervalo médio.
    // $tipos: só o fluxo 'total' quebra a média por item X/Y/Z (tempo[0..2]); os demais
    // destinos (expA/expB/elev) têm sua própria média isolada, sem misturar timestamps entre si.
    function contarItem(&$contagem, $item, $hr, $arrKey, $lastKey, $primeiroKey, $ultimoKey, $t, $tipos = false) {
        if ($item == $contagem[$lastKey]) return;

        if ($item >= 0.15) {
            $idx = null;
            if ($item < 0.35) $idx = 0;
            else if ($item < 0.65) $idx = 1;
            else if ($item < 0.95) $idx = 2;

            if ($idx !== null) {
                $contagem[$arrKey][$idx]++;

                if ($tipos) {
                    $primeiroTipo = ['primeiroX', 'primeiroY', 'primeiroZ'][$idx];
                    $ultimoTipo = ['ultimoX', 'ultimoY', 'ultimoZ'][$idx];
                    if ($contagem[$primeiroTipo] === 0) $contagem[$primeiroTipo] = $hr;
                    $contagem[$ultimoTipo] = $hr;
                    if ($contagem[$arrKey][$idx] > 1) $contagem['tempo'][$idx] = (float)number_format(($contagem[$ultimoTipo] - $contagem[$primeiroTipo]) / ($contagem[$arrKey][$idx] - 1), 2);
                }
            }

            if ($contagem[$primeiroKey] === 0) $contagem[$primeiroKey] = $hr;
            $contagem[$ultimoKey] = $hr;
            $soma = $contagem[$arrKey][0] + $contagem[$arrKey][1] + $contagem[$arrKey][2];
            if ($soma > 1) $contagem['tempo'][$t] = (float)number_format(($contagem[$ultimoKey] - $contagem[$primeiroKey]) / ($soma - 1), 2);
        }

        $contagem[$lastKey] = $item;
    }

    // $est: amostra única de _esteira -> [prodMain, prodA, prodB, prodElev, prioA, prioB, prioElev, nivel]
    function atualizarContagem($contagem, $est, $hr) {
        if (!is_array($est) || count($est) <= 6) return $contagem;

        $contagem = array_merge(contagemPadrao(), $contagem);

        contarItem($contagem, $est[0], $hr, 'total', 'lastMain', 'primeiroMain', 'ultimoMain', 3, true);
        contarItem($contagem, $est[1], $hr, 'expA', 'lastA', 'primeiroA', 'ultimoA', 4);
        contarItem($contagem, $est[2], $hr, 'expB', 'lastB', 'primeiroB', 'ultimoB', 5);
        contarItem($contagem, $est[3], $hr, 'elev', 'lastElev', 'primeiroElev', 'ultimoElev', 6);

        return $contagem;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $pedidos = isset($input['pedidos']) ? [$input['pedidos']['pedidoA'], $input['pedidos']['pedidoB']] : [];

    foreach ($pedidos as $pedido) {
        $id = $pedido['id'];
        $feitos = json_encode($pedido['feitos']);
        $estatus = $pedido['status'];
        $time = date('Y-m-d H:i:s');
        $updateSql = "UPDATE pedidos SET feitos = ?, estatus = ?, hora = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("sssi", $feitos, $estatus, $time, $id);
        $updateStmt->execute();
    }

    $encomendas = [0,0,0,0];
    $sql = "SELECT pedido, id FROM pedidos WHERE feitos IS NULL ORDER BY id ASC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $encomendas = array_merge([$row['id']], json_decode($row['pedido'], true));
        }
    }

    $comando = false;
    $hora = date('Y-m-d');
    $sql = "SELECT dados, comando FROM historico WHERE hora = ? ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hora);
    $stmt->execute();
    $result = $stmt->get_result();

    $linhaExiste = $result->num_rows > 0;

    if ($linhaExiste) {
        $row = $result->fetch_assoc();
        $maquinas = json_decode($row['dados'], true);
        $comando = $row['comando'];
    } else {
        $maquinas = ['contagem' => contagemPadrao()];
    }

    if (isset($input['maquinas'])) {
        $horario = $input['maquinas']['hora'][0] ?? 0;
        $maquinas['contagem'] = atualizarContagem($maquinas['contagem'] ?? contagemPadrao(), $input['maquinas']['_esteira'] ?? [], $horario);

        foreach ($input['maquinas'] as $nome => $arr) {
            if(count($arr) === 0) continue;
            $maquinas[$nome][] = $arr;
            if(count($maquinas[$nome]) > 180) $maquinas[$nome] = array_slice($maquinas[$nome], -150);
        }
    }

    $maquinas = json_encode($maquinas);

    if ($linhaExiste) {
        $updateSql = "UPDATE historico SET dados = ? WHERE hora = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $maquinas, $hora);
        $updateStmt->execute();
    } else {
        $insertSql = "INSERT INTO historico (dados, comando, hora) VALUES (?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sss", $maquinas, $comando, $hora);
        $insertStmt->execute();
    }

    $redes = ["despacho","prioridade", "esteira", "elevador", "expedicao"];
    $dados = [];
    $versao = 0;

    foreach ($redes as $rede) {
        $sql = "SELECT dados, versao FROM configs WHERE rede = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $rede);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $dados[$rede] = json_decode($row['dados'], true);
            if($rede == "prioridade") $versao = $row['versao'];
        } else $dados[$rede] = [0]; 
    } 

    $resposta = ["encomendas" => $encomendas, "comando" => $comando, "dados" => $dados];
    echo json_encode($resposta);