<?php
    require_once('bd.php');
    class RN {
        private $entrada_tamanho;
        private $saida_tamanho;
        private $oculta_tamanho;
        private $func_oculta;
        private $func_saida;
        private $rede;
        private $entrada;
        private $oculta_funcao;
        private $saida_funcao;
        private $resultados;
        private $erro;
        private $versao;
        private $dados;

        private $peso_entrada_oculta;
        private $peso_oculta_saida;
        private $bias_oculta;
        private $bias_saida;

        public function valor_aleatorio() {
            return (float)number_format((mt_rand() / mt_getrandmax()) * 2 - 1, 3); // Valores entre -1 e 1
        }

        private function iniciar_matriz($linhas, $colunas) {
            $pesos = [];

            for ($i = 0; $i < $linhas; $i++) {
                $pesos[$i] = [];
                for ($j = 0; $j < $colunas; $j++) 
                    $pesos[$i][$j] = $this->valor_aleatorio();
            }

            return $pesos;
        }

        public function __construct($entrada_tamanho, $saida_tamanho, $func_oculta, $func_saida, $rede, $versao=0) {
            $this->entrada_tamanho = $entrada_tamanho;
            $this->saida_tamanho = $saida_tamanho;
            $this->oculta_tamanho = (int)(ceil(($entrada_tamanho+$saida_tamanho)/2)+1);
            $this->func_oculta = $func_oculta;
            $this->func_saida = $func_saida;
            $this->rede = $rede;
            $this->versao = $versao;
            global $conn; 
               
            $sql = "SELECT dados FROM treinamento WHERE rede = ? AND versao = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $rede, $versao);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $json = $row['dados'];
                $this->dados = json_decode($json, true);    
            } else {
                $this->dados = null;
            }

            if ($this->dados !== null) {              
                $this->peso_entrada_oculta = end($this->dados['peso_entrada_oculta']);
                $this->peso_oculta_saida = end($this->dados['peso_oculta_saida']);
                $this->bias_oculta = end($this->dados['bias_oculta']);
                $this->bias_saida = end($this->dados['bias_saida']);
            } else {                
                $this->peso_entrada_oculta = $this->iniciar_matriz($this->entrada_tamanho, $this->oculta_tamanho);
                $this->peso_oculta_saida = $this->iniciar_matriz($this->oculta_tamanho, $this->saida_tamanho);
                $this->bias_oculta = [];
                $this->bias_saida = [];

                for ($i = 0; $i < $this->oculta_tamanho; $i++) $this->bias_oculta[] = $this->valor_aleatorio();
                for ($i = 0; $i < $this->saida_tamanho; $i++) $this->bias_saida[] = $this->valor_aleatorio();
            }
        }

        private function funcao_ativacao($x, $func) {
            switch ($func) {
                case 'sigmoid':
                    return 1 / (1 + exp(-$x));
                case 'tanh':
                    return tanh($x);
                default:
                    return $x;
            }        
        }

        private function softmax(array $x): array {
            $max = max($x); 
            $exp_x = [];
            $sum = 0.0;

            foreach ($x as $v) {
                $e = exp($v - $max); 
                $exp_x[] = $e;
                $sum += $e;
            }

            if ($sum <= 0.0 || !is_finite($sum)) {
                $n = count($x);
                return array_fill(0, $n, 1.0 / max(1, $n));
            }

            return array_map(fn($e) => $e / $sum, $exp_x);
        }

        private function derivada_funcao($x, $func) {
            switch ($func) {
                case 'sigmoid':
                    return $x * (1 - $x);
                case 'tanh':
                    return 1 - pow($x, 2);
                default:
                    return 1;
            }
        }

        private function loss_funcao($saida, $alvo) {
            if ($this->func_saida === 'softmax') {
                $eps = 1e-12;
                $saida = max($eps, min(1.0 - $eps, (float)$saida));
                return -log($saida) * (float)$alvo;
            }
            return 0.5 * pow(((float)$saida - (float)$alvo), 2);
        }

        public function forward($entrada) {
            $this->entrada = $entrada;
            $this->oculta_funcao = [];
            $this->saida_funcao = [];

            // Camada oculta
            for ($j = 0; $j < $this->oculta_tamanho; $j++) {
                $total = $this->bias_oculta[$j];
                for ($i = 0; $i < $this->entrada_tamanho; $i++)
                    $total += $this->entrada[$i] * $this->peso_entrada_oculta[$i][$j];           
                $this->oculta_funcao[$j] = $this->funcao_ativacao($total, $this->func_oculta);
            }

            // Camada de saída
            for ($i = 0; $i < $this->saida_tamanho; $i++) {
                $total = $this->bias_saida[$i];
                for ($j = 0; $j < $this->oculta_tamanho; $j++) {
                    $total += $this->oculta_funcao[$j] * $this->peso_oculta_saida[$j][$i];
                }
                $this->saida_funcao[$i] = $this->funcao_ativacao($total, $this->func_saida);
            }

            if ($this->func_saida === 'softmax') 
                $this->saida_funcao = $this->softmax($this->saida_funcao);

            return $this->saida_funcao;
        }

        private function backpropagation($alvo, $lr) {
            $this->erro = [];

            for ($i = 0; $i < $this->saida_tamanho; $i++) {      
                $this->erro[] = $this->loss_funcao($this->saida_funcao[$i], $alvo[$i]);
                $de = $this->saida_funcao[$i] - $alvo[$i];
                $dy = $this->derivada_funcao($this->saida_funcao[$i], $this->func_saida);
                $this->bias_saida[$i] -= $lr * $dy * $de;

                for ($j = 0; $j < $this->oculta_tamanho; $j++) {
                    $dn = $this->derivada_funcao($this->oculta_funcao[$j], $this->func_oculta);
                    $this->bias_oculta[$j] -= $lr * $this->peso_oculta_saida[$j][$i] * $dn * $dy * $de;

                    for ($k = 0; $k < $this->entrada_tamanho; $k++) 
                        $this->peso_entrada_oculta[$k][$j] -= $lr * $this->peso_oculta_saida[$j][$i] * $this->entrada[$k] * $dn * $dy * $de;
                    
                    $this->peso_oculta_saida[$j][$i] -= $lr * $this->oculta_funcao[$j] * $dy * $de;
                }
            }
        }

        private function backpropagation2($alvo, $lr) {
            $this->erro = [];
            $delta_saida = [];
            $delta_oculta = [];

            for ($i = 0; $i < $this->saida_tamanho; $i++) {
                $this->erro[] = $this->loss_funcao($this->saida_funcao[$i], $alvo[$i]);
                $de = $this->saida_funcao[$i] - $alvo[$i];
                $dy = $this->derivada_funcao($this->saida_funcao[$i], $this->func_saida);
                $delta_saida[] = $dy * $de;
            }

            for ($j = 0; $j < $this->oculta_tamanho; $j++) {
                $soma = 0;

                for ($i = 0; $i < $this->saida_tamanho; $i++)      
                    $soma += $this->peso_oculta_saida[$j][$i] * $delta_saida[$i];                

                $dn = $this->derivada_funcao($this->oculta_funcao[$j], $this->func_oculta);
                $delta_oculta[] = $dn * $soma;
            }

            for ($i = 0; $i < $this->saida_tamanho; $i++) {
                for ($j = 0; $j < $this->oculta_tamanho; $j++) 
                    $this->peso_oculta_saida[$j][$i] -= $lr * $this->oculta_funcao[$j] * $delta_saida[$i]; 

                $this->bias_saida[$i] -= $lr * $delta_saida[$i];  
            }

            for ($j = 0; $j < $this->oculta_tamanho; $j++) {
                for ($k = 0; $k < $this->entrada_tamanho; $k++)               
                    $this->peso_entrada_oculta[$k][$j] -= $lr * $this->entrada[$k] * $delta_oculta[$j];   

                $this->bias_oculta[$j] -= $lr * $delta_oculta[$j];
            }
        }

        public function treinamento($matriz_entradas, $matriz_alvos, $epocas, $lr, $pontos=10) {
            $tamanho = count($matriz_entradas);
            $last = isset($this->dados['epocas']) ? end($this->dados['epocas']) : 0;
                    
            for ($epoca = 0; $epoca <= $epocas; $epoca++) {
                $acertos = [];

                for ($k = 0; $k < $this->saida_tamanho; $k++) 
                    $acertos[] = 0;                

                for ($i = 0; $i < $tamanho; $i++) {
                    $this->resultados = $this->forward($matriz_entradas[$i]);        
                    $this->backpropagation2($matriz_alvos[$i], $lr);
                    $acertos = $this->verificar_acertos($matriz_alvos[$i], $acertos);                    
                }

                if($pontos > 0)
                    if ($epoca % (int)($epocas / $pontos) == 0) {
                        $absoluto = array_sum(array_map('abs', $this->erro)) / count($this->erro);
                        $this->salvar_dados($tamanho, $acertos, $absoluto, $epoca, $last);
                    }
            }
            
            global $conn;
            $json = json_encode($this->dados);
            $updateSql = "UPDATE treinamento SET dados = ? WHERE rede = ? AND versao = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssi", $json, $this->rede, $this->versao);
            $updateStmt->execute();
            return $this->dados;
        }

        private function salvar_dados($tamanho, $acertos, $erro, $epoca, $last) {
            if ($this->dados == null) {
                $this->dados = [
                    'tamanho' => [],
                    'epocas' => [],
                    'erro_atual' => [],
                    'erro_geral' => [],
                    'acertos' => [],
                    'peso_entrada_oculta' => [],
                    'bias_oculta' => [],
                    'peso_oculta_saida' => [],
                    'bias_saida' => []
                ];
            }

            $this->dados['tamanho'][] = $tamanho;
            $this->dados['epocas'][] = $last + $epoca;
            $this->dados['erro_atual'][] = $this->erro;
            $this->dados['erro_geral'][] = $erro;
            $this->dados['acertos'][] = $acertos;
            $this->dados['peso_entrada_oculta'][] = $this->peso_entrada_oculta;
            $this->dados['bias_oculta'][] = $this->bias_oculta;
            $this->dados['peso_oculta_saida'][] = $this->peso_oculta_saida;
            $this->dados['bias_saida'][] = $this->bias_saida;
        }

        public function carregarDados() {
            global $conn;

            $sql = "SELECT dados FROM treinamento WHERE rede = ? AND versao = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $this->rede, $this->versao);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $json = $row['dados'];
                return json_decode($json, true);    
            } else return [];
            
        }

        public function verificar_acertos($alvos, &$acertos) {
            if ($this->func_saida == 'softmax') 
                $this->resultados = $this->filtrar_maior($this->resultados);    
            
            $tolerancia = 0.05; // % de tolerância para considerar um resultado como acerto

            for ($i = 0; $i < count($this->resultados); $i++) {
                $resul = $this->resultados[$i];
                $min = $alvos[$i] - $tolerancia;
                $max = $alvos[$i] + $tolerancia;

                if ($resul >= $min && $resul <= $max) 
                    $acertos[$i]++;   
            }

            return $acertos;
        }

        public function filtrar_maior($valores){
            $max_id = array_search(max($valores), $valores);
            for ($i = 0; $i < count($valores); $i++) 
                $valores[$i] = ($i === $max_id) ? 1 : 0;
            return $valores;
        }
    }
?>