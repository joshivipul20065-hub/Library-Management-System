import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { Plus } from 'lucide-react';
import { authorsApi, categoriesApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { useAuth } from '../../store/auth-context';
import { DataTable, type Column } from '../../components/DataTable';
import { ConfirmDialog, Modal } from '../../components/Modal';
import { SearchInput } from '../../components/SearchInput';
import { Field, PageHeader } from '../../components/ui';
import { applyServerErrors, optionalText } from '../../utils/validation';
import { formatDate, nullIfEmpty } from '../../utils/format';

/** Shared CRUD screen for categories and authors (same shape: name + optional description). */
type Kind = 'categories' | 'authors';
interface Item {
  id: number;
  name: string;
  description?: string | null;
  book_count?: number;
  created_at: string;
}

const CONFIG = {
  categories: { title: 'Categories', singular: 'category', maxName: 100, hasDescription: true },
  authors: { title: 'Authors', singular: 'author', maxName: 150, hasDescription: false },
} as const;

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  description: optionalText(500),
});
type FormValues = z.infer<typeof schema>;

export default function TaxonomyPage({ kind }: { kind: Kind }) {
  const config = CONFIG[kind];
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const list = useListState({ sort: 'name', order: 'asc' });
  const items = useQuery({
    queryKey: [kind, list.params],
    queryFn: (): Promise<{ data: Item[]; meta: import('../../types').PageMeta }> =>
      kind === 'categories' ? categoriesApi.list(list.params) : authorsApi.list(list.params),
    placeholderData: keepPreviousData,
  });
  const [editing, setEditing] = useState<Item | null | undefined>(undefined);
  const [deleting, setDeleting] = useState<Item | null>(null);
  const { register, handleSubmit, reset, setError, formState } = useForm<FormValues>({
    resolver: zodResolver(schema),
  });

  useEffect(() => {
    if (editing !== undefined) reset({ name: editing?.name ?? '', description: editing?.description ?? '' });
  }, [editing, reset]);

  const refresh = () => queryClient.invalidateQueries({ queryKey: [kind] });
  const remove = useMutation({
    mutationFn: (id: number) => (kind === 'categories' ? categoriesApi.remove(id) : authorsApi.remove(id)),
    onSuccess: () => {
      toast.success(`Deleted ${config.singular}`);
      setDeleting(null);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  const save = handleSubmit(async (values) => {
    try {
      if (kind === 'categories') {
        const input = { name: values.name, description: nullIfEmpty(values.description) };
        await (editing ? categoriesApi.update(editing.id, input) : categoriesApi.create(input));
      } else {
        await (editing
          ? authorsApi.update(editing.id, { name: values.name })
          : authorsApi.create({ name: values.name }));
      }
      toast.success(`Saved ${config.singular}`);
      setEditing(undefined);
      refresh();
    } catch (error) {
      if (!applyServerErrors(error, setError, ['name', 'description'])) toast.error(getErrorMessage(error));
    }
  });

  const columns: Column<Item>[] = [
    {
      key: 'name',
      header: 'Name',
      sortable: true,
      render: (i) => <span className="font-medium">{i.name}</span>,
    },
    ...(config.hasDescription
      ? [{ key: 'description', header: 'Description', render: (i: Item) => i.description ?? '—' }]
      : []),
    { key: 'book_count', header: 'Books', sortable: true, render: (i) => i.book_count ?? 0 },
    { key: 'created_at', header: 'Added', sortable: true, render: (i) => formatDate(i.created_at) },
    {
      key: 'actions',
      header: 'Actions',
      render: (i) => (
        <div className="flex gap-1">
          <button type="button" className="btn-secondary btn-sm" onClick={() => setEditing(i)}>
            Edit
          </button>
          {user?.role === 'admin' && (
            <button type="button" className="btn-ghost btn-sm text-red-600" onClick={() => setDeleting(i)}>
              Delete
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title={config.title}
        actions={
          <button type="button" className="btn-primary" onClick={() => setEditing(null)}>
            <Plus className="h-4 w-4" aria-hidden="true" /> Add {config.singular}
          </button>
        }
      />
      <div className="mb-4">
        <SearchInput key={kind} label={`Search ${kind}`} onSearch={(v) => list.setFilter('q', v)} />
      </div>
      <DataTable
        caption={config.title}
        columns={columns}
        rows={items.data?.data}
        rowKey={(i) => i.id}
        loading={items.isLoading}
        error={items.error}
        meta={items.data?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        order={list.order}
        onSort={list.toggleSort}
        emptyTitle={`No ${kind} yet`}
      />
      <Modal
        open={editing !== undefined}
        title={editing ? `Edit ${config.singular}` : `Add ${config.singular}`}
        onClose={() => setEditing(undefined)}
        footer={
          <>
            <button type="button" className="btn-secondary" onClick={() => setEditing(undefined)}>
              Cancel
            </button>
            <button
              type="submit"
              form="taxonomy-form"
              className="btn-primary"
              disabled={formState.isSubmitting}
            >
              Save
            </button>
          </>
        }
      >
        <form id="taxonomy-form" onSubmit={save} noValidate className="space-y-4">
          <Field label="Name" error={formState.errors.name?.message}>
            <input className="input" maxLength={config.maxName} {...register('name')} />
          </Field>
          {config.hasDescription && (
            <Field label="Description" error={formState.errors.description?.message}>
              <textarea className="input" rows={3} {...register('description')} />
            </Field>
          )}
        </form>
      </Modal>
      <ConfirmDialog
        open={!!deleting}
        title={`Delete ${config.singular}`}
        danger
        busy={remove.isPending}
        message={`Delete “${deleting?.name}”? This is only possible when no books use it.`}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}
