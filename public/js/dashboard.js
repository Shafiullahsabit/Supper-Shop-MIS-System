(() => {
    const data = window.DASHBOARD_CHART_DATA;
    const chart = document.querySelector('[data-dashboard-revenue-chart]');

    if (!data || !chart) return;

    const svg = chart.querySelector('#dashboardRevenueLine');
    const title = chart.querySelector('#dashboardRevenueTitle');
    const subtitle = chart.querySelector('#dashboardRevenueSubtitle');
    const total = chart.querySelector('#dashboardRevenueTotal');
    const periodSwitch = chart.querySelector('#dashboardPeriodSwitch');
    const modeButtons = [...chart.querySelectorAll('[data-chart-mode]')];
    const periodButtons = [...chart.querySelectorAll('[data-chart-period]')];
    const panels = [...chart.querySelectorAll('[data-chart-panel]')];
    const svgNamespace = 'http://www.w3.org/2000/svg';
    let activeMode = 'bars';
    let activePeriod = 'daily';

    function formatMoney(value) {
        const amount = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value) || 0);

        return data.currencyPosition === 'after'
            ? `${amount}${data.currencySymbol}`
            : `${data.currencySymbol}${amount}`;
    }

    function element(name, attributes = {}) {
        const node = document.createElementNS(svgNamespace, name);

        Object.entries(attributes).forEach(([key, value]) => {
            node.setAttribute(key, value);
        });

        return node;
    }

    function renderLineChart(period) {
        const points = data[period] || [];
        const values = points.map(point => Number(point.value) || 0);
        const width = 760;
        const height = 300;
        const plot = { left: 88, right: 18, top: 20, bottom: 244 };
        const plotWidth = width - plot.left - plot.right;
        const plotHeight = plot.bottom - plot.top;
        const maximum = Math.max(...values, 0) || 1;
        const upperBound = maximum * 1.1;

        svg.replaceChildren();
        svg.setAttribute('aria-label', data.labels.svgTitle);

        const accessibleTitle = element('title');
        accessibleTitle.textContent = data.labels.svgTitle;
        svg.append(accessibleTitle);

        const defs = element('defs');
        const gradient = element('linearGradient', {
            id: 'dashboardRevenueGradient',
            x1: '0',
            x2: '0',
            y1: '0',
            y2: '1',
        });
        gradient.append(
            element('stop', { offset: '0%', 'stop-color': '#3b72e8', 'stop-opacity': '.24' }),
            element('stop', { offset: '100%', 'stop-color': '#3b72e8', 'stop-opacity': '.015' }),
        );
        defs.append(gradient);
        svg.append(defs);

        for (let tick = 0; tick <= 4; tick++) {
            const ratio = tick / 4;
            const y = plot.bottom - ratio * plotHeight;
            const value = upperBound * ratio;
            const grid = element('line', {
                x1: plot.left,
                x2: width - plot.right,
                y1: y,
                y2: y,
                class: 'chart-grid',
            });
            const label = element('text', {
                x: plot.left - 12,
                y: y + 4,
                'text-anchor': 'end',
                class: 'chart-axis-label',
                direction: 'ltr',
            });
            label.textContent = formatMoney(value);
            svg.append(grid, label);
        }

        if (!points.length) return;

        const coordinates = points.map((point, index) => ({
            x: plot.left + (points.length === 1 ? plotWidth / 2 : (index * plotWidth) / (points.length - 1)),
            y: plot.bottom - ((Number(point.value) || 0) / upperBound) * plotHeight,
            point,
        }));
        const linePath = coordinates
            .map(({ x, y }, index) => `${index === 0 ? 'M' : 'L'} ${x} ${y}`)
            .join(' ');
        const areaPath = `${linePath} L ${coordinates[coordinates.length - 1].x} ${plot.bottom} L ${coordinates[0].x} ${plot.bottom} Z`;

        svg.append(
            element('path', { d: areaPath, class: 'chart-area' }),
            element('path', { d: linePath, class: 'chart-line' }),
        );

        coordinates.forEach(({ x, y, point }) => {
            const marker = element('circle', {
                cx: x,
                cy: y,
                r: 4.5,
                class: 'chart-point',
                tabindex: '0',
                'aria-label': `${point.date}: ${formatMoney(point.value)}`,
            });
            const tooltip = element('title');
            tooltip.textContent = `${point.date}: ${formatMoney(point.value)}`;
            marker.append(tooltip);
            svg.append(marker);

            const label = element('text', {
                x,
                y: height - 14,
                'text-anchor': 'middle',
                class: 'chart-axis-label',
            });
            label.textContent = point.label;
            svg.append(label);
        });
    }

    function updateChart() {
        const isLine = activeMode === 'line';
        const period = isLine ? activePeriod : 'daily';
        const points = data[period] || [];
        const labels = data.labels;

        panels.forEach(panel => {
            panel.hidden = panel.dataset.chartPanel !== activeMode;
        });
        periodSwitch.hidden = !isLine;
        modeButtons.forEach(button => {
            const selected = button.dataset.chartMode === activeMode;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        periodButtons.forEach(button => {
            const selected = button.dataset.chartPeriod === activePeriod;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });

        title.textContent = isLine
            ? labels[activePeriod === 'daily' ? 'dailyTitle' : 'weeklyTitle']
            : labels.barTitle;
        subtitle.textContent = isLine
            ? labels[activePeriod === 'daily' ? 'dailySubtitle' : 'weeklySubtitle']
            : labels.dailySubtitle;
        total.textContent = formatMoney(points.reduce((sum, point) => sum + (Number(point.value) || 0), 0));

        if (isLine) renderLineChart(activePeriod);
    }

    modeButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeMode = button.dataset.chartMode;
            updateChart();
        });
    });

    periodButtons.forEach(button => {
        button.addEventListener('click', () => {
            activePeriod = button.dataset.chartPeriod;
            updateChart();
        });
    });

    updateChart();
})();
