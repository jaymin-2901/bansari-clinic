'use client';

import { useRef, useState, useEffect, useCallback } from 'react';
import { useTranslation } from '@/i18n';
import type { DeclarationData } from '@/types';

interface Props {
  data: DeclarationData;
  setData: React.Dispatch<React.SetStateAction<DeclarationData>>;
  onSubmit: () => void;
  loading: boolean;
}

export default function DeclarationStep({ data, setData, onSubmit, loading }: Props) {
  const { tr } = useTranslation();
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [isDrawing, setIsDrawing] = useState(false);
  const [hasSignature, setHasSignature] = useState(false);

  const getCtx = () => canvasRef.current?.getContext('2d');

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    canvas.width = canvas.offsetWidth;
    canvas.height = 150;
    const ctx = canvas.getContext('2d');
    if (ctx) {
      ctx.strokeStyle = '#1b5e20';
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
    }
    // Restore existing signature
    if (data.signature) {
      const img = new Image();
      img.onload = () => {
        ctx?.drawImage(img, 0, 0);
        setHasSignature(true);
      };
      img.src = data.signature;
    }
  }, [data.signature]);

  const getPos = (e: React.MouseEvent | React.TouchEvent) => {
    const canvas = canvasRef.current;
    if (!canvas) return { x: 0, y: 0 };
    const rect = canvas.getBoundingClientRect();
    if ('touches' in e) {
      return {
        x: e.touches[0].clientX - rect.left,
        y: e.touches[0].clientY - rect.top,
      };
    }
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
  };

  const startDrawing = (e: React.MouseEvent | React.TouchEvent) => {
    e.preventDefault();
    const ctx = getCtx();
    if (!ctx) return;
    const { x, y } = getPos(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
    setIsDrawing(true);
  };

  const draw = (e: React.MouseEvent | React.TouchEvent) => {
    e.preventDefault();
    if (!isDrawing) return;
    const ctx = getCtx();
    if (!ctx) return;
    const { x, y } = getPos(e);
    ctx.lineTo(x, y);
    ctx.stroke();
  };

  const stopDrawing = useCallback(() => {
    if (!isDrawing) return;
    setIsDrawing(false);
    const canvas = canvasRef.current;
    if (canvas) {
      const signatureData = canvas.toDataURL('image/png');
      setData((prev) => ({ ...prev, signature: signatureData }));
      setHasSignature(true);
    }
  }, [isDrawing, setData]);

  const clearSignature = () => {
    const canvas = canvasRef.current;
    const ctx = getCtx();
    if (canvas && ctx) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      setData((prev) => ({ ...prev, signature: '' }));
      setHasSignature(false);
    }
  };

  const canSubmit = hasSignature && data.truthConfirmation;

  return (
    <div>
      <h2 className="text-xl font-bold text-gray-900 mb-1">{tr('online.declaration.title')}</h2>
      <p className="text-sm text-gray-500 mb-6">{tr('online.declaration.subtitle')}</p>

      {/* Digital Signature */}
      <div className="mb-6">
        <label className="label-text mb-2">{tr('online.declaration.signatureLabel')} *</label>
        <p className="text-xs text-gray-500 mb-3">{tr('online.declaration.signatureInstructions')}</p>
        <div className="relative border-2 border-gray-300 rounded-xl overflow-hidden bg-white">
          <canvas
            ref={canvasRef}
            className="w-full cursor-crosshair touch-none"
            style={{ height: '150px' }}
            onMouseDown={startDrawing}
            onMouseMove={draw}
            onMouseUp={stopDrawing}
            onMouseLeave={stopDrawing}
            onTouchStart={startDrawing}
            onTouchMove={draw}
            onTouchEnd={stopDrawing}
          />
          {!hasSignature && (
            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
              <span className="text-gray-300 text-lg">✍️</span>
            </div>
          )}
        </div>
        <button
          type="button"
          onClick={clearSignature}
          className="mt-2 text-sm text-red-500 hover:text-red-700 flex items-center gap-1"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
          {tr('online.declaration.clearSignature')}
        </button>
      </div>

      {/* Truth Confirmation */}
      <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
        <label className="flex items-start gap-3 cursor-pointer">
          <input
            type="checkbox"
            checked={data.truthConfirmation}
            onChange={(e) => setData((prev) => ({ ...prev, truthConfirmation: e.target.checked }))}
            className="mt-1 w-5 h-5 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
          />
          <span className="text-sm font-medium text-amber-800">
            {tr('online.declaration.truthConfirmation')}
          </span>
        </label>
      </div>

      {/* Submit Button */}
      <button
        type="button"
        disabled={!canSubmit || loading}
        onClick={onSubmit}
        className={`w-full py-4 rounded-xl font-semibold text-lg flex items-center justify-center gap-2 transition-all ${
          canSubmit && !loading
            ? 'bg-primary-500 hover:bg-primary-700 text-white shadow-lg hover:shadow-xl'
            : 'bg-gray-300 text-gray-500 cursor-not-allowed'
        }`}
      >
        {loading ? (
          <span className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full" />
        ) : (
          <>
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {tr('online.declaration.submitCase')}
          </>
        )}
      </button>
    </div>
  );
}
