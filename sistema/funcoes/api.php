<?php
    header('Content-Type: application/json');
    require_once('bd.php');
    define('OPENAI_API_KEY', '');

    $total = [0,0,0];
    $expA = [0,0,0];
    $expB = [0,0,0];
    $elev = [0,0,0];
    $tempo = [0,0,0,0,0,0,0];

    $data = date('Y-m-d');
    $sql = "SELECT dados FROM historico WHERE hora = ? ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $data);
    $stmt->execute();
    $result = $stmt->get_result();
    $dados = [];
    $teste = [];

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $dados = json_decode($row['dados'], true);
        $contagem = $dados['contagem'] ?? [];

        $total = $contagem['total'] ?? [0,0,0];
        $expA = $contagem['expA'] ?? [0,0,0];
        $expB = $contagem['expB'] ?? [0,0,0];
        $elev = $contagem['elev'] ?? [0,0,0];
        $tempo = $contagem['tempo'] ?? [0,0,0,0,0,0,0];
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $comando = $input['comando'] ?? '';
    $api = "...";

    if($comando === 'api') {
        $limiteArquivo = sys_get_temp_dir() . '/planta_api_relatorio.lock';
        $ultimoUso = file_exists($limiteArquivo) ? (int)file_get_contents($limiteArquivo) : 0;
        $intervaloMinimo = 15; // segundos mínimos entre chamadas à API paga

        if (time() - $ultimoUso < $intervaloMinimo) {
            echo json_encode([
                'total' => $total, 'elev' => $elev, 'expA' => $expA, 'expB' => $expB,
                'tempo' => $tempo,
                'api' => 'Aguarde alguns segundos antes de gerar outro relatório.'
            ]);
            exit();
        }
        file_put_contents($limiteArquivo, time());
        $apiKey = OPENAI_API_KEY;


$systemPrompt = '
    Você é um assistente técnico de automação industrial.
    Sua tarefa é analisar dados resumidos de uma planta industrial e gerar um relatório operacional objetivo, 
    técnico e claro, em português do Brasil.

    Regras:
    - Considere que os vetores representam contagens de itens X, Y e Z, nesta ordem.
    - Leve em consideração que os produtos X, Y e Z entram no sistema de forma aleatória.
    - Considere que a ordem de entrada dos produtos pode afetar os tempos médios observados.
    - Destaque produção total, distribuição para elevador, esteira A e esteira B.
    - Esteiras A e B são responsaveis por atendimento de pedidos solicitados.
    - Elevador é o armazenamento dos produtos que entram no sistema e não são necessários nas esteiras A ou B.
    - Analise os tempos médios entre produtos no destino informado.
    - Aponte balanceamento ou desequilíbrio entre os destinos.
    - Aponte possíveis gargalos de forma cautelosa, sem afirmar falhas inexistentes.
    - Não invente dados que não existem.
    - Seja técnico, mas com texto fácil de entender.
    - A resposta deve ser em texto corrido, sem tópicos ou listas.
';

$userPrompt = "
    Analise os seguintes dados resumidos da planta industrial.

    Interpretação dos vetores:
    - total = quantidade total produzida de itens [X, Y, Z]
    - elev = quantidade de itens [X, Y, Z] enviados para o elevador
    - expA = quantidade de itens [X, Y, Z] enviados para a esteira A
    - expB = quantidade de itens [X, Y, Z] enviados para a esteira B
    
    Interpretação do vetor tempo:
    tempo = [medio_x, medio_y, medio_z, medio_total, medio_A, medio_B, medio_elevador]
    As médias de tempo refere-se ao intervalo médio entre a entrada dos itens: (ultimo-primeiro)/(total-1).

    Dados:
    total = [" . implode(',', $total) . "]
    elev = [" . implode(',', $elev) . "]
    expA = [" . implode(',', $expA) . "]
    expB = [" . implode(',', $expB) . "]
    tempo = [" . implode(',', $tempo) . "]

    Gere um relatório operacional resumido.
    Compare produção, distribuição e tempos.
    Faça análise de aproveitamento percentual de cada destinação.
    Aponte qual item aparenta maior participação no processo, 
    qual destino aparenta maior uso e se os tempos apresentam bom atendimento para A e B.
    Analise se muitos itens estão sendo direcionados para o elevador em vez das esteiras.
    Analise se os itens inseridos estão sendo direcionados corretamente.
    Retorne somente texto corrido, sem tópicos ou listas.";

        $messages = [
            [
                "role" => "system",
                "content" => $systemPrompt
            ],   
            [
                "role" => "user",
                "content" => $userPrompt
            ]
        ];

        $data = [
            "model" => "gpt-4o-mini", 
            "messages" => $messages,
            "temperature" => 0.5
        ];

        $url = "https://api.openai.com/v1/chat/completions";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ]);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        $api = $result["choices"][0]["message"]["content"] ?? "Erro na resposta da API";
        $insertSql = "INSERT INTO api (sistema, usuario, dados, resposta) VALUES (?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ssss", $systemPrompt, $userPrompt, $response, $api);
        $insertStmt->execute();  
    }

    echo json_encode([
        'total' => $total,
        'elev' => $elev,
        'expA' => $expA,
        'expB' => $expB,
        'tempo' => $tempo,
        'api' => $api
    ]);