import { useEffect, useState } from 'react';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import type { MonthlyPoint } from '../types';
import { EmptyState, Skeleton } from './ui';

// Brand-aligned categorical pair, fixed order; validated with the dataviz palette checker for each mode
// (lightness band, chroma, CVD separation >= 8, contrast vs surface).
const PALETTE = {
  light: { series1: '#0a8f73', series2: '#b85f1e' },
  dark: { series1: '#0f9b7c', series2: '#d0702a' },
};

/** Tracks the dark class on <html> so charts repaint when the theme is toggled. */
function useChartColors() {
  const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));
  useEffect(() => {
    const observer = new MutationObserver(() => setDark(document.documentElement.classList.contains('dark')));
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    return () => observer.disconnect();
  }, []);
  return dark ? PALETTE.dark : PALETTE.light;
}
const AXIS = { fontSize: 12, fill: 'currentColor' };
const tooltipStyle = { borderRadius: 10, border: '1px solid #e7e5e4', fontSize: 13, color: '#1c1917' };

function ChartFrame({
  title,
  loading,
  empty,
  children,
}: {
  title: string;
  loading: boolean;
  empty: boolean;
  children: React.ReactNode;
}) {
  return (
    <figure className="card">
      <figcaption className="mb-4 font-display text-lg font-semibold">{title}</figcaption>
      {loading ? (
        <Skeleton className="h-64 w-full" />
      ) : empty ? (
        <EmptyState title="No data for this period" />
      ) : (
        <div className="h-64 text-stone-500 dark:text-stone-400">{children}</div>
      )}
    </figure>
  );
}

export function MonthlyIssuesChart({
  data,
  loading,
}: {
  data: MonthlyPoint[] | undefined;
  loading: boolean;
}) {
  const colors = useChartColors();
  return (
    <ChartFrame
      title="Issues and returns per month"
      loading={loading}
      empty={!data?.some((p) => p.issues + p.returns > 0)}
    >
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data} margin={{ top: 4, right: 12, bottom: 0, left: -12 }}>
          <CartesianGrid vertical={false} strokeOpacity={0.15} />
          <XAxis dataKey="label" tick={AXIS} tickLine={false} axisLine={false} interval="preserveStartEnd" />
          <YAxis allowDecimals={false} tick={AXIS} tickLine={false} axisLine={false} />
          <Tooltip contentStyle={tooltipStyle} />
          <Legend wrapperStyle={{ fontSize: 13 }} />
          <Line
            type="monotone"
            dataKey="issues"
            name="Issued"
            stroke={colors.series1}
            strokeWidth={2}
            dot={{ r: 4 }}
            activeDot={{ r: 6 }}
          />
          <Line
            type="monotone"
            dataKey="returns"
            name="Returned"
            stroke={colors.series2}
            strokeWidth={2}
            dot={{ r: 4 }}
            activeDot={{ r: 6 }}
          />
        </LineChart>
      </ResponsiveContainer>
    </ChartFrame>
  );
}

/** Horizontal ranking bars (one series, so no legend: the title names it). */
export function RankingChart<T extends object>({
  title,
  data,
  labelKey,
  valueKey,
  valueLabel,
  loading,
}: {
  title: string;
  data: T[] | undefined;
  labelKey: keyof T & string;
  valueKey: keyof T & string;
  valueLabel: string;
  loading: boolean;
}) {
  const colors = useChartColors();
  return (
    <ChartFrame title={title} loading={loading} empty={!data?.length}>
      <ResponsiveContainer width="100%" height="100%">
        <BarChart
          data={data}
          layout="vertical"
          margin={{ top: 0, right: 16, bottom: 0, left: 8 }}
          barCategoryGap={6}
        >
          <CartesianGrid horizontal={false} strokeOpacity={0.15} />
          <XAxis type="number" allowDecimals={false} tick={AXIS} tickLine={false} axisLine={false} />
          <YAxis
            type="category"
            dataKey={labelKey}
            width={168}
            tick={AXIS}
            tickLine={false}
            axisLine={false}
            tickFormatter={(v: string) => (v.length > 24 ? `${v.slice(0, 23)}…` : v)}
          />
          <Tooltip contentStyle={tooltipStyle} cursor={{ fillOpacity: 0.06 }} />
          <Bar
            dataKey={valueKey}
            name={valueLabel}
            fill={colors.series1}
            radius={[0, 4, 4, 0]}
            maxBarSize={18}
          />
        </BarChart>
      </ResponsiveContainer>
    </ChartFrame>
  );
}
