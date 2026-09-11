{{-- Renders the farmer-by-organization bar chart + legend for a given canvas/legend/status id set. --}}
@php
    $organizationChartPayloadId = $canvasId.'Payload';
    $organizationChartPayloadJson = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp
<script type="application/json" id="{{ $organizationChartPayloadId }}">{!! $organizationChartPayloadJson !!}</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const organizationCanvas = document.getElementById('{{ $canvasId }}');
        const readOrganizationChartPayload = (id) => {
            try {
                return JSON.parse(document.getElementById(id)?.textContent || '{"labels":[],"counts":[]}');
            } catch (error) {
                return { labels: [], counts: [] };
            }
        };
        const organizationChartData = readOrganizationChartPayload('{{ $organizationChartPayloadId }}');

        if (organizationCanvas && window.Chart && organizationChartData?.labels?.length) {
            const organizationDisplayLabels = organizationChartData.labels.map((label) => label === 'None' ? 'No Organization' : label);
            const truncateLabel = (label, maxLength) => {
                const text = String(label ?? '');
                return text.length > maxLength ? `${text.slice(0, Math.max(1, maxLength - 3))}...` : text;
            };
            const fitLabel = (label, maxWidth) => {
                const context = organizationCanvas.getContext('2d');
                const text = String(label ?? '');
                if (!context) return truncateLabel(text, 12);

                context.save();
                context.font = '700 14px Inter, system-ui, sans-serif';

                if (context.measureText(text).width <= maxWidth) {
                    context.restore();
                    return text;
                }

                let fitted = text;
                while (fitted.length > 4 && context.measureText(`${fitted}...`).width > maxWidth) {
                    fitted = fitted.slice(0, -1);
                }
                context.restore();

                return `${fitted}...`;
            };
            const organizationColors = [
                '#1F5A46', '#4B7185', '#8FAF9A', '#B84A4A', '#C58B2A', '#2F855A',
                '#256D85', '#7B8F45', '#8B5E3C', '#6F8978', '#3A7D44', '#596E79',
                '#9A6B2F', '#4E6E5D', '#8C4A5B', '#5F8F78', '#64748B',
            ];
            const colorForOrganization = (index) => organizationColors[index % organizationColors.length];
            const countLabel = (count) => `${count} farmer${count === 1 ? '' : 's'}`;
            const statusTarget = document.getElementById('{{ $statusId }}');
            const setOrganizationStatus = (index) => {
                const count = Number(organizationChartData.counts[index] ?? 0);
                const label = organizationDisplayLabels[index] || 'Organization';

                if (statusTarget) statusTarget.textContent = `${label}: ${countLabel(count)}`;
            };

            const maxOrganizationCount = Math.max(...organizationChartData.counts, 0);
            const organizationChartShell = organizationCanvas.closest('.farmer-org-chart-shell');
            const organizationChartInner = organizationCanvas.closest('.farmer-org-chart-inner');
            const breakpointFor = () => {
                if (window.innerWidth < 768) return 'mobile';
                if (window.innerWidth < 1024) return 'tablet';
                return 'desktop';
            };
            const chartSizing = () => {
                const breakpoint = breakpointFor();
                const rows = organizationDisplayLabels.length;
                const width = organizationChartShell?.clientWidth || organizationChartInner?.clientWidth || organizationCanvas.clientWidth || window.innerWidth;

                if (breakpoint === 'mobile') {
                    return {
                        height: Math.max(500, 80 + (rows * 34)),
                        labelArea: width < 380 ? 112 : 126,
                        barThickness: 12,
                        countFontSize: 15,
                    };
                }

                if (breakpoint === 'tablet') {
                    return {
                        height: Math.max(430, 76 + (rows * 26)),
                        labelArea: width < 820 ? 190 : 220,
                        barThickness: 13,
                        countFontSize: 15,
                    };
                }

                return {
                    height: Math.max(430, Math.min(500, 72 + (rows * 24))),
                    labelArea: width < 1120 ? 230 : 270,
                    barThickness: 12,
                    countFontSize: 16,
                };
            };
            let currentSizing = chartSizing();
            let organizationAxisLabels = organizationDisplayLabels.map((label) => fitLabel(label, Math.max(60, currentSizing.labelArea - 24)));

            if (organizationChartInner) {
                organizationChartInner.style.minWidth = '0';
                organizationChartInner.style.height = `${currentSizing.height}px`;
            }

            const organizationValueLabels = {
                id: '{{ $canvasId }}ValueLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    const meta = chart.getDatasetMeta(0);

                    ctx.save();
                    ctx.font = `700 ${currentSizing.countFontSize}px Inter, system-ui, sans-serif`;
                    ctx.fillStyle = '#1F2937';
                    ctx.textBaseline = 'middle';
                    meta.data.forEach((bar, index) => {
                        const value = Number(chart.data.datasets[0].data[index] ?? 0);
                        const text = String(value);
                        const textWidth = ctx.measureText(text).width;
                        const preferredX = bar.x + 8;
                        const maxX = chart.chartArea.right - 2;

                        ctx.textAlign = preferredX + textWidth > maxX ? 'right' : 'left';
                        ctx.fillText(text, ctx.textAlign === 'right' ? maxX : preferredX, bar.y);
                    });
                    ctx.restore();
                },
            };

            const organizationChart = new Chart(organizationCanvas, {
                type: 'bar',
                data: {
                    labels: organizationDisplayLabels,
                    datasets: [{
                        label: 'Registered farmers',
                        data: organizationChartData.counts,
                        borderWidth: 1,
                        borderColor: organizationDisplayLabels.map((label, index) => colorForOrganization(index)),
                        backgroundColor: organizationDisplayLabels.map((label, index) => colorForOrganization(index)),
                        borderRadius: 5,
                        barThickness: currentSizing.barThickness,
                        maxBarThickness: currentSizing.barThickness,
                        categoryPercentage: .62,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 420 },
                    layout: { padding: { top: 4, right: 44, bottom: 2, left: 0 } },
                    onHover: (event, elements) => {
                        organizationCanvas.style.cursor = elements.length ? 'pointer' : 'default';
                    },
                    onClick: (event, elements, chart) => {
                        if (!elements.length) return;
                        const index = elements[0].index;
                        const bar = chart.getDatasetMeta(0).data[index];

                        chart.setActiveElements([{ datasetIndex: 0, index }]);
                        chart.tooltip?.setActiveElements([{ datasetIndex: 0, index }], { x: bar.x, y: bar.y });
                        chart.update();
                        setOrganizationStatus(index);
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => organizationDisplayLabels[items[0].dataIndex] || '',
                                label: (context) => {
                                    const value = context.parsed.x || 0;
                                    return `${value} registered farmer${value === 1 ? '' : 's'}`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            suggestedMax: Math.max(1, Math.ceil(maxOrganizationCount * 1.12)),
                            ticks: { precision: 0, color: '#334155', font: { size: 14, weight: 600 }, padding: 4 },
                            grid: { color: 'rgba(95,143,120,.18)', drawBorder: false },
                        },
                        y: {
                            afterFit(scale) {
                                scale.width = Math.min(currentSizing.labelArea, Math.max(scale.width, currentSizing.labelArea));
                            },
                            ticks: {
                                color: '#1F2937',
                                autoSkip: false,
                                padding: 8,
                                font: { size: 14, weight: 700, lineHeight: 1.15 },
                                callback: (value, index) => organizationAxisLabels[index] || '',
                            },
                            grid: { display: false, drawBorder: false },
                        },
                    },
                },
                plugins: [organizationValueLabels],
            });

            const activateOrganization = (index) => {
                const bar = organizationChart.getDatasetMeta(0).data[index];
                if (!bar) return;

                organizationChart.setActiveElements([{ datasetIndex: 0, index }]);
                organizationChart.tooltip?.setActiveElements([{ datasetIndex: 0, index }], { x: bar.x, y: bar.y });
                organizationChart.update();
                setOrganizationStatus(index);
            };
            const clearOrganization = () => {
                organizationChart.setActiveElements([]);
                organizationChart.tooltip?.setActiveElements([], { x: 0, y: 0 });
                organizationChart.update();
            };
            const resizeOrganizationChart = () => {
                currentSizing = chartSizing();
                organizationAxisLabels = organizationDisplayLabels.map((label) => fitLabel(label, Math.max(60, currentSizing.labelArea - 24)));
                if (organizationChartInner) organizationChartInner.style.height = `${currentSizing.height}px`;
                organizationChart.data.datasets[0].barThickness = currentSizing.barThickness;
                organizationChart.data.datasets[0].maxBarThickness = currentSizing.barThickness;
                organizationChart.resize();
                organizationChart.update('none');
            };

            organizationCanvas.title = 'Hover or tap a bar to view the full organization name and farmer count.';
            organizationCanvas.addEventListener('focus', () => setOrganizationStatus(0));
            organizationCanvas.addEventListener('blur', clearOrganization);
            window.addEventListener('resize', resizeOrganizationChart);
            window.addEventListener('orientationchange', resizeOrganizationChart);
            if (window.ResizeObserver && organizationChartShell) {
                new ResizeObserver(resizeOrganizationChart).observe(organizationChartShell);
            }
        }
    });
</script>
