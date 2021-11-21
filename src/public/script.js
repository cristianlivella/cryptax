const beforePrint = () => {
    for (const id in Chart.instances) {
        Chart.instances[id].resize();
    }
}

window.onbeforeprint = beforePrint;

if (window.matchMedia) {
    const mediaQueryList = window.matchMedia('print');
     mediaQueryList.addListener((mql) => {
         if (mql.matches) {
             beforePrint();
         }
     });
}

const generateColors = (num, paletteName = 'mpn65') => {
    const scheme = palette.listSchemes(paletteName)[0];
    return scheme.apply(scheme, [num]).reverse().map(color => {
        return '#' + color;
    })
}

const ctx1 = document.getElementById('chart1').getContext('2d');

const config1 = {
    type: 'line',
    data: {
        labels: daysList,
        datasets: [
            {
                label: 'Controvalore con prezzi al giorno 01/01/' + fiscalYear,
                data: dailyTotalValuesLegal,
                borderColor: ['rgb(54, 162, 235)'],
                backgroundColor:'rgb(54, 162, 235)',
                fill: false,
                yAxisID: 'y-axis-1',
            },
            {
                label: 'Controvalore con prezzi al giorno 31/12/' + fiscalYear,
                data: dailyTotalValuesEOY,
                borderColor: ['rgb(255, 159, 64)'],
                backgroundColor:'rgb(255, 159, 64)',
                fill: false,
                yAxisID: 'y-axis-1',
            },
            {
                label: 'Controvalore reale',
                data: dailyTotalValuesReal,
                borderColor: ['rgb(255, 99, 132)',],
                backgroundColor:'rgb(255, 99, 132)',
                fill: false,
                yAxisID: 'y-axis-1',
            }
        ]
    },
    options: {
        scales: {
            yAxes: [{
                type: 'linear',
                display: true,
                position: 'left',
                id: 'y-axis-1',
                ticks: {
                    beginAtZero: true
                },
                scaleLabel: {
                    display: true,
                    labelString: 'euro'
                }
            }],
        },
        responsive:true,
        maintainAspectRatio: false,
        title: {
            display: true,
            fontSize: 18,
            fontColor: '#212529',
            text: 'Controvalore in euro'
        },
        tooltips: {
            callbacks: {
                label: function(tooltipItem, data) {
                    const labels = ['01/01', '31/12', 'reale']
                    return labels[tooltipItem.datasetIndex] + ': €' + tooltipItem.value.toString().replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                },
            },
            mode: 'index',
            intersect: false,
            position: 'nearest',
        },
        elements: {
            point:{
                radius: 0
            }
        }
    }
};

const chart1 = new Chart(ctx1, config1);

if (document.getElementById('chart2')) {
    const ctx2 = document.getElementById('chart2').getContext('2d');

    const config2 = {
        type: 'doughnut',
        data: {
            labels: exchanges,
            datasets: [{
                data: exchangeVolumes,
                borderColor: generateColors(exchanges.length).reverse(),
                backgroundColor: generateColors(exchanges.length).reverse(),
                fill: true,
                yAxisID: 'y-axis-1',
            }
        ]
        },
        options: {
            scales: {
                yAxes: [],
            },
            responsive:true,
            maintainAspectRatio: false,
            title: {
                display: true,
                fontSize: 18,
                fontColor: '#212529',
                text: 'Volumi exchange'
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        return data.labels[tooltipItem.index] + ': €' + data.datasets[0].data[tooltipItem.index].toFixed(2).replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    },
                },
                mode: 'index',
                intersect: false,
                position: 'nearest',
            },
            elements: {
                point:{
                    radius: 0
                }
            }
        }
    };

    const chart2 = new Chart(ctx2, config2);
}
