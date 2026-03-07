# MediConnect - Frontend API Integration Guide

## Next.js Server-Side Routes for Enhanced Features

### 📍 Base Configuration

Add to `frontend/next.config.js`:

```javascript
const nextConfig = {
    // ... existing config
    async headers() {
        return [
            {
                source: '/api/:path*',
                headers: [
                    { key: 'Access-Control-Allow-Origin', value: '*' },
                    { key: 'Content-Type', value: 'application/json' },
                ]
            }
        ]
    },
    async rewrites() {
        const backendUrl = process.env.NEXT_PUBLIC_BACKEND_URL || 'http://localhost:8000';
        return [
            // Security APIs
            {
                source: '/api/auth/:path*',
                destination: `${backendUrl}/backend/api/auth/:path*`,
            },
            // Clinic Admin APIs
            {
                source: '/api/admin/:path*',
                destination: `${backendUrl}/clinic-admin/api/:path*`,
            },
            // Legal pages
            {
                source: '/api/legal/:path*',
                destination: `${backendUrl}/clinic-admin/api/legal_pages.php?action=view&slug=:path*`,
            },
        ]
    }
}

module.exports = nextConfig;
```

---

## 🛡️ SECURITY APIs

### Legal Pages API

**Endpoint:** `/api/legal/{slug}`

**Frontend Usage:**

```typescript
// frontend/src/lib/legal.ts
export async function getLegalPage(slug: string) {
    try {
        const response = await fetch(`/api/legal/${slug}`);
        if (!response.ok) throw new Error('Page not found');
        return await response.json();
    } catch (error) {
        console.error('Failed to fetch legal page:', error);
        return null;
    }
}

export async function getAllLegalPages() {
    try {
        const response = await fetch('/api/admin/legal_pages.php?action=list', {
            headers: { 'X-Admin-Token': process.env.NEXT_ADMIN_TOKEN }
        });
        return await response.json();
    } catch (error) {
        console.error('Failed to fetch pages:', error);
        return [];
    }
}
```

**Usage in Component:**

```typescript
// frontend/src/pages/legal/[slug].tsx
import { getLegalPage } from '@/lib/legal';

export async function getServerSideProps(context: any) {
    const { slug } = context.params;
    const data = await getLegalPage(slug);
    
    return {
        props: { page: data },
        revalidate: 3600 // ISR: revalidate every hour
    };
}

export default function LegalPage({ page }: any) {
    if (!page) return <div>Page not found</div>;
    
    return (
        <div className="legal-page">
            <h1>{page.data.title}</h1>
            <div dangerouslySetInnerHTML={{ __html: page.data.content }} />
            <p className="text-sm text-gray-500">
                Last updated: {new Date(page.data.updated_at).toLocaleDateString()}
            </p>
        </div>
    );
}
```

---

## 📊 APPOINTMENTS & NOTIFICATIONS

### Search Appointments API

**Backend Endpoint:** `/clinic-admin/api/search_appointments.php`

**Frontend Implementation:**

```typescript
// frontend/src/lib/appointments.ts
export interface SearchFilters {
    search?: string;
    date_from?: string;
    date_to?: string;
    status?: 'pending' | 'confirmed' | 'completed' | 'cancelled';
    type?: 'online' | 'offline';
    confirmation?: string;
    page?: number;
    limit?: number;
}

export async function searchAppointments(filters: SearchFilters) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
        if (value) params.append(key, String(value));
    });
    
    const response = await fetch(
        `/api/admin/search_appointments.php?${params}`,
        {
            headers: {
                'Cookie': `PHPSESSID=${getCookie('PHPSESSID')}`,
            }
        }
    );
    
    if (!response.ok) throw new Error('Search failed');
    return await response.json();
}

export async function exportAppointments(filters: SearchFilters) {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
        if (value) params.append(key, String(value));
    });
    
    window.location.href = `/api/admin/export_appointments.php?${params}`;
}
```

**React Component Example:**

