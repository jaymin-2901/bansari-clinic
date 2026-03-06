'use client';

import { useTranslation } from '@/i18n';
import type { ComplaintEntry } from '@/types';

interface Props {
  complaints: ComplaintEntry[];
  setComplaints: React.Dispatch<React.SetStateAction<ComplaintEntry[]>>;
}

const emptyComplaint: ComplaintEntry = {
  affectedArea: '', symptoms: '', sinceWhen: '', frequency: '',
  worseBy: '', betterBy: '', associatedSymptoms: '',
};

export default function MainComplaintsStep({ complaints, setComplaints }: Props) {
  const { tr } = useTranslation();

  const updateComplaint = (index: number, field: keyof ComplaintEntry, value: string) => {
    setComplaints((prev) =>
      prev.map((c, i) => (i === index ? { ...c, [field]: value } : c))
    );
  };

  const addComplaint = () => {
    setComplaints((prev) => [...prev, { ...emptyComplaint }]);
  };

  const removeComplaint = (index: number) => {
    if (complaints.length === 1) return;
    setComplaints((prev) => prev.filter((_, i) => i !== index));
  };

  return (
    <div>
      <h2 className="text-xl font-bold text-gray-900 mb-1">{tr('online.complaints.title')}</h2>
      <p className="text-sm text-gray-500 mb-6">{tr('online.complaints.subtitle')}</p>

      <div className="space-y-6">
        {complaints.map((complaint, index) => (
          <div key={index} className="relative bg-gray-50 rounded-xl p-5 border border-gray-100">
            {/* Header */}
            <div className="flex items-center justify-between mb-4">
              <span className="text-sm font-semibold text-primary-700 bg-primary-100 px-3 py-1 rounded-full">
                #{index + 1}
              </span>
              {complaints.length > 1 && (
                <button
                  onClick={() => removeComplaint(index)}
                  className="text-red-500 hover:text-red-700 text-sm font-medium flex items-center gap-1"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  {tr('online.complaints.removeComplaint')}
                </button>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Affected Area */}
              <div>
                <label className="label-text">{tr('online.complaints.affectedArea')} *</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder={tr('online.complaints.affectedAreaPlaceholder')}
                  value={complaint.affectedArea}
                  onChange={(e) => updateComplaint(index, 'affectedArea', e.target.value)}
                />
              </div>

              {/* Since When */}
              <div>
                <label className="label-text">{tr('online.complaints.sinceWhen')} *</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder={tr('online.complaints.sinceWhenPlaceholder')}
                  value={complaint.sinceWhen}
                  onChange={(e) => updateComplaint(index, 'sinceWhen', e.target.value)}
                />
              </div>

              {/* Symptoms - Full width */}
              <div className="md:col-span-2">
                <label className="label-text">{tr('online.complaints.symptoms')} *</label>
                <textarea
                  rows={2}
                  className="input-field"
                  placeholder={tr('online.complaints.symptomsPlaceholder')}
                  value={complaint.symptoms}
                  onChange={(e) => updateComplaint(index, 'symptoms', e.target.value)}
                />
              </div>

              {/* Frequency */}
              <div>
                <label className="label-text">{tr('online.complaints.frequency')}</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder={tr('online.complaints.frequencyPlaceholder')}
                  value={complaint.frequency}
                  onChange={(e) => updateComplaint(index, 'frequency', e.target.value)}
                />
              </div>

              {/* Worse By */}
              <div>
                <label className="label-text">{tr('online.complaints.worseBy')}</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder={tr('online.complaints.worseByPlaceholder')}
                  value={complaint.worseBy}
                  onChange={(e) => updateComplaint(index, 'worseBy', e.target.value)}
                />
              </div>

              {/* Better By */}
              <div>
                <label className="label-text">{tr('online.complaints.betterBy')}</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder={tr('online.complaints.betterByPlaceholder')}
                  value={complaint.betterBy}
                  onChange={(e) => updateComplaint(index, 'betterBy', e.target.value)}
                />
              </div>

              {/* Associated Symptoms */}
              <div>
                <label className="label-text">{tr('online.complaints.associatedSymptoms')}</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder={tr('online.complaints.associatedSymptomsPlaceholder')}
                  value={complaint.associatedSymptoms}
                  onChange={(e) => updateComplaint(index, 'associatedSymptoms', e.target.value)}
                />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Add Complaint Button */}
      <button
        onClick={addComplaint}
        className="mt-4 w-full flex items-center justify-center gap-2 py-3 border-2 border-dashed border-primary-300 rounded-xl text-primary-600 hover:bg-primary-50 transition-all font-medium"
      >
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </svg>
        {tr('online.complaints.addComplaint')}
      </button>
    </div>
  );
}
