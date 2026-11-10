import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { AlertOctagon, BookCopy, BookOpen, IndianRupee, ScanLine, Users } from 'lucide-react';
import { reportsApi } from '../../api/endpoints';
import { KpiCard, PageHeader } from '../../components/ui';
import { MonthlyIssuesChart, RankingChart } from '../../components/Charts';
import { formatMoney } from '../../utils/format';

export default function AdminDashboard() {
  const summary = useQuery({ queryKey: ['reports', 'summary'], queryFn: reportsApi.summary });
  const monthly = useQuery({
    queryKey: ['reports', 'monthly', 12],
    queryFn: () => reportsApi.monthlyIssues({ months: 12 }),
  });
  const popular = useQuery({
    queryKey: ['reports', 'popular', 'dash'],
    queryFn: () => reportsApi.popularBooks({ limit: 8 }),
  });
  const categories = useQuery({
    queryKey: ['reports', 'categories', 'dash'],
    queryFn: () => reportsApi.topCategories({ limit: 8 }),
  });
  const s = summary.data;

  return (
    <>
      <PageHeader
        title="Library overview"
        actions={
          <Link to="/admin/circulation" className="btn-primary">
            <ScanLine className="h-4 w-4" aria-hidden="true" /> Issue / Return
          </Link>
        }
      />
      <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5 [&>*:last-child]:col-span-2 lg:[&>*:last-child]:col-span-1">
        <KpiCard
          label="Titles"
          value={s ? `${s.total_titles}` : ''}
          icon={<BookOpen className="h-5 w-5" />}
          loading={!s}
        />
        <KpiCard
          label="Copies on loan"
          value={s ? `${s.issued} / ${s.total_copies}` : ''}
          icon={<BookCopy className="h-5 w-5" />}
          tone="violet"
          loading={!s}
        />
        <KpiCard
          label="Overdue"
          value={s?.overdue}
          icon={<AlertOctagon className="h-5 w-5" />}
          tone="red"
          loading={!s}
        />
        <KpiCard
          label="Students"
          value={s?.students}
          icon={<Users className="h-5 w-5" />}
          tone="emerald"
          loading={!s}
        />
        <KpiCard
          label="Fines collected"
          value={formatMoney(s?.fines_collected)}
          icon={<IndianRupee className="h-5 w-5" />}
          tone="amber"
          loading={!s}
        />
      </div>
      {s && (s.fines_outstanding > 0 || s.active_reservations > 0) && (
        <p className="mt-3 text-sm text-stone-500">
          {formatMoney(s.fines_outstanding)} in unpaid fines · {s.active_reservations} active reservation(s) ·{' '}
          {s.available_copies} copies on the shelves
        </p>
      )}
      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <div className="xl:col-span-2">
          <MonthlyIssuesChart data={monthly.data} loading={monthly.isLoading} />
        </div>
        <RankingChart
          title="Most borrowed books (last 12 months)"
          data={popular.data}
          labelKey="title"
          valueKey="issue_count"
          valueLabel="Times issued"
          loading={popular.isLoading}
        />
        <RankingChart
          title="Top categories (last 12 months)"
          data={categories.data}
          labelKey="name"
          valueKey="issue_count"
          valueLabel="Times issued"
          loading={categories.isLoading}
        />
      </div>
    </>
  );
}
