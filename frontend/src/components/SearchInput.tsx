import { useEffect, useRef, useState } from 'react';
import { Search } from 'lucide-react';
import { useDebounce } from '../hooks/useListState';

interface Props {
  label: string;
  placeholder?: string;
  value?: string;
  onSearch: (value: string) => void;
  delay?: number;
}

/** Text input that reports its value after the user pauses typing. */
export function SearchInput({ label, placeholder, value = '', onSearch, delay = 350 }: Props) {
  const [text, setText] = useState(value);
  const debounced = useDebounce(text, delay);
  const onSearchRef = useRef(onSearch);
  onSearchRef.current = onSearch;
  const first = useRef(true);

  useEffect(() => {
    if (first.current) {
      first.current = false;
      return;
    }
    onSearchRef.current(debounced.trim());
  }, [debounced]);

  return (
    <div className="relative w-full sm:max-w-xs">
      <label className="sr-only" htmlFor={`search-${label}`}>
        {label}
      </label>
      <Search
        className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-stone-400"
        aria-hidden="true"
      />
      <input
        id={`search-${label}`}
        type="search"
        className="input pl-9"
        placeholder={placeholder ?? label}
        value={text}
        onChange={(event) => setText(event.target.value)}
      />
    </div>
  );
}
