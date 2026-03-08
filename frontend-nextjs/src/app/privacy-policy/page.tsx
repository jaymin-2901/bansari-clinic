'use client';

import { useState, useEffect } from 'react';
import { API_URL } from '@/lib/api';

export default function PrivacyPolicyPage() {
  const [content, setContent] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string>('');

  useEffect(() => {
    async function fetchPage() {
      try {
        const res = await fetch(
          `${API_URL}/legal_page.php?slug=privacy-policy`,
          { cache: 'no-store' }
        );
        if (res.ok) {
          const json = await res.json();
          if (json.success && json.data?.content) {
            setContent(json.data.content);
          }
        } else {
          setError('Failed to load privacy policy');
        }
      } catch (err) {
        setError('Error loading privacy policy');
        console.error('Privacy policy fetch error:', err);
      } finally {
        setLoading(false);
      }
    }
    fetchPage();
  }, []);

  return (
    <div className="min-h-screen bg-white dark:bg-dark-bg transition-colors duration-500">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
        {loading ? (
          <div className="flex justify-center py-20">
            <div className="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin" />
          </div>
        ) : content ? (
          <article
            className="prose prose-lg dark:prose-invert max-w-none
              prose-headings:font-heading prose-headings:text-gray-900 dark:prose-headings:text-white
              prose-h1:text-3xl prose-h1:sm:text-4xl prose-h1:mb-8
              prose-h2:text-xl prose-h2:mt-10 prose-h2:mb-4 prose-h2:text-primary-700 dark:prose-h2:text-primary-400
              prose-p:text-gray-600 dark:prose-p:text-gray-400 prose-p:leading-relaxed
              prose-li:text-gray-600 dark:prose-li:text-gray-400
              prose-strong:text-gray-800 dark:prose-strong:text-gray-200
              prose-a:text-primary-600 dark:prose-a:text-primary-400 prose-a:no-underline hover:prose-a:underline"
            dangerouslySetInnerHTML={{ __html: content }}
          />
        ) : (
          <div className="text-center py-20 text-gray-500">
            <p>Privacy policy content is being prepared.</p>
          </div>
        )}
      </div>
    </div>
  );
}
