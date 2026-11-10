import { useId } from 'react';
import { ImagePlus, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { BookCover } from '../../components/ui';

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_BYTES = 2 * 1024 * 1024;

interface Props {
  url: string | null;
  title: string;
  note?: string;
  onPick: (file: File) => void;
  onRemove: () => void;
}

/** Cover preview with upload / change / remove controls. Changes are applied when the form is saved. */
export function CoverPicker({ url, title, note, onPick, onRemove }: Props) {
  const inputId = useId();

  const pick = (file: File | undefined) => {
    if (!file) return;
    if (!ALLOWED.includes(file.type)) {
      toast.error('Use a JPEG, PNG or WebP image');
      return;
    }
    if (file.size > MAX_BYTES) {
      toast.error('Image must be 2 MB or smaller');
      return;
    }
    onPick(file);
  };

  return (
    <div className="flex items-center gap-4">
      <BookCover url={url} title={title || 'this book'} className="h-36 w-24" />
      <div className="space-y-2">
        <p className="label mb-0">Cover image</p>
        <div className="flex flex-wrap gap-2">
          {/* The visually hidden input stays keyboard-focusable; the label acts as its button. */}
          <input
            id={inputId}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="peer sr-only"
            onChange={(e) => {
              pick(e.target.files?.[0]);
              e.target.value = '';
            }}
          />
          <label
            htmlFor={inputId}
            className="btn-secondary btn-sm cursor-pointer peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500"
          >
            <ImagePlus className="h-4 w-4" aria-hidden="true" /> {url ? 'Change image' : 'Upload image'}
          </label>
          {url && (
            <button type="button" className="btn-ghost btn-sm text-red-600" onClick={onRemove}>
              <Trash2 className="h-4 w-4" aria-hidden="true" /> Remove cover
            </button>
          )}
        </div>
        <p className="text-xs text-stone-500">{note ?? 'JPEG, PNG or WebP, up to 2 MB.'}</p>
      </div>
    </div>
  );
}
