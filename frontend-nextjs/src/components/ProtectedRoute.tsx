'use client';

import { ReactNode, useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { useAuth } from '@/components/AuthContext';

interface ProtectedRouteProps {
  children: ReactNode;
  fallbackPath?: string;
}

export default function ProtectedRoute({ children, fallbackPath = '/login' }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [shouldRedirect, setShouldRedirect] = useState(false);

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      // Store the intended destination
      const returnUrl = pathname;
      router.push(`${fallbackPath}?returnUrl=${encodeURIComponent(returnUrl)}`);
      setShouldRedirect(true);
    }
  }, [isLoading, isAuthenticated, pathname, fallbackPath, router]);

  // Show loading while checking auth
  if (isLoading || shouldRedirect) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg">
        <div className="flex flex-col items-center gap-3">
          <svg className="w-10 h-10 text-primary-500 dark:text-dark-accent animate-spin" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <p className="text-gray-500 dark:text-gray-400">Loading...</p>
        </div>
      </div>
    );
  }

  // Render children only if authenticated
  if (!isAuthenticated) {
    return null;
  }

  return <>{children}</>;
}

