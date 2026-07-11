"use client";

import { useState } from "react";

export interface TrendPoint {
  label: string;
  value: number;
}

/**
 * Linha de tendência de série única (o título nomeia a série — sem legenda).
 * Linha 2px, crosshair + tooltip no hover, grid recessivo, tabela acessível.
 */
export function TrendLine({
  data,
  title,
  unit = "%",
}: {
  data: TrendPoint[];
  title: string;
  unit?: string;
}) {
  const [hoverI, setHoverI] = useState<number | null>(null);

  const W = 560;
  const H = 220;
  const PAD = { top: 12, right: 8, bottom: 28, left: 34 };
  const innerW = W - PAD.left - PAD.right;
  const innerH = H - PAD.top - PAD.bottom;
  const max = Math.max(...data.map((d) => d.value)) * 1.25;

  const x = (i: number) => PAD.left + (i / (data.length - 1)) * innerW;
  const y = (v: number) => PAD.top + innerH - (v / max) * innerH;
  const path = data.map((d, i) => `${i === 0 ? "M" : "L"} ${x(i)} ${y(d.value)}`).join(" ");
  const ticks = [0, Number((max / 2).toFixed(1)), Number(max.toFixed(1))];

  return (
    <figure>
      <div className="relative">
        <svg
          viewBox={`0 0 ${W} ${H}`}
          className="w-full"
          role="img"
          aria-label={title}
          onMouseLeave={() => setHoverI(null)}
        >
          {ticks.map((t) => (
            <g key={t}>
              <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} stroke="var(--line)" strokeWidth={1} />
              <text x={PAD.left - 6} y={y(t) + 3} textAnchor="end" fontSize={10} fill="var(--ink-muted)">
                {t}{unit}
              </text>
            </g>
          ))}

          {/* Crosshair */}
          {hoverI !== null && (
            <line
              x1={x(hoverI)} x2={x(hoverI)} y1={PAD.top} y2={PAD.top + innerH}
              stroke="var(--ink-muted)" strokeWidth={1} strokeDasharray="3 3"
            />
          )}

          <path d={path} fill="none" stroke="var(--chart-1)" strokeWidth={2} strokeLinejoin="round" />

          {data.map((d, i) => (
            <g key={d.label}>
              {/* Marcador visível só no hover, com anel da cor da superfície */}
              {hoverI === i && (
                <circle cx={x(i)} cy={y(d.value)} r={4.5} fill="var(--chart-1)" stroke="var(--surface)" strokeWidth={2} />
              )}
              {/* Hit target por ponto (maior que a marca) */}
              <rect
                x={x(i) - innerW / data.length / 2}
                y={PAD.top}
                width={innerW / data.length}
                height={innerH}
                fill="transparent"
                onMouseEnter={() => setHoverI(i)}
              />
              <text x={x(i)} y={H - 8} textAnchor="middle" fontSize={10} fill="var(--ink-muted)">
                {d.label}
              </text>
            </g>
          ))}
        </svg>

        {hoverI !== null && (
          <div
            className="pointer-events-none absolute rounded-lg border border-line bg-surface px-3 py-2 text-xs shadow-lg"
            style={{ left: `${(x(hoverI) / W) * 100}%`, top: 0, transform: "translateX(-50%)" }}
          >
            <div className="font-medium text-ink">{data[hoverI].label}</div>
            <div className="mt-0.5 text-ink-muted">
              <span className="font-semibold text-ink">{data[hoverI].value}{unit}</span>
            </div>
          </div>
        )}
      </div>

      <table className="sr-only">
        <caption>{title}</caption>
        <thead><tr><th>Mês</th><th>{title}</th></tr></thead>
        <tbody>
          {data.map((d) => (
            <tr key={d.label}><td>{d.label}</td><td>{d.value}{unit}</td></tr>
          ))}
        </tbody>
      </table>
    </figure>
  );
}
