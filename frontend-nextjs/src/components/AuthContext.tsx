'use client';

import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import { useRouter, usePathname } from 'next/navigation';

interface UserInfo {
  id: number;
  name: string;
  email?: string;
  mobile?: string;
  role?: string;
}

interface AuthContextType {
  user: UserInfo | null;
  accessToken: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (userData: UserInfo, accessToken: string, refreshToken: string) => void;
  logout: () => void;
  updateUser: (userData: UserInfo) => void;
  checkAuth: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Helper to parse JWT payload
function parseJwt(token: string): any {
  try {
    const base64Url = token.split('.')[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
    return JSON.parse(jsonPayload);
  } catch (e) {
    return null;
  }
}

// Routes that require authentication
const PROTECTED_ROUTES = [
  '/book-appointment',
  '/my-appointments',
  '/profile',
];

// Routes that should redirect to dashboard if already authenticated
const AUTH_ROUTES = [
  '/login',
  '/signup',
];

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<UserInfo | null>(null);
  const [accessToken, setAccessToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();
  const pathname = usePathname();

  // Check authentication on mount and pathname change
  useEffect(() => {
    checkAuth();
  }, [pathname]);

  const refreshSession = useCallback(async (token: string) => {
    try {
      const { refreshToken: refreshTokenApi } = await import('@/lib/api');
      const data = await refreshTokenApi(token);
      if (data.success) {
        localStorage.setItem('access_token', data.access_token);
        localStorage.setItem('refresh_token', data.refresh_token);
        setAccessToken(data.access_token);
        return data.access_token;
      } else {
        logout();
        return null;
      }
    } catch (err) {
      console.error('Token refresh failed:', err);
      return null;
    }
  }, []);

  const checkAuth = useCallback(async () => {
    try {
      const storedUser = localStorage.getItem('patient_user');
      const storedToken = localStorage.getItem('access_token');
      const storedRefresh = localStorage.getItem('refresh_token');
      
      if (storedUser && storedToken) {
        const parsedUser = JSON.parse(storedUser);
        const payload = parseJwt(storedToken);
        
        // If token is expired or about to expire (within 2 minutes), try to refresh
        if (payload && payload.exp * 1000 < Date.now() + 120000) {
          if (storedRefresh) {
            const newToken = await refreshSession(storedRefresh);
            if (newToken) {
              setUser(parsedUser);
              setAccessToken(newToken);
            }
          } else {
            logout();
          }
        } else {
          setUser(parsedUser);
          setAccessToken(storedToken);
        }
        
        // If authenticated user tries to access auth routes, redirect to book-appointment
        if (AUTH_ROUTES.some(route => pathname === route)) {
          router.push('/book-appointment');
          return;
        }
      } else {
        setUser(null);
        setAccessToken(null);
        
        // If unauthenticated user tries to access protected routes, redirect to login
        if (PROTECTED_ROUTES.some(route => pathname.startsWith(route))) {
          const returnUrl = pathname;
          router.push(`/login?returnUrl=${encodeURIComponent(returnUrl)}`);
        }
      }
    } catch (error) {
      console.error('Auth check error:', error);
      setUser(null);
      setAccessToken(null);
    } finally {
      setIsLoading(false);
    }
  }, [pathname, router, refreshSession]);

  // Periodic background refresh check
  useEffect(() => {
    if (!accessToken) return;

    const interval = setInterval(() => {
      const storedRefresh = localStorage.getItem('refresh_token');
      if (!storedRefresh) return;

      const payload = parseJwt(accessToken);
      if (payload && payload.exp * 1000 < Date.now() + 300000) { // Refresh if less than 5 mins left
        refreshSession(storedRefresh);
      }
    }, 60000); // Check every minute

    return () => clearInterval(interval);
  }, [accessToken, refreshSession]);

  const login = (userData: UserInfo, access: string, refresh: string) => {
    localStorage.setItem('patient_user', JSON.stringify(userData));
    localStorage.setItem('access_token', access);
    localStorage.setItem('refresh_token', refresh);
    setUser(userData);
    setAccessToken(access);
  };

  const logout = useCallback(() => {
    localStorage.removeItem('patient_user');
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    setUser(null);
    setAccessToken(null);
    router.push('/login');
  }, [router]);

  const updateUser = (userData: UserInfo) => {
    localStorage.setItem('patient_user', JSON.stringify(userData));
    setUser(userData);
  };

  return (
    <AuthContext.Provider 
      value={{ 
        user,
        accessToken,
        isLoading, 
        isAuthenticated: !!user, 
        login, 
        logout,
        updateUser,
        checkAuth
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

