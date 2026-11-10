import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { Download } from 'lucide-react';
import { subYears } from 'date-fns';
import { downloadCsv, reportsApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { MonthlyIssuesChart, RankingChart } from '../../components/Charts';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/ui';
import { formatMoney, todayIso } from '../../utils/format';

export default function ReportsPage() {
  const [from, setFrom] = useState(() => subYears(new Date(), 1).toISOString().slice(0, 10));
  const [to, setTo] = useState(todayIso);
  const range = { from, to };
  const valid = from <= to;

  const summary = useQuery({ queryKey: ['reports', 'summary'], queryFn: reportsApi.summary });
  const monthly = useQuery({
    queryKey: ['reports', 'monthly', 12],
    queryFn: () => reportsApi.monthlyIssues({ months: 12 }),
  });
  const popular = useQuery({
    queryKey: ['reports', 'popular', range],
    queryFn: () => reportsApi.popularBooks({ ...range, limit: 20 }),
    enabled: valid,
  });
  const categories = useQuery({
    queryKey: ['reports', 'categories', range],
    queryFn: () => reportsApi.topCategories({ ...range, limit: 10 }),
    enabled: valid,
  });

  const exportCsv = (path: string, name: string, params?: Record<string, string | number>) =>
    downloadCsv(path, name, params).catch((e) => toast.error(getErrorMessage(e)));
  const ExportButton = ({
    path,
    name,
    params,
  }: {
    path: string;
    name: string;
    params?: Record<string, string | number>;
  }) => (
    <button type="button" className="btn-secondary btn-sm" onClick={() => exportCsv(path, name, params)}>
      <Download className="h-3.5 w-3.5" aria-hidden="true" /> CSV
    </button>
  );

  const s = summary.data;
  return (
    <>
      <PageHeader
        title="Reports"
        actions={
          <>
            <ExportButton path="/reports/summary" name="summary.csv" />
            <ExportButton path="/reports/monthly-issues" name="monthly-issues.csv" params={{ months: 12 }} />
            <ExportButton path="/reports/overdue" name="overdue.csv" />
          </>
        }
      />
      {s && (
        <dl className="card mb-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-5">
          {[
            ['Titles', s.total_titles],
            ['Copies', s.total_copies],
            ['On loan', s.issued],
            ['Overdue', s.overdue],
            ['Students', s.students],
            ['Suspended', s.suspended_students],
            ['Reservations', s.active_reservations],
            ['On shelf', s.available_copies],
            ['Fines collected', formatMoney(s.fines_collected)],
            ['Fines outstanding', formatMoney(s.fines_outstanding)],
          ].map(([label, value]) => (
            <div key={label}>
              <dt className="text-stone-500">{label}</dt>
              <dd className="text-lg font-semibold">{value}</dd>
            </div>
          ))}
        </dl>
      )}

      <MonthlyIssuesChart data={monthly.data} loading={monthly.isLoading} />

      <div className="my-6 flex flex-wrap items-end gap-3" role="group" aria-label="Date range for rankings">
        <label className="text-sm">
          <span className="label">From</span>
          <input
            type="date"
            className="input"
            value={from}
            max={to}
            onChange={(e) => setFrom(e.target.value)}
          />
        </label>
        <label className="text-sm">
          <span className="label">To</span>
          <input
            type="date"
            className="input"
            value={to}
            min={from}
            onChange={(e) => setTo(e.target.value)}
          />
        </label>
        {!valid && <p className="text-sm text-red-600">“From” must be before “To”.</p>}
        <ExportButton
          path="/reports/popular-books"
          name="popular-books.csv"
          params={{ ...range, limit: 100 }}
        />
        <ExportButton
          path="/reports/top-categories"
          name="top-categories.csv"
          params={{ ...range, limit: 50 }}
        />
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <RankingChart
          title="Most borrowed books"
          data={popular.data?.slice(0, 10)}
          labelKey="title"
          valueKey="issue_count"
          valueLabel="Times issued"
          loading={popular.isLoading}
        />
        <RankingChart
          title="Top categories"
          data={categories.data}
          labelKey="name"
          valueKey="issue_count"
          valueLabel="Times issued"
          loading={categories.isLoading}
        />
      </div>

      <h2 className="mb-3 mt-8 text-lg font-semibold">Most borrowed books — table</h2>
      <DataTable
        caption="Most borrowed books in the selected period"
        columns={[
          { key: 'rank', header: '#', render: (b) => (popular.data?.indexOf(b) ?? 0) + 1 },
          { key: 'title', header: 'Title', render: (b) => <span className="font-medium">{b.title}</span> },
          { key: 'authors', header: 'Authors', render: (b) => b.authors ?? '—' },
          { key: 'category', header: 'Category', render: (b) => b.category_name ?? '—' },
          { key: 'count', header: 'Times issued', render: (b) => b.issue_count },
        ]}
        rows={popular.data}
        rowKey={(b) => b.id}
        loading={popular.isLoading}
        error={popular.error}
        emptyTitle="No loans in this period"
      />
    </>
  );
}
