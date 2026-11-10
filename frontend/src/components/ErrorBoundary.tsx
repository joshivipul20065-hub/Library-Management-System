import { Component, type ErrorInfo, type ReactNode } from 'react';
import { AlertTriangle } from 'lucide-react';

interface State {
  error: Error | null;
}

/** Catches render errors so one broken page does not blank the whole app. */
export class ErrorBoundary extends Component<{ children: ReactNode }, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('Unhandled UI error', error, info.componentStack);
  }

  render() {
    if (!this.state.error) {
      return this.props.children;
    }
    return (
      <div
        role="alert"
        className="flex min-h-[50vh] flex-col items-center justify-center gap-4 p-6 text-center"
      >
        <AlertTriangle className="h-10 w-10 text-red-500" aria-hidden="true" />
        <h1 className="text-xl font-semibold">Something went wrong</h1>
        <p className="max-w-md text-sm text-stone-500">
          An unexpected error occurred while showing this page. Reloading usually fixes it.
        </p>
        <div className="flex gap-2">
          <button type="button" className="btn-secondary" onClick={() => this.setState({ error: null })}>
            Try again
          </button>
          <button type="button" className="btn-primary" onClick={() => window.location.reload()}>
            Reload page
          </button>
        </div>
      </div>
    );
  }
}
