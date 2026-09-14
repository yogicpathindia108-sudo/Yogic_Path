import datetime
from typing import Optional
from pydantic import BaseModel, EmailStr

class LeadCreate(BaseModel):
    name: str
    email: EmailStr
    phone: Optional[str] = None
    brochure: Optional[str] = None
    message: Optional[str] = None
    source: Optional[str] = "website"

class LeadResponse(BaseModel):
    id: int
    name: str
    email: str
    phone: Optional[str] = None
    brochure_name: Optional[str] = None
    message: Optional[str] = None
    source: str
    created_at: datetime.datetime

    class Config:
        from_attributes = True

class ApplicationCreate(BaseModel):
    fullName: str
    email: EmailStr
    phone: Optional[str] = None
    courseSlug: str
    locationSlug: str
    batchDate: Optional[str] = None
    roomType: Optional[str] = "Double Sharing"
    paymentType: Optional[str] = "deposit"
    amount: Optional[float] = 500.0

class ApplicationResponse(BaseModel):
    id: int
    application_number: str
    full_name: str
    email: str
    phone: Optional[str] = None
    course_slug: str
    location_slug: str
    batch_date: Optional[str] = None
    room_type: str
    payment_type: str
    amount: float
    status: str
    created_at: datetime.datetime

    class Config:
        from_attributes = True

class CourseResponse(BaseModel):
    id: int
    title: str
    slug: str
    duration_hours: int
    location: str
    description: Optional[str] = None
    price_double: float
    price_private: float

    class Config:
        from_attributes = True
