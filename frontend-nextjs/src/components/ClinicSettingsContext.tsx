'use client';

import { createContext, useContext, useState, useEffect, useRef, ReactNode } from 'react';
import { fetchSettings, getImageUrl, fetchClinicStatus } from '@/lib/api';

interface ClinicStatus {
  closed: boolean;
  message: string;
  start?: string;
  end?: string;
  is_open: boolean;
}

interface ClinicSettings {
  clinic_name?: string;
  clinic_logo?: string;
  clinic_tagline?: string;
  about_doctor_name?: string;
  contact_phone?: string;
  contact_email?: string;
  contact_address?: string;
  contact_whatsapp?: string;
  contact_hours?: string;
  contact_map_url?: string;
  [key: string]: string | undefined;
}

interface ClinicSettingsContextType {
  settings: ClinicSettings;
  loading: boolean;
  clinicName: string;
  clinicLogo: string | null;
  clinicTagline: string;
  doctorName: string;
  clinicStatus: ClinicStatus;
  refreshSettings: () => Promise<void>;
}

const defaultSettings: ClinicSettings = {
  clinic_name: 'Bansari Homeopathy',
  clinic_tagline: 'Gentle Healing, Lasting Results',
  about_doctor_name: 'Dr. Bansari Patel',
};

const defaultStatus: ClinicStatus = {
  closed: false,
  message: '',
  is_open: true,
};

const ClinicSettingsContext = createContext<ClinicSettingsContextType | undefined>(undefined);

export function ClinicSettingsProvider({ children }: { children: ReactNode }) {
  const [settings, setSettings] = useState<ClinicSettings>(defaultSettings);
  const [clinicStatus, setClinicStatus] = useState<ClinicStatus>(defaultStatus);
  const [loading, setLoading] = useState(true);
  const intervalRef = useRef<NodeJS.Timeout>();

  const refreshSettings = async () => {
    try {
      const [general, about, contact, status] = await Promise.all([
        fetchSettings('general'),
        fetchSettings('about'),
        fetchSettings('contact'),
        fetchClinicStatus(),
      ]);

      const merged = {
        ...defaultSettings,
        ...general,
        ...about,
        ...contact,
      };

      setSettings(merged);
      setClinicStatus(status);
    } catch (error) {
      console.error('Failed to fetch clinic settings:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshSettings();
  }, []);

  // Polling for live updates every 15 seconds
  useEffect(() => {
    intervalRef.current = setInterval(() => {
      refreshSettings();
    }, 15000);

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, []);

  // Compute derived values
  const clinicName = settings.clinic_name || 'Bansari Homeopathy';
  const clinicTagline = settings.clinic_tagline || 'Gentle Healing, Lasting Results';
  const doctorName = settings.about_doctor_name || 'Dr. Bansari Patel';

  // Get full logo URL
  let clinicLogo: string | null = null;
  if (settings.clinic_logo) {
    const logoUrl = getImageUrl(settings.clinic_logo);
    if (logoUrl) {
      const timestamp = Date.now();
      clinicLogo = `${logoUrl}${logoUrl.includes('?') ? '&' : '?'}_t=${timestamp}`;
    }
  }

  return (
    <ClinicSettingsContext.Provider
      value={{
        settings,
        loading,
        clinicName,
        clinicLogo,
        clinicTagline,
        doctorName,
        clinicStatus,
        refreshSettings,
      }}
    >
      {children}
    </ClinicSettingsContext.Provider>
  );
}

export function useClinicSettings() {
  const context = useContext(ClinicSettingsContext);
  if (context === undefined) {
    throw new Error('useClinicSettings must be used within a ClinicSettingsProvider');
  }
  return context;
}
