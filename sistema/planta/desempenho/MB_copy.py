import time
from collections import defaultdict

from pymodbus.client import ModbusTcpClient

class ModBus:
    def __init__(self, ip):
        self.cliente = ModbusTcpClient(ip, port=502)
        self.tempos_escrita = defaultdict(list)
        self.medindo_escrita = True
        self.conexao = self.cliente.connect()
        if self.conexao:
            print('Modbus TCP conectado com sucesso!')
        else:
            print('Não foi possível conectar ao servidor Modbus TCP!')

    def leitura(self):
        if self.conexao:
            try:
                inicio = time.perf_counter()
                self.dig = self.cliente.read_discrete_inputs(address=0, count=7)
                print(f'{(time.perf_counter() - inicio) * 1000:.3f} ms')
                if self.dig.isError():
                    print('Erro na leitura das entradas digitais:', self.dig)  

                inicio = time.perf_counter()
                self.ana = self.cliente.read_input_registers(address=0, count=6)
                print(f'{(time.perf_counter() - inicio) * 1000:.3f} ms')
                if self.ana.isError():
                    print('Erro na leitura das entradas analogicas:', self.ana)    
                                            
            except KeyboardInterrupt:
                print('Finalizando conexão...')
                self.cliente.close()
                self.conexao = False
            except Exception as e:
                print('Erro na comunicação Modbus:', e)
                self.cliente.close()
                self.conexao = False
        else:
            self.conexao = self.cliente.connect()

    def escrever_coil(self, rede, saida, address, value):
        inicio = time.perf_counter()
        resposta = self.cliente.write_coil(address=address, value=value)
        tempo_ms = (time.perf_counter() - inicio) * 1000
        if self.medindo_escrita:
            self.tempos_escrita[f'{rede}/{saida}'].append(tempo_ms)
        print(f'{tempo_ms:.3f} ms')
        return resposta

    def escrever_registro(self, rede, saida, address, value):
        inicio = time.perf_counter()
        resposta = self.cliente.write_register(address=address, value=value)
        tempo_ms = (time.perf_counter() - inicio) * 1000
        if self.medindo_escrita:
            self.tempos_escrita[f'{rede}/{saida}'].append(tempo_ms)
        print(f'{tempo_ms:.3f} ms')
        return resposta

    def salvar_tempos_escrita(self, arquivo='tempos_modbus.txt'):
        saidas_por_rede = defaultdict(dict)
        with open(arquivo, 'w', encoding='utf-8') as dados:
            dados.write('Tempos de resposta das escritas Modbus (ms)\n')
            dados.write('Rede/Saida;Menor (ms);Maior (ms);Media (ms);Quantidade\n')
            for chave in sorted(self.tempos_escrita):
                tempos = self.tempos_escrita[chave]
                menor_tempo = min(tempos)
                maior_tempo = max(tempos)
                media_tempo = sum(tempos) / len(tempos)
                dados.write(
                    f'{chave};{menor_tempo:.3f};{maior_tempo:.3f};'
                    f'{media_tempo:.3f};{len(tempos)}\n'
                )
                rede, saida = chave.split('/', 1)
                saidas_por_rede[rede][saida] = tempos

            # Tempo para escrever o CONJUNTO de saidas de cada rede: em cada
            # rodada (iteracao em que todas as saidas da rede sao escritas),
            # soma-se o tempo de escrita de cada saida; menor/maior/media sao
            # calculados sobre essas somas por rodada, nao sobre escritas isoladas.
            dados.write('\nAcumulado por rede (tempo para escrever todas as saidas do conjunto) (ms)\n')
            dados.write('Rede;Menor (ms);Maior (ms);Media (ms);Quantidade\n')
            for rede in sorted(saidas_por_rede):
                tempos_por_saida = list(saidas_por_rede[rede].values())
                tempos_por_rodada = [sum(amostras) for amostras in zip(*tempos_por_saida)]
                menor_tempo = min(tempos_por_rodada)
                maior_tempo = max(tempos_por_rodada)
                media_tempo = sum(tempos_por_rodada) / len(tempos_por_rodada)
                dados.write(
                    f'{rede};{menor_tempo:.3f};{maior_tempo:.3f};'
                    f'{media_tempo:.3f};{len(tempos_por_rodada)}\n'
                )

    def todas_saidas_com_amostras(self, quantidade):
        return bool(self.tempos_escrita) and all(
            len(tempos) >= quantidade
            for tempos in self.tempos_escrita.values()
        )
