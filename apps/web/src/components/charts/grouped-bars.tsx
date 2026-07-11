"use client";

import { useState } from "react";

export interface GroupedBarsDatum {
  label: string;
  a: number; // série 1
  b: number; // série 2
}

/**
 * Barras agrupadas com 2 séries (SVG inline, sem lib).
 * Segue o design system de dataviz: barras finas com topo arredondado 4px
 * ancorado na baseline, gap entre barras, grid recessivo, texto em tokens de
 * texto (nunca na cor da série), tooltip por barra e tabela acessível.
 */
export function GroupedBars({
  data,
  seriesNames,
  title,
}: {
  data: GroupedBarsDatum[];
  seriesNames: [string, string];
  title: string;
}) {
  const [hover, setHover] = useState<{ i: number; s: "a" | "b" } | null>(null);

  const W = 560;
  const H = 220;
  const PAD = { top: 12, right: 8, bottom: 28, left: 30 };
  const innerW = W - PAD.left - PAD.right;
  const innerH = H - PAD.top - PAD.bottom;
  const max = Math.max(...data.flatMap((d) => [d.a, d.b])) * 1.15;

  const groupW = innerW / data.length;
  const barW = 16;
  const gap = 2; // gap de 2px entre barras adjacentes (spec de marcas)

  const y = (v: number) => PAD.top + innerH - (v / max) * innerH;
  const ticks = [0, Math.round(max / 2), Math.round(max)];

  return (
    <figure>
      {/* Legenda: obrigatória para ≥2 séries; identidade nunca só pela cor */}
      <figcaption className="mb-3 flex items-center gap-5 text-xs text-ink-muted">
        <span className="flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 rounded-sm" style={{ background: "var(--chart-1)" }} />
          {seriesNames[0]}
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 rounded-sm" style={{ background: "var(--chart-2)" }} />
          {seriesNames[1]}
        </span>
      </figcaption>

      <div className="relative">
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label={title}>
          {/* Grid recessivo */}
          {ticks.map((t) => (
            <g key={t}>
              <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} stroke="var(--line)" strokeWidth={1} />
              <text x={PAD.left - 6} y={y(t) + 3} textAnchor="end" fontSize={10} fill="var(--ink-muted)">
                {t}
              </text>
            </g>
          ))}

          {data.map((d, i) => {
            const cx = PAD.left + groupW * i + groupW / 2;
            const bars: { s: "a" | "b"; v: number; x: number; fill: string }[] = [
              { s: "a", v: d.a, x: cx - barW - gap / 2, fill: "var(--chart-1)" },
              { s: "b", v: d.b, x: cx + gap / 2, fill: "var(--chart-2)" },
            ];
            return (
              <g key={d.label}>
                {bars.map((bar) => {
                  const h = PAD.top + innerH - y(bar.v);
                  const dimmed = hover && !(hover.i === i && hover.s === bar.s);
                  return (
                    <g key={bar.s}>
                      {/* Topo arredondado 4px, base reta ancorada na baseline */}
                      <path
                        d={`M ${bar.x} ${y(bar.v) + 4}
                            a 4 4 0 0 1 4 -4 h ${barW - 8} a 4 4 0 0 1 4 4
                            V ${PAD.top + innerH} H ${bar.x} Z`}
                        fill={bar.fill}
                        opacity={dimmed ? 0.35 : 1}
                        style={{ transition: "opacity 150ms cubic-bezier(0.2,0,0,1)" }}
                      />
                      {/* Hit target maior que a marca */}
                      <rect
                        x={bar.x - 4}
                        y={PAD.top}
                        width={barW + 8}
                        height={innerH}
                        fill="transparent"
                        onMouseEnter={() => setHover({ i, s: bar.s })}
                        onMouseLeave={() => setHover(null)}
                      />
                    </g>
                  );
                })}
                <text x={cx} y={H - 8} textAnchor="middle" fontSize={10} fill="var(--ink-muted)">
                  {d.label}
                </text>
              </g>
            );
          })}
        </svg>

        {/* Tooltip */}
        {hover && (
          <div
            className="pointer-events-none absolute rounded-lg border border-line bg-surface px-3 py-2 text-xs shadow-lg"
            style={{
              left: `${((PAD.left + groupW * hover.i + groupW / 2) / W) * 100}%`,
              top: 0,
              transform: "translateX(-50%)",
            }}
          >
            <div className="font-medium text-ink">{data[hover.i].label}</div>
            <div className="mt-0.5 text-ink-muted">
              {hover.s === "a" ? seriesNames[0] : seriesNames[1]}:{" "}
              <span className="font-semibold text-ink">
                {hover.s === "a" ? data[hover.i].a : data[hover.i].b}
              </span>
            </div>
          </div>
        )}
      </div>

      {/* Vista em tabela (acessibilidade) */}
      <table className="sr-only">
        <caption>{title}</caption>
        <thead>
          <tr><th>Mês</th><th>{seriesNames[0]}</th><th>{seriesNames[1]}</th></tr>
        </thead>
        <tbody>
          {data.map((d) => (
            <tr key={d.label}><td>{d.label}</td><td>{d.a}</td><td>{d.b}</td></tr>
          ))}
        </tbody>
      </table>
    </figure>
  );
}
