// Decorative shelf of book spines drawn with CSS (no images, no data). Deterministic so it never shifts.
const SPINES = [
  { h: 86, w: 26, c: '#c9a227' },
  { h: 94, w: 34, c: '#2f6f62' },
  { h: 78, w: 22, c: '#8a3b2e' },
  { h: 98, w: 30, c: '#e8dcc0' },
  { h: 88, w: 24, c: '#3f5a7a' },
  { h: 72, w: 36, c: '#d58a1c' },
  { h: 92, w: 20, c: '#1a403c' },
  { h: 84, w: 28, c: '#a8b89a' },
  { h: 96, w: 32, c: '#6b2f3a' },
  { h: 80, w: 24, c: '#e5a92f' },
  { h: 90, w: 30, c: '#4fae9c' },
  { h: 76, w: 22, c: '#efe6d2' },
  { h: 94, w: 26, c: '#9b4d15' },
];

export function BookSpines({ className = '' }: { className?: string }) {
  return (
    <div
      className={`flex items-end gap-1.5 border-b-[6px] border-black/30 pb-0 ${className}`}
      aria-hidden="true"
    >
      {SPINES.map((s, i) => (
        <div
          key={i}
          className="relative shrink-0 rounded-t-[3px] shadow-[inset_-3px_0_0_rgba(0,0,0,0.18)]"
          style={{
            height: `${s.h}%`,
            width: s.w,
            backgroundColor: s.c,
            transform: i === 5 ? 'rotate(-6deg) translateX(-4px)' : undefined,
            transformOrigin: 'bottom left',
          }}
        >
          <span className="absolute inset-x-1 top-[14%] h-px bg-black/20" />
          <span className="absolute inset-x-1 top-[18%] h-px bg-black/20" />
          <span className="absolute inset-x-1 bottom-[12%] h-px bg-black/20" />
        </div>
      ))}
    </div>
  );
}
