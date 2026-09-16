import time
from MB_copy import ModBus
from RN import RedeNeural
from HTTP import http
import threading

pesos = {'despacho': [], 'prioridade': [], 'esteira': [], 'expedicao': [], 'elevador': []}
maquinas = {'hora': [], 
            '_expedicaoA': [], 'expedicaoA': [],
            '_expedicaoB': [], 'expedicaoB': [],
            '_elevador': [], 'elevador': [],
            '_esteira': [], 'esteira': [],
            '_prioridade': [], 'prioridade': [],
            '_despacho': [], 'despacho': []}
pedidos = {'pedidoA': {'id': 0, 'encomenda': [0, 0, 0], 'feitos': [0, 0, 0], 'status': '.', 'inicio': 0, 'time': 0}, 
           'pedidoB': {'id': 0, 'encomenda': [0, 0, 0], 'feitos': [0, 0, 0], 'status': '.', 'inicio': 0, 'time': 0}}
encomendas = [0,0,0,0]
comando = 0
tempos_loop = []
tempos_entre_requisicoes = []

def gerenciarPedidos(encomendas, pedidos):
    pedido = encomendas.copy()
    pedido.pop(0)
    producao = ['pedidoA', 'pedidoB']

    for prod in producao:
        if pedidos[prod]['id'] == 0 and encomendas[0] > 0:
            if pedidos['pedidoA']['id'] != encomendas[0] and pedidos['pedidoB']['id'] != encomendas[0]:
                pedidos[prod]['inicio'] = time.time()
                pedidos[prod]['time'] = 0
                pedidos[prod]['encomenda'] = pedido.copy()
                pedidos[prod]['feitos'] = [0,0,0]
                pedidos[prod]['status'] = f'Fabricando em {prod[-1]}'
                pedidos[prod]['id'] = encomendas[0]
                break
        elif pedidos[prod]['encomenda'][0] <= pedidos[prod]['feitos'][0]:
            if pedidos[prod]['encomenda'][1] <= pedidos[prod]['feitos'][1]:
                if pedidos[prod]['encomenda'][2] <= pedidos[prod]['feitos'][2]:
                    pedidos[prod]['status'] = f'Finalizado em {prod[-1]}'
                    pedidos[prod]['id'] = 0            

    return pedidos

def producaoCnt(feitos, item):
    if item < 0.15:
        return feitos
    
    if item < 0.35:
        feitos[0] += 1
    elif item < 0.65:
        feitos[1] += 1
    elif item < 0.95:
        feitos[2] += 1

    return feitos

def producaoTime(item):
    global pedidos 
    now = time.time()

    tA = now - pedidos['pedidoA']['inicio']
    tB = now - pedidos['pedidoB']['inicio']
    max_time = max(tA, tB)

    if max_time == 0:
        max_time = 0.00001

    pedidos['pedidoA']['time'] = tA/max_time
    pedidos['pedidoB']['time'] = tB/max_time

def site():
    global comando, encomendas, pesos, pedidos, maquinas
    while True:
        comando, encomendas, pesos = http(pedidos=pedidos, maquinas=maquinas)
        pedidos = gerenciarPedidos(encomendas, pedidos)
        time.sleep(1)
 
def binario(valor):
    return 1 if valor > 0.95 else 0

def maiorValor(lista):
    max_valor = max(lista)
    return [1 if v == max_valor else 0 for v in lista]

mb_tempo = None

