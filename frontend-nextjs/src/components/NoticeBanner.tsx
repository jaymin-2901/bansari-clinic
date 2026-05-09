'use client';

import React from 'react';
import { useClinicSettings } from './ClinicSettingsContext';
import { Info } from 'lucide-react';

const NoticeBanner: React.FC = () => {
  const { clinicStatus } = useClinicSettings();

  if (!clinicStatus.message) {
    return null;
  }

  const formatDateTime = (dt?: string) => {
    if (!dt) return '';
    try {
      const date = new Date(dt);
      return date.toLocaleString('en-IN', {
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    } catch {
      return dt;
    }
  };

  const timeRange = clinicStatus.start && clinicStatus.end 
    ? `(${formatDateTime(clinicStatus.start)} to ${formatDateTime(clinicStatus.end)})`
    : '';

  const prefix = clinicStatus.closed ? 'CLINIC CLOSED: ' : 'NOTICE: ';
  const fullMessage = `${prefix}${clinicStatus.message} ${timeRange}`;

  return (
    <div className={`py-2 overflow-hidden relative z-50 shadow-md ${clinicStatus.closed ? 'bg-red-600 dark:bg-red-700' : 'bg-amber-500 dark:bg-amber-600'} text-white`}>
      <div className="flex items-center whitespace-nowrap animate-marquee">
        <div className="flex items-center px-4">
          <Info className="w-4 h-4 mr-2 flex-shrink-0" />
          <span className="font-medium">{fullMessage}</span>
        </div>
        {/* Duplicate for seamless loop */}
        <div className="flex items-center px-4">
          <Info className="w-4 h-4 mr-2 flex-shrink-0" />
          <span className="font-medium">{fullMessage}</span>
        </div>
        <div className="flex items-center px-4">
          <Info className="w-4 h-4 mr-2 flex-shrink-0" />
          <span className="font-medium">{fullMessage}</span>
        </div>
        <div className="flex items-center px-4">
          <Info className="w-4 h-4 mr-2 flex-shrink-0" />
          <span className="font-medium">{fullMessage}</span>
        </div>
      </div>

      <style jsx>{`
        .animate-marquee {
          display: flex;
          width: fit-content;
          animation: marquee 30s linear infinite;
        }
        @keyframes marquee {
          0% { transform: translateX(0); }
          100% { transform: translateX(-25%); }
        }
        .animate-marquee:hover {
          animation-play-state: paused;
        }
      `}</style>
    </div>
  );
};

export default NoticeBanner;
