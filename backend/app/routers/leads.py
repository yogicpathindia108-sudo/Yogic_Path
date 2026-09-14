from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from ..database import get_db
from ..models import Lead
from ..schemas import LeadCreate, LeadResponse

router = APIRouter(prefix="/api/leads", tags=["Leads"])

@router.post("/", response_model=LeadResponse)
def create_lead(lead_in: LeadCreate, db: Session = Depends(get_db)):
    db_lead = Lead(
        name=lead_in.name,
        email=lead_in.email,
        phone=lead_in.phone,
        brochure_name=lead_in.brochure,
        message=lead_in.message,
        source=lead_in.source or "website"
    )
    db.add(db_lead)
    db.commit()
    db.refresh(db_lead)
    return db_lead

@router.get("/", response_model=list[LeadResponse])
def get_leads(skip: int = 0, limit: int = 50, db: Session = Depends(get_db)):
    leads = db.query(Lead).order_by(Lead.created_at.desc()).offset(skip).limit(limit).all()
    return leads
