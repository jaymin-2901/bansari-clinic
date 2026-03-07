'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { fetchSettings, getImageUrl } from '@/lib/api';

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
  refreshSettings: () => Promise<void>;
}

const defaultSettings: ClinicSettings = {
  clinic_name: 'Bansari Homeopathy',
  clinic_tagline: 'Gentle Healing, Lasting Results',
  about_doctor_name: 'Dr. Bansari Patel',
};

const ClinicSettingsContext = createContext<ClinicSettingsContextType | undefined>(undefined);

export function ClinicSettingsProvider({ children }: { children: ReactNode }) {
  const [settings, setSettings] = useState<ClinicSettings>(defaultSettings);
  const [loading, setLoading] = useState(true);

  const refreshSettings = async () => {
    try {
      // Fetch general settings (includes clinic_name, clinic_logo, clinic_tagline)
      const generalSettings = await fetchSettings('general');
      // Fetch about settings (includes about_doctor_name)
      const aboutSettings = await fetchSettings('about');
      // Fetch contact settings (includes phone, email, address, whatsapp, hours)
      const contactSettings = await fetchSettings('contact');

      // Merge all settings
      const merged = {
        ...defaultSettings,
        ...generalSettings,
        ...aboutSettings,
        ...contactSettings,
      };

      setSettings(merged);
    } catch (error) {
      console.error('Failed to fetch clinic settings:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshSettings();
  }, []);

  // Compute derived values
  const clinicName = settings.clinic_name || 'Bansari Homeopathy';
  const clinicTagline = settings.clinic_tagline || 'Gentle Healing, Lasting Results';
  const doctorName = settings.about_doctor_name || 'Dr. Bansari Patel';

  // Get full logo URL with cache busting
  let clinicLogo: string | null = null;
  if (settings.clinic_logo) {
    // The API returns path like /uploads/general/filename.jpg
    const logoUrl = getImageUrl(settings.clinic_logo);
    if (logoUrl) {
      // Add cache busting query parameter
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

