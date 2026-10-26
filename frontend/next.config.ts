import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  trailingSlash: true,
  reactStrictMode: false,
  outputFileTracingIncludes: {
    '/**': ['./cloned-pages/**/*'],
  },
};

export default nextConfig;
