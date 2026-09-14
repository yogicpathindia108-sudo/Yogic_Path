from fastapi import APIRouter

router = APIRouter(prefix="/api/courses", tags=["Courses"])

COURSES_DATA = [
    {
        "id": 1,
        "title": "200 Hour Yoga Teacher Training",
        "slug": "200-hour-yoga-teacher-training-rishikesh",
        "duration_hours": 200,
        "location": "Rishikesh, India",
        "description": "Foundation training covering Hatha, Ashtanga, Pranayama, Philosophy, and Anatomy in the world yoga capital.",
        "price_double": 1499.0,
        "price_private": 1899.0,
        "batches": [
            {"date": "01–24 October 2026", "status": "available"},
            {"date": "01–24 November 2026", "status": "available"},
            {"date": "01–24 December 2026", "status": "available"}
        ]
    },
    {
        "id": 2,
        "title": "200 Hour Yoga Teacher Training",
        "slug": "200-hour-yoga-teacher-training-kerala",
        "duration_hours": 200,
        "location": "Varkala, Kerala, India",
        "description": "Oceanfront immersive training in Kerala with authentic Ayurvedic lifestyle and spiritual practices.",
        "price_double": 1699.0,
        "price_private": 2099.0,
        "batches": [
            {"date": "02–25 November 2026", "status": "available"},
            {"date": "02–25 December 2026", "status": "available"},
            {"date": "02–25 January 2027", "status": "available"}
        ]
    },
    {
        "id": 3,
        "title": "300 Hour Yoga Teacher Training",
        "slug": "300-hour-yoga-teacher-training-rishikesh",
        "duration_hours": 300,
        "location": "Rishikesh, India",
        "description": "Advanced training for 200-hour graduates focusing on sequencing, therapy, advanced philosophy, and adjustments.",
        "price_double": 1999.0,
        "price_private": 2499.0,
        "batches": [
            {"date": "01–28 November 2026", "status": "available"},
            {"date": "01–28 January 2027", "status": "available"}
        ]
    },
    {
        "id": 4,
        "title": "300 Hour Yoga Teacher Training",
        "slug": "300-hour-yoga-teacher-training-kerala",
        "duration_hours": 300,
        "location": "Varkala, Kerala, India",
        "description": "Transformational 300-hour master curriculum in serene coastal Kerala.",
        "price_double": 2200.0,
        "price_private": 3200.0,
        "batches": [
            {"date": "02–29 January 2027", "status": "available"},
            {"date": "02–29 September 2027", "status": "available"},
            {"date": "02–29 November 2027", "status": "available"}
        ]
    }
]

@router.get("/")
def get_courses():
    return COURSES_DATA

@router.get("/{slug}")
def get_course_by_slug(slug: str):
    for c in COURSES_DATA:
        if c["slug"] == slug:
            return c
    return {"error": "Course not found"}
