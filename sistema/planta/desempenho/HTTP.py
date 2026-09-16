import requests

def http(pedidos, maquinas):   
    try:
        dados = {"origem": "planta",
                 "pedidos": pedidos,              
                 "maquinas": maquinas                               
                }       
                
        url = "http://localhost/industrial/funcoes/planta.php"
        resposta = requests.post(url, json=dados)
        resul = resposta.json()
        return resul['comando'], resul['encomendas'], resul['dados'], True

    except Exception as e:
        print("Erro na requisição:", e)   
        return False, [0,0,0,0], {'despacho': [], 'expedicao': [], 'elevador': [], 'esteira': [], 'prioridade': []}, False