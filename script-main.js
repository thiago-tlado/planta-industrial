function corCustom(indice, total, transp) {
    const inicio = [0, 125, 255];   // rgba(0, 128, 255, 1)
    const fim = [255, 125, 0];     // rgba(154, 44, 44, 1)
    const fator = indice / Math.max(total - 1, 1);
    const r = inicio[0] + (fator * (fim[0] - inicio[0]));
    const g = inicio[1] + (fator * (fim[1] - inicio[1]));
    const b = inicio[2] + (fator * (fim[2] - inicio[2]));
    const cor = `rgba(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)}, ${transp})`;
    return cor;
}

function config(tipo = 'line') {
    return {
        type: tipo,
        data: {
            labels: [],
            datasets: [{
                data: [],
                fill: false,
                tension: 0.4, // linha mais suave
                pointRadius: 0, // remove os pontos
                borderWidth: 2 // linha mais fina
            }]
        },
        options: {
            responsive: true,
            animation: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#888', // cor mais discreta
                        font: {
                            size: 10, // fonte menor
                            weight: 'normal'
                        }
                    }
                }
            },
            elements: {
                point: {
                    radius: 0 // remove os pontos
                },
                line: {
                    tension: 0.4 // linha mais suave
                }
            }
        }
    };
}