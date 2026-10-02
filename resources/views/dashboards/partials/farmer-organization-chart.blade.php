{{-- Renders the farmer-by-organization bar chart + legend for a given canvas/legend/status id set. --}}
<script type="application/json" data-organization-chart-config>{!! json_encode([
    'canvasId' => $canvasId,
    'statusId' => $statusId,
    'legendId' => $legendId,
    'compact' => (bool) ($compact ?? false),
    'chartData' => $chartData,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script>
(() => {
    // Each include reads the config block right before it; the IIFE keeps several charts on one page from clashing.
    const chartConfig = JSON.parse(document.currentScript.previousElementSibling.textContent);
    const initializeOrganizationChart = async () => {
        const organizationCanvas = document.getElementById(chartConfig.canvasId);
        const organizationChartData = chartConfig.chartData;
        const statusTarget = document.getElementById(chartConfig.statusId);
        const chartState = organizationCanvas?.parentElement?.querySelector('[data-organization-chart-state]');
        const setLoadingState = (message, ready = false) => {
            if (chartState) {
                chartState.textContent = message;
                chartState.hidden = ready;
            }
        };

        if (!organizationCanvas) return;
        if (statusTarget) statusTarget.textContent = 'Loading real farmer organization data...';
        setLoadingState('Loading real farmer organization data...');

        await (window.iClimateVisualsReady || Promise.resolve());

        const organizationLabels = Array.isArray(organizationChartData?.labels) ? organizationChartData.labels : [];
        const organizationCounts = Array.isArray(organizationChartData?.counts)
            ? organizationChartData.counts.map((count) => Number(count) || 0)
            : [];

        if (!window.Chart) {
            organizationCanvas.hidden = true;
            setLoadingState('The organization chart could not load. Refresh the page to try again.');
            if (statusTarget) statusTarget.textContent = 'The organization chart could not load. Refresh the page to try again.';
            return;
        }

        if (!organizationLabels.length || !organizationCounts.some((count) => count > 0)) {
            organizationCanvas.hidden = true;
            setLoadingState('No registered farmer organization records found.');
            if (statusTarget) statusTarget.textContent = 'No registered farmer organization records found.';
            return;
        }

        try {
            const chartLabels = window.iClimateChartLabels || {};
            const organizationDisplayLabels = organizationLabels.map((label) => label === 'None' ? 'No Organization' : label);
            const organizationColors = [
                '#1F5A46', '#4B7185', '#8FAF9A', '#B84A4A', '#C58B2A', '#2F855A',
                '#256D85', '#7B8F45', '#8B5E3C', '#6F8978', '#3A7D44', '#596E79',
                '#9A6B2F', '#4E6E5D', '#8C4A5B', '#5F8F78', '#64748B',
            ];
            const colorForOrganization = (index) => organizationColors[index % organizationColors.length];
            const escapeLegendText = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[char]));
            const countLabel = (count) => `${count} farmer${count === 1 ? '' : 's'}`;
            const setOrganizationStatus = (index) => {
                const count = Number(organizationCounts[index] ?? 0);
                const label = organizationDisplayLabels[index] || 'Organization';

                if (statusTarget) statusTarget.textContent = `${label}: ${countLabel(count)}`;
            };

            const maxOrganizationCount = Math.max(...organizationCounts, 0);
            const isCompact = chartConfig.compact;

            // Many bars crowd vertical labels (rotated, overlapping). Switch to a
            // horizontal layout past this threshold so labels stay flat and readable.
            const HORIZONTAL_BAR_THRESHOLD = 6;
            const isHorizontal = organizationLabels.length > HORIZONTAL_BAR_THRESHOLD;

            const organizationChartInner = organizationCanvas.closest('.farmer-org-chart-inner');
            if (organizationChartInner) {
                organizationChartInner.style.minWidth = '0';
                organizationChartInner.style.width = '100%';
                // Keep chart as one whole view without requiring vertical scrolling or expanding oversized
                organizationChartInner.style.height = isCompact ? '380px' : '400px';
            }

            const organizationValueLabels = {
                id: chartConfig.canvasId + 'ValueLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    const meta = chart.getDatasetMeta(0);
                    const zeroY = chart.scales.y?.getPixelForValue(0) ?? chart.chartArea.bottom;

                    ctx.save();
                    ctx.font = '700 11px Inter, system-ui, sans-serif';
                    ctx.fillStyle = '#1F2937';
                    ctx.textAlign = isHorizontal ? 'left' : 'center';
                    ctx.textBaseline = isHorizontal ? 'middle' : 'bottom';
                    meta.data.forEach((bar, index) => {
                        const value = Number(chart.data.datasets[0].data[index] ?? 0);
                        if (isHorizontal) {
                            ctx.fillText(String(value), bar.x + 6, bar.y);
                        } else {
                            const labelY = value > 0 ? bar.y - 6 : zeroY - 6;
                            ctx.fillText(String(value), bar.x, labelY);
                        }
                    });
                    ctx.restore();
                },
            };
            const legendTarget = document.getElementById(chartConfig.legendId);
            if (legendTarget) {
                legendTarget.innerHTML = organizationDisplayLabels.map((label, index) => {
                    const count = Number(organizationCounts[index] ?? 0);
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

            const organizationAbbrMap = {
                'Balibago Farmers Association': 'Balibago FA',
                'Cuaco Irrigator\'s Association': 'Cuaco IA',
                'Bungahan Baldeo Irrigators Association Inc': 'Bungahan Baldeo IA',
                'Molino-Resagwa Farmers Irrigators Association Inc': 'Molino-Resagwa IA',
                'Lumang Tubigan Farmers Association': 'Lumang Tubigan FA',
                'Saluysoy Farmers Association': 'Saluysoy FA',
                'Luyahan Farmers Association': 'Luyahan FA',
                'Malaruhatan Farmers Association': 'Malaruhatan FA',
                'Matabungkay Farmers Association': 'Matabungkay FA',
                'Prenza Bagbag Bungahan Farmers Association': 'Prenza Bagbag Bungahan FA',
                'Sitio Calaca Barangay Prenza Lian, Batangas Farmers Assn. Inc.': 'Calaca FA',
                'Laguluan Spring Irrigators FA': 'Laguluan FA',
                'Masipag Prenza Organic Farmers Association': 'MPOFA',
                'Prenza Ladron Balanoy Sampalocan Calumpit Irrigators Association Inc.': 'PreLaBaSaKa IA',
                'Samahan ng Gintong Pagasa Ng Magsasaka Ng Lian, Inc.': 'Gintong Pag-asa FA',
                'Tan-ag Farmers Association': 'Tan-ag FA',
            };

            const organizationChart = new window.Chart(organizationCanvas, {
                type: 'bar',
                data: {
                    labels: organizationDisplayLabels,
                    datasets: [{
                        label: 'Registered farmers',
                        data: organizationCounts,
                        borderWidth: 1,
                        borderColor: organizationDisplayLabels.map((label, index) => colorForOrganization(index)),
                        backgroundColor: organizationDisplayLabels.map((label, index) => colorForOrganization(index)),
                        borderRadius: 6,
                        barPercentage: .65,
                        categoryPercentage: .72,
                    }],
                },
                options: {
                    indexAxis: isHorizontal ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 420 },
                    layout: { padding: isHorizontal ? { right: 28 } : { top: 24, right: 14, bottom: 4, left: 4 } },
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
                            ticks: { precision: 0, color: '#64748B', font: { size: 10 } },
                            grid: { color: 'rgba(95,143,120,.18)', drawBorder: false },
                        },
                        y: {
                            ticks: {
                                color: '#1F2937',
                                autoSkip: false,
                                font: { size: 10.5, weight: 600 },
                                callback: function(value, index) {
                                    const label = organizationDisplayLabels[index] || '';
                                    return organizationAbbrMap[label] || (label.length > 25 ? label.substring(0, 23) + '…' : label);
                                },
                            },
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
            organizationCanvas.hidden = false;
            setLoadingState('', true);
            organizationCanvas.removeAttribute('aria-busy');
            if (statusTarget) statusTarget.textContent = `Chart loaded: ${organizationCounts.reduce((sum, count) => sum + count, 0)} registered farmers.`;

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
        } catch (error) {
            organizationCanvas.hidden = true;
            setLoadingState('The organization chart could not be drawn. The totals remain available above.');
            if (statusTarget) statusTarget.textContent = 'The organization chart could not be drawn. The totals remain available above.';
            console.error('iClimate organization chart failed to render.', error);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeOrganizationChart, { once: true });
    } else {
        initializeOrganizationChart();
    }
})();
</script>
