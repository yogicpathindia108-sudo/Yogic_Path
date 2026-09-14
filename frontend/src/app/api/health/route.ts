import { NextResponse } from 'next/server';

const PYTHON_API_URL = process.env.PYTHON_API_URL || 'http://127.0.0.1:8000';

export async function GET() {
  let pythonStatus = 'unknown';
  try {
    const pyRes = await fetch(`${PYTHON_API_URL}/api/health`);
    if (pyRes.ok) {
      const data = await pyRes.json();
      pythonStatus = data.status || 'healthy';
    }
  } catch (e: any) {
    pythonStatus = 'offline: ' + e.message;
  }

  return NextResponse.json({
    status: 'healthy',
    frontend: 'Next.js 16',
    backend: `Python FastAPI (${pythonStatus})`,
    timestamp: new Date().toISOString(),
  });
}
