import math

class RedeNeural:
    def __init__(self, w, func_oculta, func_saida):
        self.peso_entrada_oculta = (w['peso_entrada_oculta'] if 'peso_entrada_oculta' in w else [])     
        self.peso_oculta_saida = (w['peso_oculta_saida'] if 'peso_oculta_saida' in w else [])
        self.bias_oculta = (w['bias_oculta'] if 'bias_oculta' in w else [])
        self.bias_saida = (w['bias_saida'] if 'bias_saida' in w else [])
        self.entrada_tamanho = len(self.peso_entrada_oculta)
        self.saida_tamanho = len(self.bias_saida)
        self.oculta_tamanho = len(self.bias_oculta)
        self.func_oculta = func_oculta
        self.func_saida = func_saida

    def forward(self, entrada):
        self.entrada = entrada
        self.oculta_funcao = []     
        self.saida_funcao = []   

        for j in range(self.oculta_tamanho):
            total = sum(self.entrada[i] * self.peso_entrada_oculta[i][j] for i in range(self.entrada_tamanho)) 
            self.oculta_funcao.append(self.funcao_ativacao(total+self.bias_oculta[j],self.func_oculta))

        for j in range(self.saida_tamanho):
            total = sum(self.oculta_funcao[i] * self.peso_oculta_saida[i][j] for i in range(self.oculta_tamanho)) 
            self.saida_funcao.append(self.funcao_ativacao(total+self.bias_saida[j],self.func_saida))

        if self.func_saida == 'softmax':
            self.saida_funcao = self.softmax(self.saida_funcao)

        return self.saida_funcao
    
    def funcao_ativacao(self, x, func):
        if func == 'sigmoid':
            return 1 / (1 + math.exp(-x))
        elif func == 'tanh':
            return math.tanh(x)
        else:
            return x # Se for softmax
        
    def softmax(self, x):
        m = max(x)
        exp_x = [math.exp(v - m) for v in x]
        s = sum(exp_x)

        if s <= 0.0 or not math.isfinite(s):
            n = len(x)
            return [1.0 / max(1, n) for _ in range(n)]

        return [v / s for v in exp_x]