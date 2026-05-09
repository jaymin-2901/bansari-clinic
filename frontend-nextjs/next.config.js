/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '',
        pathname: '/uploads/**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '8000',
        pathname: '/uploads/**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '8000',
        pathname: '/uploads/**',
      },
      {
        protocol: 'http',
        hostname: '**',
        port: '',
        pathname: '/uploads/**',
      },
      {
        protocol: 'https',
        hostname: 'bansari-homeopathic-clinic.infinityfreeapp.com',
        port: '',
        pathname: '/**',
      },
      {
        protocol: 'https',
        hostname: '*.infinityfreeapp.com',
        port: '',
        pathname: '/**',
      },
    ],
  },
  env: {
    NEXT_PUBLIC_API_URL: '/api/clinic',
    NEXT_PUBLIC_BACKEND_URL: 'http://localhost',
  },
  async headers() {
    return [
      {
        source: '/api/:path*',
        headers: [
          { key: 'Access-Control-Allow-Origin', value: '*' },
          { key: 'Access-Control-Allow-Methods', value: 'GET, POST, PUT, DELETE, OPTIONS' },
          { key: 'Access-Control-Allow-Headers', value: 'Content-Type, x-admin-api-key, x-admin-id, x-cron-secret, Authorization' },
        ],
      },
    ];
  },
  async rewrites() {
    return [
      {
        source: '/api/clinic/:path*',
        destination: 'http://localhost:8000/api/clinic/:path*',
      },
      {
        source: '/api/patient/:path*',
        destination: 'http://localhost:8000/api/patient/:path*',
      },
      {
        source: '/uploads/:path*',
        destination: 'http://localhost:8000/uploads/:path*',
      },
    ];
  },
};

module.exports = nextConfig;

