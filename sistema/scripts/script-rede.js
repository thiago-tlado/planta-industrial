function checarDados(entrada, saida) {
    function validarAmostras(str) {
        const amostras = str.split(',').map(s => s.trim()).filter(s => s.length > 0);
        if (amostras.length === 0) return false;
        const regex = /^-?\d+(\.\d+)?$/;
        
        for (const amostra of amostras) {
            if (!regex.test(amostra)) return false;            
        }
        return true;
    }
    return validarAmostras(entrada) && validarAmostras(saida);
}

async function dadosTreinamento(comando, pos = 0) {
    if(comando === 'remover' && !confirm('Tem certeza que deseja deletar esta rede?')) return;
    if(comando === 'deletar' && !confirm('Tem certeza que deseja deletar este conjunto de dados de treinamento?')) return;

    const entrada = document.getElementById('entrada').value;
    const saida = document.getElementById('saida').value;

    if(comando === 'inserir') {
        if(!checarDados(entrada, saida)) {
            alert("Dados inválidos!");
            return;
        }
    }

    const rede = document.getElementById('rede-select').value;
    const versao = document.getElementById('versao-select').value; 
    const versaoFinal = document.getElementById('versao-final');

    await fetch('funcoes/dados.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ comando, pos, rede, versao, entrada, saida })
    })
    .then(response => response.json())
    .then(async data => {
        console.log(data);
        versaoFinal.value = (data.coment) ? data.coment : 'Não configurado';             

        if(comando === 'inserir' || comando === 'deletar') {
            dadosTreinamento('treinos', versao);
            desenharTabela(data.lista);
        }
        else if(comando === 'remover') {
            dadosTreinamento('treinos');
            desenharTabela([]);
        }
        else if(comando === 'clonar') {
            dadosTreinamento('treinos', data.versao);
            desenharTabela(data.lista);
        }
        else if(comando === 'treinos') {
            listarTreinos(data.treinos, pos);
        }
        else if(comando === 'listar') {
            desenharTabela(data.lista);
        }

        const removerGroup = document.getElementById('remover-group');
        removerGroup.style.display = (versao === 'novo') ? 'none' : 'flex';
    });
}

async function listarTreinos(treinos, index) {
    const versao = document.getElementById('versao-select');
    versao.innerHTML = '<option value="novo">Novo</option>';

    if(treinos) {
        treinos.forEach((treino, i) => {
            const option = document.createElement('option');
            option.value = treino;
            option.textContent = 'v' + treino;
            if(index == 'novo' && i === 0) option.selected = true;
            else if(treino == index) option.selected = true;
            versao.appendChild(option);
        });
    }
}

