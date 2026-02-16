/**
 * Minimal SVG line chart
 * Usage: drawSimpleLineChart(containerEl, { labels: [...], values: [...] }, { height, width })
 */
function drawSimpleLineChart(containerEl, series, opts) {
  if (!containerEl) return;
  containerEl.innerHTML = ""; // clear
  const width = (opts && opts.width) || containerEl.clientWidth || 600;
  const height = (opts && opts.height) || 220;
  const padding = { top: 20, right: 20, bottom: 30, left: 40 };
  const w = Math.max(100, width);
  const h = Math.max(120, height);
  const svgNS = "http://www.w3.org/2000/svg";
  const svg = document.createElementNS(svgNS, "svg");
  svg.setAttribute("viewBox", `0 0 ${w} ${h}`);
  svg.setAttribute("width", w);
  svg.setAttribute("height", h);
  svg.style.display = "block";

  const labels = series.labels || [];
  const values = series.values || [];
  const n = values.length;
  if (n === 0) {
    const t = document.createElementNS(svgNS, "text");
    t.setAttribute("x", w/2); t.setAttribute("y", h/2);
    t.setAttribute("text-anchor", "middle");
    t.setAttribute("font-size", "14");
    t.textContent = "No data yet";
    svg.appendChild(t);
    containerEl.appendChild(svg);
    return;
  }

  const x0 = padding.left, x1 = w - padding.right;
  const y0 = h - padding.bottom, y1 = padding.top;

  // Use fixed Y-axis scale from 0 to 5
  const minY = 0;
  const maxY = 5;
  const yRange = maxY - minY;

  function xScale(i) {
    if (n === 1) return (x0 + x1) / 2;
    return x0 + (i * (x1 - x0)) / (n - 1);
  }
  function yScale(v) {
    return y0 - ((v - minY) * (y0 - y1)) / yRange;
  }

  // Axes
  const axisStyle = "stroke:#9CA3AF;stroke-width:1";
  // y-axis
  const yAxis = document.createElementNS(svgNS, "line");
  yAxis.setAttribute("x1", x0); yAxis.setAttribute("y1", y0);
  yAxis.setAttribute("x2", x0); yAxis.setAttribute("y2", y1);
  yAxis.setAttribute("style", axisStyle);
  svg.appendChild(yAxis);
  // x-axis
  const xAxis = document.createElementNS(svgNS, "line");
  xAxis.setAttribute("x1", x0); xAxis.setAttribute("y1", y0);
  xAxis.setAttribute("x2", x1); xAxis.setAttribute("y2", y0);
  xAxis.setAttribute("style", axisStyle);
  svg.appendChild(xAxis);

  // Gridlines and labels (fixed at 0, 1, 2, 3, 4, 5)
  for (let t = 0; t <= 5; t++) {
    const yVal = t;
    const y = yScale(yVal);
    const gl = document.createElementNS(svgNS, "line");
    gl.setAttribute("x1", x0); gl.setAttribute("y1", y);
    gl.setAttribute("x2", x1); gl.setAttribute("y2", y);
    gl.setAttribute("style", "stroke:#E5E7EB;stroke-width:1");
    svg.appendChild(gl);

    const lbl = document.createElementNS(svgNS, "text");
    lbl.setAttribute("x", x0 - 8);
    lbl.setAttribute("y", y + 4);
    lbl.setAttribute("text-anchor", "end");
    lbl.setAttribute("font-size", "10");
    lbl.setAttribute("fill", "#374151");
    lbl.textContent = yVal.toFixed(0);
    svg.appendChild(lbl);
  }

  // Line path
  let d = "";
  values.forEach((v, i) => {
    const x = xScale(i), y = yScale(v);
    d += (i === 0 ? `M ${x} ${y}` : ` L ${x} ${y}`);
  });
  const path = document.createElementNS(svgNS, "path");
  path.setAttribute("d", d);
  path.setAttribute("fill", "none");
  path.setAttribute("stroke", "#2563EB");
  path.setAttribute("stroke-width", "2");
  svg.appendChild(path);

  // Dots
  values.forEach((v, i) => {
    const x = xScale(i), y = yScale(v);
    const c = document.createElementNS(svgNS, "circle");
    c.setAttribute("cx", x); c.setAttribute("cy", y); c.setAttribute("r", 3);
    c.setAttribute("fill", "#1F2937");
    svg.appendChild(c);
  });

  containerEl.appendChild(svg);
}

// helper to auto-resize charts when container width changes
function observeResize(el, cb) {
  if (!el) return;
  const ro = new ResizeObserver(cb);
  ro.observe(el);
  return () => ro.disconnect();
}