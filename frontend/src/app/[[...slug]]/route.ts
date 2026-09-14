import { NextResponse, type NextRequest } from 'next/server';
import fs from 'fs';
import path from 'path';

const ROUTE_MAP: Record<string, string> = {
  '': 'home.html',
  'about-us': 'about-us.html',
  'yogicpath-story': 'yogicpath-story.html',
  'teacher-training-programs': 'teacher-training-programs.html',
  '200-hour-yoga-teacher-training-rishikesh': '200-hour-yoga-teacher-training-rishikesh.html',
  '200-hour-yoga-teacher-training-kerala': '200-hour-yoga-teacher-training-kerala.html',
  '300-hour-yoga-teacher-training-rishikesh': '300-hour-yoga-teacher-training-rishikesh.html',
  '300-hour-yoga-teacher-training-kerala': '300-hour-yoga-teacher-training-kerala.html',
  'events': 'events.html',
  'contact-us': 'contact-us.html',
  'yogic-path-course-registration': 'yogic-path-course-registration.html',
  'pricing-cancellation-policy': 'pricing-cancellation-policy.html',
  'terms-conditions': 'terms-conditions.html',
  'privacy-policy': 'terms-conditions.html',
  'blog': 'why-kerala-for-yoga-teacher-traing.html',
  'blogs': 'why-kerala-for-yoga-teacher-traing.html',
  '2026/08/23/why-kerala-for-yoga-teacher-traing': 'why-kerala-for-yoga-teacher-traing.html',
};

export async function GET(
  request: NextRequest,
  context: { params: Promise<{ slug?: string[] }> }
) {
  const resolvedParams = await context.params;
  const slugArray = resolvedParams.slug || [];
  const normalizedPath = slugArray.join('/').replace(/\/+$/, '');

  const fileName = ROUTE_MAP[normalizedPath] ?? ROUTE_MAP[''];
  const filePath = path.join(process.cwd(), 'cloned-pages', fileName);

  if (fs.existsSync(filePath)) {
    const html = fs.readFileSync(filePath, 'utf8');
    return new NextResponse(html, {
      headers: {
        'Content-Type': 'text/html; charset=utf-8',
      },
    });
  }

  return new NextResponse('Page not found', { status: 404 });
}