```typescript
// frontend/src/components/AppointmentSearch.tsx
import { useState } from 'react';
import { searchAppointments, exportAppointments } from '@/lib/appointments';

export default function AppointmentSearch() {
    const [filters, setFilters] = useState<SearchFilters>({
        page: 1,
        limit: 20
    });
    const [results, setResults] = useState<any>(null);
    const [loading, setLoading] = useState(false);

    const handleSearch = async () => {
        setLoading(true);
        try {
            const data = await searchAppointments(filters);
            setResults(data);
        } catch (error) {
            console.error('Search failed:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleExport = async () => {
        await exportAppointments(filters);
    };

    return (
        <div className="search-container">
            {/* Filter inputs */}
            <input
                type="text"
                placeholder="Search by patient name or mobile"
                onChange={(e) => setFilters({ ...filters, search: e.target.value })}
            />
            
            <input
                type="date"
                onChange={(e) => setFilters({ ...filters, date_from: e.target.value })}
            />
            
            <select onChange={(e) => setFilters({ ...filters, status: e.target.value as any })}>
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
            </select>

            <button onClick={handleSearch} disabled={loading}>
                {loading ? 'Searching...' : 'Search'}
            </button>

            <button onClick={handleExport}>Export to Excel</button>

            {/* Results table */}
            {results && (
                <>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {results.data?.map((apt: any) => (
                                <tr key={apt.id}>
                                    <td>{apt.full_name}</td>
                                    <td>{apt.appointment_date}</td>
                                    <td>{apt.consultation_type}</td>
                                    <td>{apt.status}</td>
                                    <td>
                                        <button>View</button>
                                        <button>Edit</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    <div className="pagination">
                        {results.pagination && (
                            <>
                                <span>Page {results.pagination.page} of {results.pagination.pages}</span>
                                <button 
                                    onClick={() => handleSearch()}
                                    disabled={results.pagination.page === 1}
                                >
                                    Previous
                                </button>
                                <button 
                                    onClick={() => setFilters({ ...filters, page: filters.page! + 1 })}
                                    disabled={results.pagination.page === results.pagination.pages}
                                >
                                    Next
                                </button>
                            </>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
```

---

## 🔔 NOTIFICATIONS & BADGES

### Get Badge Counts API

**Backend Endpoint:** `/clinic-admin/api/notifications.php?action=get-counts`

**Frontend Implementation:**

```typescript
// frontend/src/lib/notifications.ts
export interface BadgeCounts {
    pending_appointments: number;
    unread_messages: number;
    pending_reminders: number;
    whatsapp_failures: number;
    awaiting_confirmation: number;
    no_followup: number;
    new_patients: number;
    unprocessed: number;
}

export async function getBadgeCounts(): Promise<BadgeCounts | null> {
    try {
        const response = await fetch('/api/admin/notifications.php?action=get-counts');
        const data = await response.json();
        return data.success ? data.counts : null;
    } catch (error) {
        console.error('Failed to get badges:', error);
        return null;
    }
}

export async function getDetailedAlerts(type = 'all') {
    try {
        const response = await fetch(`/api/admin/notifications.php?action=get-alerts&type=${type}`);
        const data = await response.json();
        return data.success ? data.alerts : [];
    } catch (error) {
        console.error('Failed to get alerts:', error);
        return [];
    }
}

export async function markNotificationRead(id: number) {
    try {
        const response = await fetch('/api/admin/notifications.php?action=mark-read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        return await response.json();
    } catch (error) {
        console.error('Failed to mark notification:', error);
        return { success: false };
    }
}
```

**Dashboard Badge Component:**

```typescript
// frontend/src/components/DashboardBadges.tsx
import { useEffect, useState } from 'react';
import { getBadgeCounts, BadgeCounts } from '@/lib/notifications';
import Link from 'next/link';

export default function DashboardBadges() {
    const [counts, setCounts] = useState<BadgeCounts | null>(null);

    useEffect(() => {
        const loadBadges = async () => {
            const data = await getBadgeCounts();
            setCounts(data);
        };

        loadBadges();

        // Update every 30 seconds
        const interval = setInterval(loadBadges, 30000);
        return () => clearInterval(interval);
    }, []);

    if (!counts) return <div>Loading badges...</div>;

    const badges = [
        {
            label: 'Pending',
            count: counts.pending_appointments,
            href: '/admin/appointments?status=pending',
            color: 'bg-yellow-100 text-yellow-800'
        },
        {
            label: 'Messages',
            count: counts.unread_messages,
            href: '/admin/messages',
            color: 'bg-blue-100 text-blue-800'
        },
        {
            label: 'Reminders',
            count: counts.pending_reminders,
            href: '/admin/appointments?reminder=pending',
            color: 'bg-purple-100 text-purple-800'
        },
        {
            label: 'Failures',
            count: counts.whatsapp_failures,
            href: '/admin/appointments?failures=true',
            color: 'bg-red-100 text-red-800'
        },
    ];

    return (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {badges.map((badge) => (
                <Link href={badge.href} key={badge.label}>
                    <a className={`p-4 rounded-lg ${badge.color} text-center`}>
                        <div className="text-2xl font-bold">{badge.count}</div>
                        <div className="text-sm">{badge.label}</div>
                    </a>
                </Link>
            ))}
        </div>
    );
}
```

