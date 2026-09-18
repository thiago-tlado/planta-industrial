async function gerenciarItens(comando, id = 0) {
    if(comando === 'deletar' && !confirm('Tem certeza que deseja deletar este pedido?')) return;

    let pedido = [];
    if(comando === 'inserir') {
        const x = parseInt(document.getElementById('X').value);
        const y = parseInt(document.getElementById('Y').value);
        const z = parseInt(document.getElementById('Z').value);
        pedido = [x, y, z];
        const total = x + y + z;

        if(total < 2 || total > 6) {
            alert('Escolha pelo menos 2 itens e no máximo 6, para efetuar o pedido.');
            return false;
        }
    }

    await fetch('funcoes/pedidos.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ comando, pedido, id })
    })
    .then(response => response.json())
    .then(async data => {
        console.log(data);
        desenharTabela(data.pedidos);
    });
}

async function desenharTabela(pedidos) {
    const tbody = document.getElementById('dados-tabela-body');
    tbody.innerHTML = '';

    if (!pedidos || pedidos.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="7" style="text-align: center;">Nenhum dado encontrado</td>`;
        tbody.appendChild(tr);
        return;
    }

    pedidos.forEach(pedido => {
        const tr = document.createElement('tr');
        const estatus = pedido.estatus;
        let estatusHtml = estatus;
        if (estatus === 'Pendente') {
            estatusHtml = `<span class="status-label status-pendente">${estatus}</span>`;
        } else if (estatus.startsWith('Finalizado')) {
            estatusHtml = `<span class="status-label status-finalizado">${estatus}</span>`;
        } else {
            estatusHtml = `<span class="status-label status-fabricando">${estatus}</span>`;
        }

        function itemHtml(feito, total) {
            const texto = `${feito}/${total}`;
            if (feito >= total) {
                return `<span class="status-label status-finalizado">${texto}</span>`;
            }
            if (feito > 0) {
                return `<span class="status-label status-fabricando">${texto}</span>`;
            }
            return texto;
        }

        tr.innerHTML = `
            <td>${pedido.id}</td>
            <td>${itemHtml(pedido.feitos[0], pedido.pedido[0])}</td>
            <td>${itemHtml(pedido.feitos[1], pedido.pedido[1])}</td>
            <td>${itemHtml(pedido.feitos[2], pedido.pedido[2])}</td>
            <td>${estatusHtml}</td>
            <td>${pedido.hora}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="gerenciarItens('deletar', ${pedido.id})">
                Deletar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

const ctx0 = document.getElementById('chart0').getContext('2d');
const ctx1 = document.getElementById('chart1').getContext('2d');
const ctx2 = document.getElementById('chart2').getContext('2d');
const ctx3 = document.getElementById('chart3').getContext('2d');
const ctx4 = document.getElementById('chart4').getContext('2d');
const ctx5 = document.getElementById('chart5').getContext('2d');

let grafico0;
let grafico1;
let grafico2;
let grafico3;
let grafico4;
let grafico5;

async function limparGraficos() {
    if (grafico0) await grafico0.destroy();
    if (grafico1) await grafico1.destroy();
    if (grafico2) await grafico2.destroy();
    if (grafico3) await grafico3.destroy();
    if (grafico4) await grafico4.destroy();
    if (grafico5) await grafico5.destroy();
    grafico0 = await new Chart(ctx0, config('line'));
    grafico1 = await new Chart(ctx1, config('line'));
    grafico2 = await new Chart(ctx2, config('line'));
    grafico3 = await new Chart(ctx3, config('line'));
    grafico4 = await new Chart(ctx4, config('line'));
    grafico5 = await new Chart(ctx5, config('line'));
}

async function atualizarGraficos(comando = 'atualizar') {
    const rede = document.getElementById('dados-rede').value;
    const acao = document.getElementById('acao-btn');
    const qtd = document.getElementById('qtd').value;
    document.getElementById('hora').value = new Date().toLocaleTimeString();

    await fetch('funcoes/dash.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ comando, rede, qtd })
    })
    .then(response => response.json())
    .then(async json => {
        console.log(json);
        maquinas = json.maquinas;

        if(json.status) {
            acao.textContent = 'Parar';
            acao.style.backgroundColor = '#dc3545';
        } else {
            acao.textContent = 'Ligar';
            acao.style.backgroundColor = '#28a745';
        }

        if(comando === 'acao') {
            return true;
        }

        if(comando === 'listar') {
            desenharTabelaSinais(maquinas); 
            return true;
        }

        function updateGrafico(grafico, hora, dados) {
            grafico.data.labels = hora.map(h => new Date(h * 1000).toLocaleTimeString());
            for(let i = 0; i < dados.length; i++) {
                if(grafico.data.datasets[i]) {
                    grafico.data.datasets[i].label = 'Saída ' + (i + 1);
                    grafico.data.datasets[i].data = dados[i];
                } else {
                    grafico.data.datasets.push({
                        label: 'Saída ' + (i + 1),
                        data: dados[i],
                        borderColor: corCustom(i, dados.length, 1),
                        backgroundColor: corCustom(i, dados.length, 0.2),
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        borderWidth: 2
                    });
                }
            }

            grafico.update();
        }

        function fill(elementoId, value) {
            const objID = document.getElementById(elementoId);
            const cor = (parseFloat(value) >= 0.95) ? 'green' : 'red';
            objID.setAttribute('fill', cor);
        }

        function content(elementoId, value) {
            const objID = document.getElementById(elementoId);
            objID.textContent = parseFloat(value).toFixed(3);
        }

        if(maquinas.despacho) {
            if (maquinas.despacho[1]) {
               fill('saida-A', maquinas.despacho[0][maquinas.despacho[0].length - 1]);
               fill('saida-B', maquinas.despacho[1][maquinas.despacho[1].length - 1]);
            }
        }

        if(maquinas.prioridade) {
            if (maquinas.prioridade[1]) {
               fill('pivotA', maquinas.prioridade[0][maquinas.prioridade[0].length - 1]);          
               fill('pivotB', maquinas.prioridade[1][maquinas.prioridade[1].length - 1]);
            }
        }

        if (maquinas._esteira) {
            if (maquinas._esteira[3]) {
               content('item', maquinas._esteira[0][maquinas._esteira[0].length - 1]);
               content('item-A', maquinas._esteira[1][maquinas._esteira[1].length - 1]);
               content('item-B', maquinas._esteira[2][maquinas._esteira[2].length - 1]);
               content('item-elevador', maquinas._esteira[3][maquinas._esteira[3].length - 1]);
            }
        }
        
        if (maquinas.esteira) {
            if (maquinas.esteira[1]) {
               fill('repositor', maquinas.esteira[0][maquinas.esteira[0].length - 1]);
               content('esteira-main', maquinas.esteira[1][maquinas.esteira[1].length - 1]);
            }  
        }      
        
        if(maquinas.elevador) {
            if (maquinas.elevador[4]) {
                content('elevador-nivel', maquinas.elevador[0][maquinas.elevador[0].length - 1]);
                fill('elevador-esteira', maquinas.elevador[1][maquinas.elevador[1].length - 1]);
                fill('elevador0', maquinas.elevador[2][maquinas.elevador[2].length - 1]);
                fill('elevador1', maquinas.elevador[3][maquinas.elevador[3].length - 1]);
                fill('elevador2', maquinas.elevador[4][maquinas.elevador[4].length - 1]);
            }
        }

        if (maquinas._expedicaoA) {
            if (maquinas._expedicaoA[0]) {   
                fill('sensor-fimA', maquinas._expedicaoA[0][maquinas._expedicaoA[0].length - 1]);
            }
        }
            
        if (maquinas.expedicaoA) {
            if (maquinas.expedicaoA[3]) {   
                fill('agarrar-A', maquinas.expedicaoA[0][maquinas.expedicaoA[0].length - 1]);
                content('eixoX-A', maquinas.expedicaoA[1][maquinas.expedicaoA[1].length - 1]);
                content('eixoZ-A', maquinas.expedicaoA[2][maquinas.expedicaoA[2].length - 1]);
                content('esteira-A', maquinas.expedicaoA[3][maquinas.expedicaoA[3].length - 1]);
            }
        }

        if (maquinas._expedicaoB) {
            if (maquinas._expedicaoB[0]) {   
                fill('sensor-fimB', maquinas._expedicaoB[0][maquinas._expedicaoB[0].length - 1]);
            }
        }
         
        if (maquinas.expedicaoB) {
            if (maquinas.expedicaoB[3]) {   
                fill('agarrar-B', maquinas.expedicaoB[0][maquinas.expedicaoB[0].length - 1]);          
                content('eixoX-B', maquinas.expedicaoB[1][maquinas.expedicaoB[1].length - 1]);
                content('eixoZ-B', maquinas.expedicaoB[2][maquinas.expedicaoB[2].length - 1]);
                content('esteira-B', maquinas.expedicaoB[3][maquinas.expedicaoB[3].length - 1]);
            }
        }

        if (maquinas.despacho) updateGrafico(grafico0, maquinas.hora, maquinas.despacho);  
        if (maquinas.prioridade) updateGrafico(grafico1, maquinas.hora, maquinas.prioridade);   
        if (maquinas.esteira) updateGrafico(grafico2, maquinas.hora, maquinas.esteira); 
        if (maquinas.elevador) updateGrafico(grafico3, maquinas.hora, maquinas.elevador);
        if (maquinas.expedicaoA) updateGrafico(grafico4, maquinas.hora, maquinas.expedicaoA);
        if (maquinas.expedicaoB) updateGrafico(grafico5, maquinas.hora, maquinas.expedicaoB);
    });
}

async function desenharTabelaSinais(dados) {
    const tbody = document.getElementById('sinais-tabela-body');
    tbody.innerHTML = '';

    if(dados.hora.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="4" style="text-align: center;">Nenhum dado encontrado</td>`;
        tbody.appendChild(tr);
    }
    else {
        for(let i = dados.hora.length-1; i >= 0; i--) {
        const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${i+1}</td>
                <td>${new Date(dados.hora[i] * 1000).toLocaleTimeString()}</td>
                <td>${dados.entrada[i]}</td>
                <td>${dados.saida[i]}</td>
            `;
            tbody.appendChild(tr);    
        }
    }
}
