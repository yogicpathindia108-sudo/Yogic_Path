from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from .config import settings
from .database import Base, engine
from .routers import leads, applications, courses

# Create SQLite database tables
Base.metadata.create_all(bind=engine)

app = FastAPI(
    title=settings.PROJECT_NAME,
    version=settings.VERSION,
    description="YogicPath Python Backend API - Leads, Applications, and Course Management"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(leads.router)
app.include_router(applications.router)
app.include_router(courses.router)

@app.get("/api/health")
def health_check():
    return {
        "status": "healthy",
        "service": "YogicPath Python Backend",
        "version": settings.VERSION
    }
