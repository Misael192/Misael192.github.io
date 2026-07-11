import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  async rewrites() {
    // Em dev, o front fala com a API local; em produção a Vercel aponta
    // para a URL pública da API (Railway) via env.
    return [
      {
        source: "/api/v1/:path*",
        destination: `${process.env.API_URL ?? "http://localhost:3001"}/api/v1/:path*`,
      },
    ];
  },
};

export default nextConfig;