---

## 💾 BACKUP MANAGEMENT

### Backup API Integration

**Backend Endpoints:**
- `GET /api/admin/manage_backups.php?action=list` - List all backups
- `GET /api/admin/manage_backups.php?action=download&file={filename}` - Download backup
- `POST /api/admin/manage_backups.php?action=delete` - Delete backup

**Frontend Implementation:**

```typescript
// frontend/src/lib/backups.ts
export interface Backup {
    name: string;
    type: 'daily' | 'weekly';
    size: number;
    size_readable: string;
    created: string;
    compressed: boolean;
}

export async function listBackups(): Promise<Backup[]> {
    try {
        const response = await fetch('/api/admin/manage_backups.php?action=list');
        const data = await response.json();
        return data.success ? data.backups : [];
    } catch (error) {
        console.error('Failed to list backups:', error);
        return [];
    }
}

export async function downloadBackup(filename: string) {
    window.location.href = `/api/admin/manage_backups.php?action=download&file=${filename}`;
}

export async function deleteBackup(filename: string): Promise<boolean> {
    try {
        const response = await fetch('/api/admin/manage_backups.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file: filename })
        });
        const data = await response.json();
        return data.success;
    } catch (error) {
        console.error('Failed to delete backup:', error);
        return false;
    }
}
```

**Backup Management Component:**

```typescript
// frontend/src/components/BackupManager.tsx
import { useEffect, useState } from 'react';
import { listBackups, downloadBackup, deleteBackup, Backup } from '@/lib/backups';

export default function BackupManager() {
    const [backups, setBackups] = useState<Backup[]>([]);
    const [loading, setLoading] = useState(true);

    const loadBackups = async () => {
        setLoading(true);
        const data = await listBackups();
        setBackups(data);
        setLoading(false);
    };

    useEffect(() => {
        loadBackups();
    }, []);

    const handleDelete = async (filename: string) => {
        if (confirm('Delete this backup? This cannot be undone.')) {
            if (await deleteBackup(filename)) {
                alert('Backup deleted successfully');
                loadBackups();
            } else {
                alert('Failed to delete backup');
            }
        }
    };

    return (
        <div className="backup-manager">
            <h2>Database Backups</h2>
            
            <button onClick={loadBackups}>Refresh</button>

            {loading ? (
                <div>Loading backups...</div>
            ) : (
                <table>
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {backups.map((backup) => (
                            <tr key={backup.name}>
                                <td>{backup.name}</td>
                                <td>{backup.type}</td>
                                <td>{backup.size_readable}</td>
                                <td>{backup.created}</td>
                                <td>
                                    <button onClick={() => downloadBackup(backup.name)}>
                                        Download
                                    </button>
                                    <button onClick={() => handleDelete(backup.name)}>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
```

---

## 🚀 ENV VARIABLES

Add to `frontend/.env.local`:

```bash
# API Configuration
NEXT_PUBLIC_BACKEND_URL=http://localhost:8000
NEXT_PUBLIC_API_URL=http://localhost:8000/backend/api
NEXT_ADMIN_TOKEN=your_admin_token_here

# Features
NEXT_PUBLIC_ENABLE_SMS_FALLBACK=true
NEXT_PUBLIC_ENABLE_EXCEL_EXPORT=true
NEXT_PUBLIC_NOTIFICATION_REFRESH_INTERVAL=30000
```

---

## 🔒 SECURITY HEADERS

Add to `frontend/next.config.js`:

```javascript
async headers() {
    return [
        {
            source: '/:path*',
            headers: [
                {
                    key: 'Strict-Transport-Security',
                    value: 'max-age=31536000; includeSubDomains'
                },
                {
                    key: 'X-Content-Type-Options',
                    value: 'nosniff'
                },
                {
                    key: 'X-Frame-Options',
                    value: 'SAMEORIGIN'
                },
                {
                    key: 'X-XSS-Protection',
                    value: '1; mode=block'
                }
            ]
        }
    ]
}
```

---

## ✅ INTEGRATION CHECKLIST

- [ ] Add Next.js API routes for legal pages
- [ ] Add notification badge component to dashboard
- [ ] Add appointment search/filter component
- [ ] Add export to Excel functionality
- [ ] Add backup manager UI
- [ ] Implement auto-refresh for badges (30s interval)
- [ ] Add error handling for all API calls
- [ ] Test all APIs with admin authentication
- [ ] Verify HTTPS on all API calls
- [ ] Add loading states for all async operations

---

**Last Updated:** March 5, 2025
