from pymodbus.client import ModbusTcpClient

class ModBus:
    def __init__(self, ip):
        self.cliente = ModbusTcpClient(ip, port=502)
        self.conexao = self.cliente.connect()
        if self.conexao:
            print('Modbus TCP conectado com sucesso!')
        else:
            print('Não foi possível conectar ao servidor Modbus TCP!')

    def leitura(self):
        if self.conexao:
            try:
                self.dig = self.cliente.read_discrete_inputs(address=0, count=7)
                if self.dig.isError():
                    print('Erro na leitura das entradas digitais:', self.dig)  

                self.ana = self.cliente.read_input_registers(address=0, count=6)
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

