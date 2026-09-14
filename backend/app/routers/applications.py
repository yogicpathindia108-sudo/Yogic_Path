import random
import datetime
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from ..database import get_db
from ..models import Application
from ..schemas import ApplicationCreate, ApplicationResponse

router = APIRouter(prefix="/api/applications", tags=["Applications"])

def generate_app_number():
    year = datetime.datetime.now().year
    rand_id = random.randint(1000, 9999)
    return f"YP-{year}-{rand_id}"

@router.post("/", response_model=ApplicationResponse)
def create_application(app_in: ApplicationCreate, db: Session = Depends(get_db)):
    app_number = generate_app_number()
    db_app = Application(
        application_number=app_number,
        full_name=app_in.fullName,
        email=app_in.email,
        phone=app_in.phone,
        course_slug=app_in.courseSlug,
        location_slug=app_in.locationSlug,
        batch_date=app_in.batchDate,
        room_type=app_in.roomType or "Double Sharing",
        payment_type=app_in.paymentType or "deposit",
        amount=app_in.amount or 500.0,
        status="registered"
    )
    db.add(db_app)
    db.commit()
    db.refresh(db_app)
    return db_app

@router.get("/", response_model=list[ApplicationResponse])
def get_applications(skip: int = 0, limit: int = 50, db: Session = Depends(get_db)):
    return db.query(Application).order_by(Application.created_at.desc()).offset(skip).limit(limit).all()
