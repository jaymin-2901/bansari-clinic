'use client';

import { useTranslation } from '@/i18n';
import type { PhysicalGeneralsData, Gender } from '@/types';

interface Props {
  data: PhysicalGeneralsData;
  setData: React.Dispatch<React.SetStateAction<PhysicalGeneralsData>>;
  gender: Gender;
}

export default function PhysicalGeneralsStep({ data, setData, gender }: Props) {
  const { tr } = useTranslation();

  const handleChange = (field: keyof PhysicalGeneralsData, value: string) => {
    setData((prev) => ({ ...prev, [field]: value }));
  };

  const thermalOptions = [
    { value: 'tolerates_well', labelKey: 'online.physicalGenerals.toleratesWell' },
    { value: 'feels_cold', labelKey: 'online.physicalGenerals.feelsCold' },
    { value: 'feels_hot', labelKey: 'online.physicalGenerals.feelsHot' },
    { value: 'normal', labelKey: 'online.physicalGenerals.normal' },
  ];

  const textFields: { field: keyof PhysicalGeneralsData; labelKey: string; placeholderKey: string }[] = [
    { field: 'appetite', labelKey: 'online.physicalGenerals.appetite', placeholderKey: 'online.physicalGenerals.appetitePlaceholder' },
    { field: 'thirst', labelKey: 'online.physicalGenerals.thirst', placeholderKey: 'online.physicalGenerals.thirstPlaceholder' },
    { field: 'foodCravings', labelKey: 'online.physicalGenerals.foodCravings', placeholderKey: 'online.physicalGenerals.foodCravingsPlaceholder' },
    { field: 'foodAversions', labelKey: 'online.physicalGenerals.foodAversions', placeholderKey: 'online.physicalGenerals.foodAversionsPlaceholder' },
    { field: 'stool', labelKey: 'online.physicalGenerals.stool', placeholderKey: 'online.physicalGenerals.stoolPlaceholder' },
    { field: 'urine', labelKey: 'online.physicalGenerals.urine', placeholderKey: 'online.physicalGenerals.urinePlaceholder' },
    { field: 'sweat', labelKey: 'online.physicalGenerals.sweat', placeholderKey: 'online.physicalGenerals.sweatPlaceholder' },
    { field: 'sleep', labelKey: 'online.physicalGenerals.sleep', placeholderKey: 'online.physicalGenerals.sleepPlaceholder' },
    { field: 'dreams', labelKey: 'online.physicalGenerals.dreams', placeholderKey: 'online.physicalGenerals.dreamsPlaceholder' },
  ];

  return (
    <div>
      <h2 className="text-xl font-bold text-gray-900 mb-1">{tr('online.physicalGenerals.title')}</h2>
      <p className="text-sm text-gray-500 mb-6">{tr('online.physicalGenerals.subtitle')}</p>

      {/* Text Fields Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        {textFields.map(({ field, labelKey, placeholderKey }) => (
          <div key={field}>
            <label className="label-text">{tr(labelKey)}</label>
            <input
              type="text"
              className="input-field"
              placeholder={tr(placeholderKey)}
              value={data[field] || ''}
              onChange={(e) => handleChange(field, e.target.value)}
            />
          </div>
        ))}
      </div>

      {/* Thermal Sensitivity Table */}
      <div className="bg-gray-50 rounded-xl p-5 border border-gray-100 mb-6">
        <h3 className="text-lg font-semibold text-gray-800 mb-4">
          🌡️ {tr('online.physicalGenerals.thermalSensitivity')}
        </h3>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200">
                <th className="text-left py-2 px-3 font-medium text-gray-600"></th>
                {thermalOptions.map((opt) => (
                  <th key={opt.value} className="text-center py-2 px-2 font-medium text-gray-600 text-xs">
                    {tr(opt.labelKey)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {(['thermalWinter', 'thermalSummer', 'thermalMonsoon'] as const).map((season) => {
                const seasonLabel =
                  season === 'thermalWinter' ? 'online.physicalGenerals.winter'
                  : season === 'thermalSummer' ? 'online.physicalGenerals.summer'
                  : 'online.physicalGenerals.monsoon';
                const emoji = season === 'thermalWinter' ? '❄️' : season === 'thermalSummer' ? '☀️' : '🌧️';
                return (
                  <tr key={season} className="border-b border-gray-100">
                    <td className="py-3 px-3 font-medium text-gray-700">
                      {emoji} {tr(seasonLabel)}
                    </td>
                    {thermalOptions.map((opt) => (
                      <td key={opt.value} className="text-center py-3 px-2">
                        <label className="cursor-pointer">
                          <input
                            type="radio"
                            name={season}
                            value={opt.value}
                            checked={data[season] === opt.value}
                            onChange={() => handleChange(season, opt.value)}
                            className="w-4 h-4 text-primary-500 focus:ring-primary-500"
                          />
                        </label>
                      </td>
                    ))}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Menstrual History - only for females */}
      {gender === 'FEMALE' && (
        <div>
          <label className="label-text">{tr('online.physicalGenerals.menstrualHistory')}</label>
          <textarea
            rows={2}
            className="input-field"
            placeholder={tr('online.physicalGenerals.menstrualPlaceholder')}
            value={data.menstrualHistory || ''}
            onChange={(e) => handleChange('menstrualHistory', e.target.value)}
          />
        </div>
      )}
    </div>
  );
}
