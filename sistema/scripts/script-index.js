const ctx1 = document.getElementById('chart1').getContext('2d');
const ctx2 = document.getElementById('chart2').getContext('2d');
const ctx3 = document.getElementById('chart3').getContext('2d');
const ctx4 = document.getElementById('chart4').getContext('2d');
const ctx5 = document.getElementById('chart5').getContext('2d');
const ctx6 = document.getElementById('chart6').getContext('2d');

let grafico1;
let grafico2;
let grafico3;
let grafico4;
let grafico5;
let grafico6;

function configPie(labels, colors) {
    const cfg = config('pie');
    cfg.options = cfg.options || {};
    cfg.options.radius = '75%';

    cfg.plugins = [{
        id: 'pieValueLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const dataset = chart.data.datasets[0];
            const meta = chart.getDatasetMeta(0);
            if (!dataset || !meta || !meta.data) return;
            ctx.save();
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 20px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            chart.data.labels = labels;
            chart.data.datasets[0].backgroundColor = colors;

            meta.data.forEach((arc, index) => {
                const valor = dataset.data[index];
                if (valor === null || valor === undefined) return;
                const pos = arc.tooltipPosition();
                ctx.fillText(String(valor), pos.x, pos.y);
            });
            ctx.restore();
        }
    }];

    return cfg;
}

async function limparGraficos() {
    if (grafico1) await grafico1.destroy();
    if (grafico2) await grafico2.destroy();
    if (grafico3) await grafico3.destroy();
    if (grafico4) await grafico4.destroy();
    if (grafico5) await grafico5.destroy();
    if (grafico6) await grafico6.destroy();
    let labels = ['Item X', 'Item Y', 'Item Z'];
    let colors = ['blue', 'green', 'silver'];

    grafico1 = await new Chart(ctx1, configPie(labels, colors));
    grafico2 = await new Chart(ctx2, configPie(labels, colors));
    grafico3 = await new Chart(ctx3, configPie(labels, colors));
    grafico4 = await new Chart(ctx4, configPie(labels, colors));
    grafico5 = await new Chart(ctx5, configPie(labels, colors));
    labels = ['Esteira A', 'Esteira B', 'Estoque'];
    colors = ['red', 'orange', 'brown'];
    grafico6 = await new Chart(ctx6, configPie(labels, colors));
}

async function relatorio(comando = 'atualizar') {
    const relatorioDiv = document.getElementById('relatorio');
    if(comando === 'api') relatorioDiv.textContent = 'Gerando relatório...';
    
    await fetch('funcoes/api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ comando })
    })
    .then(response => response.json())
    .then(async json => {
        console.log(json);
        grafico1.data.datasets[0].data = json.total;
        grafico2.data.datasets[0].data = json.elev;
        grafico3.data.datasets[0].data = json.expA;
        grafico4.data.datasets[0].data = json.expB;
        grafico5.data.datasets[0].data = json.tempo.slice(0, 3);
        grafico6.data.datasets[0].data = json.tempo.slice(4, 7);
        await grafico1.update();
        await grafico2.update();
        await grafico3.update();
        await grafico4.update();
        await grafico5.update();
        await grafico6.update();
        if(comando === 'api') relatorioDiv.textContent = json.api;        
    })
}