def plantaIndustrial():
    global mb_tempo, tempos_loop, tempos_entre_requisicoes
    qtd_amostras_tempo = 200  # quantidade de amostras de tempo coletadas (apenas as 200 primeiras); depois disso a medição para, mas a planta continua rodando
    mb = ModBus(ip='127.0.0.1')
    mb_tempo = mb
    prodMain = 0
    prodA = 0
    prodB = 0
    prodElev = 0

    faltamA = 0
    faltamB = 0

    medindo = True
    tempo_requisicao_anterior = None

    def parar_medicao_e_salvar():
        nonlocal medindo
        if not medindo:
            return
        medindo = False
        mb.medindo_escrita = False
        mb.salvar_tempos_escrita()
        with open('tempos_modbus.txt', 'a', encoding='utf-8') as dados:
            if tempos_loop:
                menor_loop = min(tempos_loop)
                maior_loop = max(tempos_loop)
                media_loop = sum(tempos_loop) / len(tempos_loop)
                dados.write('\nTempo de processamento do loop (planta industrial) (ms)\n')
                dados.write('Menor (ms);Maior (ms);Media (ms);Quantidade\n')
                dados.write(
                    f'{menor_loop:.3f};{maior_loop:.3f};{media_loop:.3f};{len(tempos_loop)}\n'
                )
            if tempos_entre_requisicoes:
                menor_req = min(tempos_entre_requisicoes)
                maior_req = max(tempos_entre_requisicoes)
                media_req = sum(tempos_entre_requisicoes) / len(tempos_entre_requisicoes)
                dados.write('\nTempo total entre requisicoes, incluindo delay de 50ms (ms)\n')
                dados.write('Menor (ms);Maior (ms);Media (ms);Quantidade;Delay configurado (ms)\n')
                dados.write(
                    f'{menor_req:.3f};{maior_req:.3f};{media_req:.3f};{len(tempos_entre_requisicoes)};50.000\n'
                )
        print(f'Medição concluída: {qtd_amostras_tempo} primeiras amostras registradas em tempos_modbus.txt')

    def registrar_tempo_loop(inicio):
        if not medindo:
            return
        tempo_loop = (time.perf_counter() - inicio) * 1000
        tempos_loop.append(tempo_loop)
        if len(tempos_loop) >= qtd_amostras_tempo:
            parar_medicao_e_salvar()

    while True:
        inicio_loop = time.perf_counter()

        if medindo:
            if tempo_requisicao_anterior is not None:
                tempos_entre_requisicoes.append((inicio_loop - tempo_requisicao_anterior) * 1000)
            tempo_requisicao_anterior = inicio_loop

        if not comando:
            maquinas['expedicaoA'] = [0 for _ in range(len(maquinas['expedicaoA']))]
            maquinas['expedicaoB'] = [0 for _ in range(len(maquinas['expedicaoB']))]
            maquinas['elevador'] = [0 for _ in range(len(maquinas['elevador']))]
            maquinas['esteira'] = [0 for _ in range(len(maquinas['esteira']))]
            maquinas['prioridade'] = [0 for _ in range(len(maquinas['prioridade']))]
            maquinas['despacho'] = [0 for _ in range(len(maquinas['despacho']))]
            time.sleep(0.050)
            continue

       # print(f'Pedidos: {pedidos}')
        mb.leitura()
        if not mb.conexao:
            time.sleep(0.050)
            continue

        despacho = RedeNeural(w=pesos['despacho'], func_oculta='tanh', func_saida='sigmoid')
        esteira = RedeNeural(w=pesos['esteira'], func_oculta='tanh', func_saida='sigmoid')
        prioridade = RedeNeural(w=pesos['prioridade'], func_oculta='tanh', func_saida='softmax')
        expedicao = RedeNeural(w=pesos['expedicao'], func_oculta='tanh', func_saida='sigmoid')
        elevador = RedeNeural(w=pesos['elevador'], func_oculta='tanh', func_saida='sigmoid')        

        if mb.ana.registers[0] > 0 and prodMain == 0:
            prodMain = mb.ana.registers[0] * 0.1           

        producaoTime(prodMain)
        tA = pedidos['pedidoA']['time']
        tB = pedidos['pedidoB']['time']
        xA = (pedidos['pedidoA']['encomenda'][0] - pedidos['pedidoA']['feitos'][0]) * 0.1
        yA = (pedidos['pedidoA']['encomenda'][1] - pedidos['pedidoA']['feitos'][1]) * 0.1
        zA = (pedidos['pedidoA']['encomenda'][2] - pedidos['pedidoA']['feitos'][2]) * 0.1
        xB = (pedidos['pedidoB']['encomenda'][0] - pedidos['pedidoB']['feitos'][0]) * 0.1
        yB = (pedidos['pedidoB']['encomenda'][1] - pedidos['pedidoB']['feitos'][1]) * 0.1
        zB = (pedidos['pedidoB']['encomenda'][2] - pedidos['pedidoB']['feitos'][2]) * 0.1
        # [Faltam em A, Faltam em B, Tempo A, Tempo B]
        _prioID = [0,0,0,0]

        if prodMain < 0.15:
             _prioID = [0, 0, 0, 0]
        elif prodMain < 0.35:
            _prioID = [xA, xB, tA, tB]
        elif prodMain < 0.65:
            _prioID = [yA, yB, tA, tB]
        elif prodMain < 0.95:
            _prioID = [zA, zB, tA, tB]

        prioID = prioridade.forward(_prioID) if prioridade.entrada_tamanho == len(_prioID) else [0 for _ in range(prioridade.saida_tamanho)]
        maquinas['_prioridade'] = _prioID
        maquinas['prioridade'] = prioID
        print(f"prioridade: {_prioID} -> {prioID}")
        prioID = maiorValor(prioID) if len(prioID) == 3 else [0 for _ in range(3)]
       # prioID = [0,0,1]
        if len(prioID) == 3:
            mb.escrever_coil(rede='prioridade', saida='coil_1', address=1, value=prioID[0]) # Prioridade A (pivot)
            mb.escrever_coil(rede='prioridade', saida='coil_2', address=2, value=prioID[0]) # Prioridade A (movimento)
            mb.escrever_coil(rede='prioridade', saida='coil_3', address=3, value=prioID[1]) # Prioridade B (pivot)
            mb.escrever_coil(rede='prioridade', saida='coil_4', address=4, value=prioID[1]) # Prioridade B (movimento)
            mb.escrever_coil(rede='prioridade', saida='coil_5', address=5, value=prioID[2]) # Prioridade Elevador (led)   

        _est = [prodMain,prodA,prodB,prodElev,prioID[0],prioID[1],prioID[2],mb.ana.registers[5]/1000]
        est = esteira.forward(_est) if esteira.entrada_tamanho == len(_est) else [0 for _ in range(esteira.saida_tamanho)]
        maquinas['_esteira'] = _est
        maquinas['esteira'] = est
        print(f"esteira: {_est} -> {est}")

        if len(est) == 2:
            mb.escrever_coil(rede='esteira', saida='coil_0', address=0, value=binario(est[0])) # Repositor
            mb.escrever_registro(rede='esteira', saida='register_0', address=0, value=int(est[1]*1000)) # Velocidade esteira 

        # Entradas:[Sensor Fim Esteira, Sensor Material, Posição X, posição Z]
        _expA = [mb.dig.bits[1],mb.dig.bits[2], mb.ana.registers[1]/1000, mb.ana.registers[2]/1000]         
        expA = expedicao.forward(_expA) if expedicao.entrada_tamanho == len(_expA) else [0 for _ in range(expedicao.saida_tamanho)]
        maquinas['_expedicaoA'] = _expA
        maquinas['expedicaoA'] = expA
        print(f"expedicaoA: {_expA} -> {expA}")

        if len(expA) == 4:
            mb.escrever_coil(rede='expedicaoA', saida='coil_6', address=6, value=binario(expA[0])) # Agarrar A
            mb.escrever_registro(rede='expedicaoA', saida='register_1', address=1, value=int(expA[1]*1000)) # Eixo X robô A
            mb.escrever_registro(rede='expedicaoA', saida='register_2', address=2, value=int(expA[2]*1000)) # Eixo Z robô A
            mb.escrever_registro(rede='expedicaoA', saida='register_3', address=3, value=int(expA[3]*1000)) # Esteira robô A   

        # Entradas:[Sensor Fim Esteira, Sensor Material, Posição X, posição Z]
       # _expB = [mb.dig.bits[4], mb.dig.bits[5], mb.ana.registers[3]/1000, mb.ana.registers[4]/1000]         
       # expB = expedicao.forward(_expB) if expedicao.entrada_tamanho == len(_expB) else [0 for _ in range(expedicao.saida_tamanho)]
       #  maquinas['_expedicaoB'] = _expB
       #  maquinas['expedicaoB'] = expB
       # print(f"expedicaoB: {_expB} -> {expB}")

        #if len(expB) == :
         #   mb.escrever_coil(rede='expedicaoB', saida='coil_7', address=7, value=binario(expB[0])) # Agarrar B
          #  mb.escrever_registro(rede='expedicaoB', saida='register_4', address=4, value=int(expB[1]*1000)) # Eixo X robô B
           # mb.escrever_registro(rede='expedicaoB', saida='register_5', address=5, value=int(expB[2]*1000)) # Eixo Z robô B
            #mb.escrever_registro(rede='expedicaoB', saida='register_6', address=8, value=int(expB[3]*1000)) # Esteira robô B  

        totalA = xA + yA + zA
        totalB = xB + yB + zB

        if (totalA < faltamA) or (faltamA < -1):
            faltamA = totalA
        elif totalA > faltamA:
            faltamA -= 0.01

        if (totalB < faltamB) or (faltamB < -1):
            faltamB = totalB
        elif totalB > faltamB:
            faltamB -= 0.01

        _des = [faltamA, faltamB]
        des = despacho.forward(_des) if despacho.entrada_tamanho == len(_des) else [0 for _ in range(despacho.saida_tamanho)]
        maquinas['_despacho'] = _des
        maquinas['despacho'] = des
        print(f"despacho: {_des} -> {des}")

        if len(des) == 2:
            mb.escrever_coil(rede='despacho', saida='coil_12', address=12, value=binario(des[0])) # Esteira Saída A
            mb.escrever_coil(rede='despacho', saida='coil_13', address=13, value=binario(des[1])) # Esteira Saída B 

        # [Nível elevador, Item]
        _elev = [mb.ana.registers[5]/1000, prodElev]  
        elev = elevador.forward(_elev) if elevador.entrada_tamanho == len(_elev) else [0 for _ in range(elevador.saida_tamanho)]
        maquinas['_elevador'] = _elev
        maquinas['elevador'] = elev
        print(f"elevador: {_elev} -> {elev}")

        if len(elev) == 5:
            mb.escrever_registro(rede='elevador', saida='register_7', address=7, value=int(elev[0]*1000)) # Setpoint elevador
            mb.escrever_coil(rede='elevador', saida='coil_8', address=8, value=binario(elev[1])) # Esteira elevador
            mb.escrever_coil(rede='elevador', saida='coil_9', address=9, value=binario(elev[2])) # Esteira 0
            mb.escrever_coil(rede='elevador', saida='coil_10', address=10, value=binario(elev[3])) # Esteira 1
            mb.escrever_coil(rede='elevador', saida='coil_11', address=11, value=binario(elev[4])) # Esteira 2            

        if medindo and mb.todas_saidas_com_amostras(qtd_amostras_tempo):
            parar_medicao_e_salvar()

        if(mb.dig.bits[0] > 0 and prodA == 0): # Entrada A
            prodA = prodMain
            pedidos['pedidoA']['feitos'] = producaoCnt(pedidos['pedidoA']['feitos'], prodA)
            prodMain = 0

        if(mb.dig.bits[3] > 0 and prodB == 0): # Entrada B
            prodB = prodMain
            pedidos['pedidoB']['feitos'] = producaoCnt(pedidos['pedidoB']['feitos'], prodB)
            prodMain = 0

        if(mb.dig.bits[6] == 0 and prodElev == 0): # Entrada Elevador
            prodElev = prodMain
            prodMain = 0
        
        if(mb.dig.bits[1] == 0 and mb.dig.bits[2] > 0): # Saída A
            prodA = 0 

        if(mb.dig.bits[4] == 0 and mb.dig.bits[5] > 0): # Saída B
            prodB = 0 

        if(mb.dig.bits[6] > 0): # Saída Elevador
            prodElev = 0  
       
        maquinas['hora'] = [time.time()]
        registrar_tempo_loop(inicio_loop)
        time.sleep(0.020)

thread_http = threading.Thread(target=site)
thread_http.daemon = True
thread_http.start()
try:
    plantaIndustrial()
except KeyboardInterrupt:
    pass
finally:
    if mb_tempo is not None:
        mb_tempo.salvar_tempos_escrita()
    if tempos_loop:
        menor_loop = min(tempos_loop)
        maior_loop = max(tempos_loop)
        media_loop = sum(tempos_loop) / len(tempos_loop)
        with open('tempos_modbus.txt', 'a', encoding='utf-8') as dados:
            dados.write('\nTempo de processamento do loop (planta industrial) (ms)\n')
            dados.write('Menor (ms);Maior (ms);Media (ms);Quantidade\n')
            dados.write(
                f'{menor_loop:.3f};{maior_loop:.3f};{media_loop:.3f};{len(tempos_loop)}\n'
            )