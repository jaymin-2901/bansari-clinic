'use client';

import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import { useRouter, usePathname } from 'next/navigation';

interface PatientInfo {
  id: number;
  name: string;
  mobile: string;
  email?: string;
  age?: number | null;
  gender?: string | null;
  city?: string | null;
}

interface AuthContextType {
  patient: PatientInfo | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (patientData: PatientInfo) => void;
  logout: () => void;
  checkAuth: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

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
  const [patient, setPatient] = useState<PatientInfo | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();
  const pathname = usePathname();

  // Check authentication on mount and pathname change
  useEffect(() => {
    checkAuth();
  }, [pathname]);

  const checkAuth = useCallback(() => {
    try {
      const stored = localStorage.getItem('patient');
      if (stored) {
        const patientData = JSON.parse(stored);
        setPatient(patientData);
        
        // If authenticated user tries to access auth routes, redirect to book-appointment
        if (AUTH_ROUTES.some(route => pathname === route)) {
          router.push('/book-appointment');
          return;
        }
      } else {
        setPatient(null);
        
        // If unauthenticated user tries to access protected routes, redirect to login
        if (PROTECTED_ROUTES.some(route => pathname.startsWith(route))) {
          // Store the intended destination
          const returnUrl = pathname;
          router.push(`/login?returnUrl=${encodeURIComponent(returnUrl)}`);
        }
      }
    } catch (error) {
      console.error('Auth check error:', error);
      setPatient(null);
    } finally {
      setIsLoading(false);
    }
  }, [pathname, router]);

  const login = (patientData: PatientInfo) => {
    localStorage.setItem('patient', JSON.stringify(patientData));
    setPatient(patientData);
  };

  const logout = () => {
    localStorage.removeItem('patient');
    setPatient(null);
  };

  return (
    <AuthContext.Provider 
      value={{ 
        patient, 
        isLoading, 
        isAuthenticated: !!patient, 
        login, 
        logout,
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