async function desenharTabela(lista) {
    const tbody = document.getElementById('dados-tabela-body');
    tbody.innerHTML = '';

    if (!lista || lista.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="4" style="text-align: center;">Nenhum dado encontrado</td>`;
        tbody.appendChild(tr);
        return;
    }

    lista.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.id+1}</td>
            <td>${item.entrada}</td>
            <td>${item.saida}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="dadosTreinamento('deletar', ${item.id})">
                Deletar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

const ctx01 = document.getElementById('chart01').getContext('2d');
const ctx02 = document.getElementById('chart02').getContext('2d');
const ctx1 = document.getElementById('chart1').getContext('2d');
const ctx2 = document.getElementById('chart2').getContext('2d');
const ctx3 = document.getElementById('chart3').getContext('2d');
const ctx4 = document.getElementById('chart4').getContext('2d');
const ctx5 = document.getElementById('chart5').getContext('2d');
const ctx6 = document.getElementById('chart6').getContext('2d');

let grafico01;
let grafico02;
let grafico1;
let grafico2;
let grafico3;
let grafico4;
let grafico5;
let grafico6;
let data = {};

async function limparGraficos() {
    if (grafico01) await grafico01.destroy();
    if (grafico02) await grafico02.destroy();
    if (grafico1) await grafico1.destroy();
    if (grafico2) await grafico2.destroy();
    if (grafico3) await grafico3.destroy();
    if (grafico4) await grafico4.destroy();
    if (grafico5) await grafico5.destroy();
    if (grafico6) await grafico6.destroy();
    grafico01 = await new Chart(ctx01, config('line'));
    grafico02 = await new Chart(ctx02, config('bar'));
    grafico1 = await new Chart(ctx1, config('line'));
    grafico2 = await new Chart(ctx2, config('bar'));
    grafico3 = await new Chart(ctx3, config('line'));
    grafico4 = await new Chart(ctx4, config('line'));
    grafico5 = await new Chart(ctx5, config('line'));
    grafico6 = await new Chart(ctx6, config('line'));
}
limparGraficos();

async function atualizarGraficos() {
    await limparGraficos();

    function inserirNovo(nome, valor, i, total) {
        return { label: nome,
            data: [valor],
            borderColor: corCustom(i, total, 1),
            backgroundColor: corCustom(i, total, 0.2),
            fill: false,
            tension: 0.4,
            pointRadius: 0,
            borderWidth: 2
        };
    }

    let EO = parseInt(document.getElementById('EO').value);
    if (isNaN(EO)) EO = 0;
    let OS = parseInt(document.getElementById('OS').value);
    if (isNaN(OS)) OS = 0;

    for(let i = 0; i < data.epocas.length; i++) {
        grafico01.data.labels.push(data.epocas[i]);
        grafico02.data.labels.push(data.epocas[i]);     
        grafico1.data.labels.push(data.epocas[i]);
        grafico2.data.labels.push(data.epocas[i]);
        grafico3.data.labels.push(data.epocas[i]);
        grafico4.data.labels.push(data.epocas[i]);
        grafico5.data.labels.push(data.epocas[i]);
        grafico6.data.labels.push(data.epocas[i]);

        const total = data.acertos[i].reduce((a, b) => a + b, 0);
        grafico02.data.datasets[0].label = `Acurácia Geral`;
        grafico02.data.datasets[0].data.push((100*total/(data.tamanho[i]*data.acertos[i].length)).toFixed(2));   
        grafico2.data.datasets[0].label = `Erro Geral`;
        grafico2.data.datasets[0].data.push(data.erro_geral[i]);    

        for(let j = 0; j < data.erro_atual[i].length; j++) {
            if(grafico1.data.datasets[j]) {
                grafico01.data.datasets[j].label = `Saída ${j + 1}`;
                grafico01.data.datasets[j].data.push((100*data.acertos[i][j]/data.tamanho[i]).toFixed(2));
                grafico1.data.datasets[j].label = `Saída ${j + 1}`;
                grafico1.data.datasets[j].data.push(data.erro_atual[i][j].toFixed(5));
            } else {
                grafico01.data.datasets.push(inserirNovo(`Saída ${j + 1}`, (100*data.acertos[i][j]/data.tamanho[i]).toFixed(2), j, data.acertos[i].length));
                grafico1.data.datasets.push(inserirNovo(`Saída ${j + 1}`, data.erro_atual[i][j].toFixed(5), j, data.erro_atual[i].length));
            }
        }

        for(let j = 0; j < data.peso_entrada_oculta[i][EO].length; j++) {
            if(grafico3.data.datasets[j]) {
                grafico3.data.datasets[j].label = `E${EO + 1}-O${j + 1}`;
                grafico3.data.datasets[j].data.push(data.peso_entrada_oculta[i][EO][j].toFixed(5));
            } else {
                grafico3.data.datasets.push(inserirNovo(`E${EO + 1}-O${j + 1}`, data.peso_entrada_oculta[i][EO][j].toFixed(5), j, data.peso_entrada_oculta[i][EO].length));
            }
        }

        for(let j = 0; j < data.peso_oculta_saida[i][OS].length; j++) {
            if(grafico4.data.datasets[j]) {
                grafico4.data.datasets[j].label = `O${OS + 1}-S${j + 1}`;
                grafico4.data.datasets[j].data.push(data.peso_oculta_saida[i][OS][j].toFixed(5));
            } else {
                grafico4.data.datasets.push(inserirNovo(`O${OS + 1}-S${j + 1}`, data.peso_oculta_saida[i][OS][j].toFixed(5), j, data.peso_oculta_saida[i][OS].length));
            }
        }

        for(let j = 0; j < data.bias_oculta[i].length; j++) {
            if(grafico5.data.datasets[j]) {
                grafico5.data.datasets[j].label = `Bias O${j + 1}`;
                grafico5.data.datasets[j].data.push(data.bias_oculta[i][j].toFixed(5));
            } else {
                grafico5.data.datasets.push(inserirNovo(`Bias O${j + 1}`, data.bias_oculta[i][j].toFixed(5), j, data.bias_oculta[i].length));
            }
        }

        for(let j = 0; j < data.bias_saida[i].length; j++) {
            if(grafico6.data.datasets[j]) {
                grafico6.data.datasets[j].label = `Bias S${j + 1}`;
                grafico6.data.datasets[j].data.push(data.bias_saida[i][j].toFixed(5));
            } else {
                grafico6.data.datasets.push(inserirNovo(`Bias S${j + 1}`, data.bias_saida[i][j].toFixed(5), j, data.bias_saida[i].length));
            }
        }
    }

    grafico01.update();
    grafico02.update();
    grafico1.update();
    grafico2.update();
    grafico3.update();
    grafico4.update();
    grafico5.update();
    grafico6.update();
}

async function treinarRede(comando) {
    if(comando === 'resetar' && !confirm('Tem certeza que deseja resetar o treinamento?')) return;

    const rede = document.getElementById('rede-select').value;
    const versao = document.getElementById('versao-select').value;

    if(versao  === 'novo') return;
    const epocas = document.getElementById('epocas').value;
    const taxa = document.getElementById('taxa').value;
    const teste = document.getElementById('teste-select').value;

    const overlay = document.getElementById('loading-overlay');
    if(comando === 'treinar') {
        overlay.style.display = 'flex';
        await new Promise(resolve => setTimeout(resolve, 1000));       
    }

    await fetch('funcoes/redes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ rede, versao, epocas, taxa, teste, comando })
    })
    .then(response => response.json())
    .then(async json => {
        data = json;
        console.log(data);

        if(comando === 'teste') {
            let saida = '';
            data.forEach((item, index) => { saida += (`Saída ${index + 1}: ${item}\n`); });
            alert(saida);
            return;
        }

        const EO = document.getElementById('EO');
        const OS = document.getElementById('OS');
        EO.innerHTML = '';
        OS.innerHTML = '';

        if(!data || !data.peso_entrada_oculta || !data.peso_oculta_saida) {
            limparGraficos();
            if(versao !== 'novo') alert("Sem dados de treinamento!");
            return;
        }

        await atualizarGraficos();

        if(comando === 'usar') {
            const versaoFinal = document.getElementById('versao-final');
            versaoFinal.value = data.coment;
                let txt = '';
                if (data.dados) {
                    function formatarParametro(arr) {
                        let linhas = [];
                        if (Array.isArray(arr[0])) {
                            arr.forEach((sub, i) => {
                                sub.forEach((val, j) => {
                                    linhas.push(`[${i}][${j}]: ${val}`);
                                });
                            });
                        } else { arr.forEach((val, i) => { linhas.push(`[${i}]: ${val}`); });}
                        return linhas.join('<br>');
                    }
                    if (data.dados.peso_entrada_oculta) {
                        txt += '--- Pesos da entrada para a camada oculta --- [entrada][oculta] <br>';
                        txt += formatarParametro(data.dados.peso_entrada_oculta) + '<br>';
                    }
                    if (data.dados.bias_oculta) {
                        txt += '<br>--- Bias da camada oculta --- [oculta] <br>';
                        txt += formatarParametro(data.dados.bias_oculta) + '<br>';
                    }
                    if (data.dados.peso_oculta_saida) {
                        txt += '<br>--- Pesos da camada oculta para a saída --- [oculta][saida] <br>';
                        txt += formatarParametro(data.dados.peso_oculta_saida) + '<br>';
                    }
                    if (data.dados.bias_saida) {
                        txt += '<br>--- Bias da camada de saída --- [saida] <br>';
                        txt += formatarParametro(data.dados.bias_saida) + '<br>';
                    }
                }
                if (data.erro_atual && data.erro_atual.length > 0) {
                    txt += '<br>--- Erro --- [saida] <br>';
                    txt += formatarParametro(data.erro_atual[data.erro_atual.length - 1]) + '<br>';
                }
                if (data.erro_geral && data.erro_geral.length > 0) {
                    txt += '<br>--- Erro geral --- <br>';
                    txt += data.erro_geral[data.erro_geral.length - 1] + '<br>';
                }
                document.getElementById('info').innerHTML = txt;
                alert("Rede configurada com sucesso!\nVeja os detalhes no fim da página!");
        }

        for(let k = 0; k < data.peso_entrada_oculta[0].length; k++) {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = `Entrada ${k + 1}`;
            EO.appendChild(opt);
        }

        for(let k = 0; k < data.peso_oculta_saida[0].length; k++) {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = `Oculta ${k + 1}`;
            OS.appendChild(opt);
        }
    })
    .catch((err) => {
        console.error(err);
    });

    if(comando === 'treinar') {  
        dadosTreinamento('listar'); 
        overlay.style.display = 'none';
        alert("Treinamento concluído!");
    }
}