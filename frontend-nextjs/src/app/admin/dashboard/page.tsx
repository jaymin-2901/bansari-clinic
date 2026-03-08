'use client';

import { useEffect, useState } from 'react';
import { useLanguage } from '@/lib/LanguageContext';

interface AdminInfo {
  id: number;
  name: string;
  email: string;
  role: string;
}

export default function AdminDashboardPage() {
  const [admin, setAdmin] = useState<AdminInfo | null>(null);
  const { t } = useLanguage();

  useEffect(() => {
    const stored = localStorage.getItem('admin');
    if (stored) {
      try {
        setAdmin(JSON.parse(stored));
      } catch {
        window.location.href = '/admin/login';
      }
    } else {
      window.location.href = '/admin/login';
    }
  }, []);

  const handleLogout = () => {
    localStorage.removeItem('admin');
    window.location.href = '/admin/login';
  };

  if (!admin) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg">
        <div className="text-gray-500 dark:text-gray-400">{t('Loading...', 'લોડ થઈ રહ્યું છે...')}</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-dark-bg py-8 px-4">
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="bg-white dark:bg-dark-card rounded-2xl shadow-lg dark:shadow-2xl dark:border dark:border-dark-border p-6 mb-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-200">
                {t('Admin Dashboard', 'એડમિન ડેશબોર્ડ')}
              </h1>
              <p className="text-gray-500 dark:text-gray-400 mt-1">
                {t('Welcome', 'સ્વાગત છે')}, {admin.name}
              </p>
            </div>
            <button
              onClick={handleLogout}
              className="px-4 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors text-sm font-medium"
            >
              {t('Logout', 'લૉગઆઉટ')}
            </button>
          </div>
        </div>

        {/* Quick Links */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <DashboardCard
            title={t('Full Admin Panel', 'સંપૂર્ણ એડમિન પેનલ')}
            description={t('Access the full clinic admin dashboard', 'સંપૂર્ણ ક્લિનિક એડમિન ડેશબોર્ડ ઍક્સેસ કરો')}
            icon="🏥"
            href="http://localhost:8001/dashboard.php"
            external
          />
          <DashboardCard
            title={t('Appointments', 'એપોઇન્ટમેન્ટ્સ')}
            description={t('View and manage appointments', 'એપોઇન્ટમેન્ટ્સ જુઓ અને સંચાલિત કરો')}
            icon="📅"
            href="http://localhost:8001/appointments.php"
            external
          />
          <DashboardCard
            title={t('Patients', 'દર્દીઓ')}
            description={t('Manage patient records', 'દર્દી રેકોર્ડ્સ સંચાલિત કરો')}
            icon="👥"
            href="http://localhost:8001/patients.php"
            external
          />
          <DashboardCard
            title={t('Schedule', 'શેડ્યુલ')}
            description={t('Manage clinic schedule', 'ક્લિનિક શેડ્યુલ સંચાલિત કરો')}
            icon="🕐"
            href="http://localhost:8001/schedule.php"
            external
          />
          <DashboardCard
            title={t('Testimonials', 'પ્રશંસાપત્ર')}
            description={t('Manage testimonials', 'પ્રશંસાપત્ર સંચાલિત કરો')}
            icon="⭐"
            href="http://localhost:8001/testimonials.php"
            external
          />
          <DashboardCard
            title={t('Settings', 'સેટિંગ્સ')}
            description={t('Clinic settings', 'ક્લિનિક સેટિંગ્સ')}
            icon="⚙️"
            href="http://localhost:8001/settings.php"
            external
          />
        </div>
      </div>
    </div>
  );
}

function DashboardCard({ title, description, icon, href, external }: {
  title: string;
  description: string;
  icon: string;
  href: string;
  external?: boolean;
}) {
  return (
    <a
      href={href}
      target={external ? '_blank' : undefined}
      rel={external ? 'noopener noreferrer' : undefined}
      className="block bg-white dark:bg-dark-card rounded-xl shadow dark:shadow-lg dark:border dark:border-dark-border p-5 hover:shadow-md dark:hover:shadow-xl transition-shadow group"
    >
      <div className="text-3xl mb-3">{icon}</div>
      <h3 className="font-semibold text-gray-900 dark:text-gray-200 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
        {title}
      </h3>
      <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{description}</p>
    </a>
  );
}
