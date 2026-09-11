{{-- Renders the farmer-by-organization bar chart + legend for a given canvas/legend/status id set. --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const organizationCanvas = document.getElementById('{{ $canvasId }}');
        const organizationChartData = @json($chartData);

        if (organizationCanvas && window.Chart && organizationChartData?.labels?.length) {
            const chartLabels = window.iClimateChartLabels || {};
            const organizationDisplayLabels = organizationChartData.labels.map((label) => label === 'None' ? 'No Organization' : label);
            const organizationColors = [
                '#1F5A46', '#4B7185', '#8FAF9A', '#B84A4A', '#C58B2A', '#2F855A',
                '#256D85', '#7B8F45', '#8B5E3C', '#6F8978', '#3A7D44', '#596E79',
                '#9A6B2F', '#4E6E5D', '#8C4A5B', '#5F8F78', '#64748B',
            ];
            const colorForOrganization = (index) => organizationColors[index % organizationColors.length];
            const escapeLegendText = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
            const countLabel = (count) => `${count} farmer${count === 1 ? '' : 's'}`;
            const statusTarget = document.getElementById('{{ $statusId }}');
            const setOrganizationStatus = (index) => {
                const count = Number(organizationChartData.counts[index] ?? 0);
                const label = organizationDisplayLabels[index] || 'Organization';

                if (statusTarget) statusTarget.textContent = `${label}: ${countLabel(count)}`;
            };

            const maxOrganizationCount = Math.max(...organizationChartData.counts, 0);

            // Many bars crowd vertical labels (rotated, overlapping). Switch to a
            // horizontal layout past this threshold so labels stay flat and readable.
            const HORIZONTAL_BAR_THRESHOLD = 6;
            const isHorizontal = organizationChartData.labels.length > HORIZONTAL_BAR_THRESHOLD;

            const organizationChartInner = organizationCanvas.closest('.farmer-org-chart-inner');
            if (organizationChartInner) {
                if (isHorizontal) {
                    organizationChartInner.style.minWidth = '0';
                    organizationChartInner.style.height = `${Math.max(480, organizationChartData.labels.length * 46)}px`;
                } else {
                    organizationChartInner.style.minWidth = '1180px';
                    organizationChartInner.style.height = '480px';
                }
            }

            const organizationValueLabels = {
                id: '{{ $canvasId }}ValueLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    const meta = chart.getDatasetMeta(0);
                    const zeroY = chart.scales.y?.getPixelForValue(0) ?? chart.chartArea.bottom;

                    ctx.save();
                    ctx.font = '700 16px Inter, system-ui, sans-serif';
                    ctx.fillStyle = '#1F2937';
                    ctx.textAlign = isHorizontal ? 'left' : 'center';
                    ctx.textBaseline = isHorizontal ? 'middle' : 'bottom';
                    meta.data.forEach((bar, index) => {
                        const value = Number(chart.data.datasets[0].data[index] ?? 0);
                        if (isHorizontal) {
                            ctx.fillText(String(value), bar.x + 8, bar.y);
                        } else {
                            const labelY = value > 0 ? bar.y - 6 : zeroY - 6;
                            ctx.fillText(String(value), bar.x, labelY);
                        }
                    });
                    ctx.restore();
                },
            };
            const legendTarget = document.getElementById('{{ $legendId }}');
            if (legendTarget) {
                legendTarget.innerHTML = organizationDisplayLabels.map((label, index) => {
                    const count = Number(organizationChartData.counts[index] ?? 0);
                    const ariaLabel = `${label}: ${countLabel(count)}`;

                    return `
                        <button type="button" class="farmer-org-legend-item" data-organization-index="${index}" aria-label="${escapeLegendText(ariaLabel)}" title="${escapeLegendText(ariaLabel)}">
                            <span class="farmer-org-legend-swatch" style="background:${colorForOrganization(index)}"></span>
                            <span class="farmer-org-legend-name">${escapeLegendText(label)}</span>
                            <span class="farmer-org-legend-count">${count}</span>
                        </button>
                    `;
                }).join('');
            }

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
                        borderRadius: 7,
                        barPercentage: .68,
                        categoryPercentage: .72,
                    }],
                },
                options: {
                    indexAxis: isHorizontal ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 420 },
                    layout: { padding: isHorizontal ? { right: 32 } : { top: 24, right: 14, bottom: 4, left: 4 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => organizationDisplayLabels[items[0].dataIndex] || '',
                                label: (context) => {
                                    const value = (isHorizontal ? context.parsed.x : context.parsed.y) || 0;
                                    return `${value} registered farmer${value === 1 ? '' : 's'}`;
                                },
                            },
                        },
                    },
                    scales: isHorizontal ? {
                        x: {
                            beginAtZero: true,
                            suggestedMax: maxOrganizationCount + 1,
                            ticks: { precision: 0, color: '#64748B' },
                            grid: { color: 'rgba(95,143,120,.18)', drawBorder: false },
                        },
                        y: {
                            ticks: { color: '#1F2937', autoSkip: false, font: { size: 11, weight: 600 } },
                            grid: { display: false, drawBorder: false },
                        },
                    } : {
                        x: {
                            ticks: chartLabels.horizontalCategoryTicks
                                ? chartLabels.horizontalCategoryTicks({ display: false, color: '#1F2937', autoSkip: false, padding: 0, font: { size: 14, weight: 600 } })
                                : { display: false, color: '#1F2937', maxRotation: 0, minRotation: 0, autoSkip: false, padding: 0, font: { size: 14, weight: 600 } },
                            grid: { display: false, drawBorder: false },
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: maxOrganizationCount + 1,
                            ticks: { precision: 0, color: '#1F2937', font: { size: 14, weight: 700 }, padding: 8 },
                            grid: { color: 'rgba(95,143,120,.16)', drawBorder: false },
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

            legendTarget?.querySelectorAll('[data-organization-index]').forEach((item) => {
                const index = Number(item.dataset.organizationIndex);

                item.addEventListener('mouseenter', () => activateOrganization(index));
                item.addEventListener('focus', () => activateOrganization(index));
                item.addEventListener('click', () => activateOrganization(index));
                item.addEventListener('mouseleave', clearOrganization);
                item.addEventListener('blur', clearOrganization);
            });
            organizationCanvas.addEventListener('focus', () => setOrganizationStatus(0));
        }
    });
</script>
